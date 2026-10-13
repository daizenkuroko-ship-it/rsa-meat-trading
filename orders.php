<?php
require __DIR__ . '/../config/auth.php';
requireStaffApi();

$action = $_GET['action'] ?? 'list';

try {
    if ($action === 'list') {
        $status = trim((string)($_GET['status'] ?? 'All'));
        $search = trim((string)($_GET['search'] ?? ''));

        $sql = "SELECT * FROM orders";
        $params = [];
        $conds = [];

        if ($status !== 'All') {
            $conds[] = 'status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $conds[] = '(order_number LIKE ? OR company_name LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($conds) $sql .= ' WHERE ' . implode(' AND ', $conds);
        $sql .= ' ORDER BY created_at DESC, id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        $itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        foreach ($orders as &$order) {
            $itemStmt->execute([$order['id']]);
            $order['items'] = $itemStmt->fetchAll();
        }
        unset($order);

        jsonResponse($orders);
    }

    if ($action === 'stats') {
        $result = [];
        foreach (['Total', 'Pending', 'Preparing', 'Ready', 'Completed'] as $status) {
            if ($status === 'Total') {
                $stmt = $pdo->query('SELECT COUNT(*) FROM orders');
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE status = ?');
                $stmt->execute([$status]);
            }
            $result[lcfirst($status)] = (int)$stmt->fetchColumn();
        }
        jsonResponse($result);
    }

    jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Unable to load orders.'], 500);
}
?>
