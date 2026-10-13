<?php
require __DIR__ . '/../config/auth.php';
requireStaffApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = jsonInput();
$orderId = filter_var($input['order_id'] ?? null, FILTER_VALIDATE_INT);
$status = trim((string)($input['status'] ?? ''));
$allowed = ['Pending', 'Preparing', 'Ready', 'Completed'];

if (!$orderId || !in_array($status, $allowed, true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid order status.'], 400);
}

try {
    $cur = $pdo->prepare('SELECT order_number, status FROM orders WHERE id = ?');
    $cur->execute([$orderId]);
    $order = $cur->fetch();
    if (!$order) {
        jsonResponse(['success' => false, 'message' => 'Order not found.'], 404);
    }

    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$status, $orderId]);

    if ($order['status'] !== $status) {
        auditLog($pdo, 'order.status_change', 'order', $orderId, [
            'order_number' => $order['order_number'],
            'from'         => $order['status'],
            'to'           => $status,
        ]);
    }
    jsonResponse(['success' => true]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Unable to update order status.'], 500);
}
?>
