<?php
require __DIR__ . '/../config/auth.php';
requireStaffApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = jsonInput();
$items = $input['items'] ?? [];
$allowedStatuses = ['Pending', 'Preparing', 'Ready', 'Completed'];

if (!is_array($items) || count($items) === 0) {
    jsonResponse(['success' => false, 'message' => 'At least one order item is required.'], 400);
}

foreach (['company_name', 'order_date', 'delivery_date'] as $field) {
    if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
        jsonResponse(['success' => false, 'message' => "Missing required field: $field"], 400);
    }
}

$status = trim((string)($input['status'] ?? 'Pending'));
if (!in_array($status, $allowedStatuses, true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid order status.'], 400);
}

$orderDate = trim((string)$input['order_date']);
$deliveryDate = trim((string)$input['delivery_date']);
$validDate = static function (string $date): bool {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
};
if (!$validDate($orderDate) || !$validDate($deliveryDate)) {
    jsonResponse(['success' => false, 'message' => 'Invalid order or delivery date.'], 400);
}

// Prices come from the official price list (products table), NOT from the browser.
// The browser only tells us which product and how many.
try {
    ensureProductsTable($pdo);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Unable to load the product price list.'], 500);
}

$cleanItems = [];
$total = 0.0;
$lookup = $pdo->prepare('SELECT id, name, price_per_kg FROM products WHERE id = ? AND is_active = 1');
foreach ($items as $item) {
    $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = is_numeric($item['quantity'] ?? null) ? (float)$item['quantity'] : 0.0;

    if (!$productId || $quantity <= 0 || $quantity > 100000) {
        jsonResponse(['success' => false, 'message' => 'Please check every product and quantity.'], 400);
    }

    $lookup->execute([$productId]);
    $product = $lookup->fetch();
    if (!$product) {
        jsonResponse(['success' => false, 'message' => 'A selected product is no longer available. Please reload the page.'], 400);
    }

    $price = (float)$product['price_per_kg'];
    $subtotal = round($quantity * $price, 2);
    $total += $subtotal;
    $cleanItems[] = [(string)$product['name'], $quantity, $price, $subtotal];
}
$total = round($total, 2);

try {
    $pdo->beginTransaction();

    $requestedOrderNumber = trim((string)($input['order_number'] ?? ''));
    $orderNumber = $requestedOrderNumber !== '' ? $requestedOrderNumber : ('RSA-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2))));

    $stmt = $pdo->prepare("INSERT INTO orders
        (order_number, company_name, contact_person, phone, email, address, order_date, delivery_date, status, total_amount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    try {
        $stmt->execute([
            $orderNumber,
            trim((string)$input['company_name']),
            trim((string)($input['contact_person'] ?? '')),
            trim((string)($input['phone'] ?? '')),
            trim((string)($input['email'] ?? '')),
            trim((string)($input['address'] ?? '')),
            $orderDate,
            $deliveryDate,
            $status,
            $total
        ]);
    } catch (PDOException $e) {
        if ((int)$e->errorInfo[1] === 1062 && $requestedOrderNumber !== '') {
            $orderNumber = 'RSA-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt->execute([
                $orderNumber,
                trim((string)$input['company_name']),
                trim((string)($input['contact_person'] ?? '')),
                trim((string)($input['phone'] ?? '')),
                trim((string)($input['email'] ?? '')),
                trim((string)($input['address'] ?? '')),
                $orderDate,
                $deliveryDate,
                $status,
                $total
            ]);
        } else {
            throw $e;
        }
    }

    $orderId = $pdo->lastInsertId();
    $itemStmt = $pdo->prepare("INSERT INTO order_items
        (order_id, product_name, quantity, price_per_unit, subtotal) VALUES (?, ?, ?, ?, ?)");

    foreach ($cleanItems as [$product, $quantity, $price, $subtotal]) {
        $itemStmt->execute([$orderId, $product, $quantity, $price, $subtotal]);
    }

    $pdo->commit();

    auditLog($pdo, 'order.create', 'order', $orderId, [
        'order_number' => $orderNumber,
        'company'      => trim((string)$input['company_name']),
        'status'       => $status,
        'items'        => count($cleanItems),
        'total'        => $total,
    ]);

    jsonResponse([
        'success' => true,
        'order_id' => (int)$orderId,
        'order_number' => $orderNumber,
        'total_amount' => $total
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => 'Unable to save the order.'], 500);
}
?>
