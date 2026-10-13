<?php
require __DIR__ . '/../config/auth.php';
requireOwnerApi();

try {
    $stmt = $pdo->query('SELECT * FROM orders ORDER BY id DESC');
    $orders = $stmt->fetchAll();

    $itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    foreach ($orders as &$order) {
        $itemStmt->execute([$order['id']]);
        $order['items'] = $itemStmt->fetchAll();
    }
    unset($order);

    jsonResponse($orders);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Unable to load reports.'], 500);
}
?>
