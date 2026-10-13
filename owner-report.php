<?php
require __DIR__ . '/../config/owner-auth.php';
requireOwnerApi();

$month = trim((string)($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    jsonResponse(['success' => false, 'message' => 'Invalid month.'], 400);
}

// "all" = every order placed that month; "completed" = only Completed orders.
$basis = (($_GET['basis'] ?? 'all') === 'completed') ? 'completed' : 'all';
$cond = ($basis === 'completed') ? " AND o.status = 'Completed'" : '';

try {
    $start      = new DateTimeImmutable($month . '-01');
    $end        = $start->modify('first day of next month');
    $trendStart = $start->modify('-11 months');
    $daysInMonth = (int)$start->format('t');

    /* ---------- 12-month trend (orders + sales) ---------- */
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(o.order_date, '%Y-%m') AS ym,
                COUNT(*) AS orders,
                COALESCE(SUM(o.total_amount), 0) AS sales
         FROM orders o
         WHERE o.order_date >= ? AND o.order_date < ? $cond
         GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')"
    );
    $stmt->execute([$trendStart->format('Y-m-d'), $end->format('Y-m-d')]);
    $trendRows = [];
    foreach ($stmt->fetchAll() as $r) {
        $trendRows[$r['ym']] = $r;
    }

    /* ---------- 12-month trend (units sold) ---------- */
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(o.order_date, '%Y-%m') AS ym,
                COALESCE(SUM(i.quantity), 0) AS units
         FROM order_items i
         JOIN orders o ON o.id = i.order_id
         WHERE o.order_date >= ? AND o.order_date < ? $cond
         GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')"
    );
    $stmt->execute([$trendStart->format('Y-m-d'), $end->format('Y-m-d')]);
    $unitRows = [];
    foreach ($stmt->fetchAll() as $r) {
        $unitRows[$r['ym']] = (float)$r['units'];
    }

    $trend = [];
    for ($i = 0; $i < 12; $i++) {
        $m = $trendStart->modify("+$i months");
        $key = $m->format('Y-m');
        $trend[] = [
            'ym'     => $key,
            'label'  => $m->format('M Y'),
            'orders' => (int)($trendRows[$key]['orders'] ?? 0),
            'units'  => round($unitRows[$key] ?? 0, 2),
            'sales'  => round((float)($trendRows[$key]['sales'] ?? 0), 2),
        ];
    }

    /* ---------- Daily sales for the selected month ---------- */
    $stmt = $pdo->prepare(
        "SELECT DAY(o.order_date) AS d,
                COUNT(*) AS orders,
                COALESCE(SUM(o.total_amount), 0) AS sales
         FROM orders o
         WHERE o.order_date >= ? AND o.order_date < ? $cond
         GROUP BY DAY(o.order_date)"
    );
    $stmt->execute([$start->format('Y-m-d'), $end->format('Y-m-d')]);
    $dailyRows = [];
    foreach ($stmt->fetchAll() as $r) {
        $dailyRows[(int)$r['d']] = $r;
    }
    $daily = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $daily[] = [
            'day'    => $d,
            'orders' => (int)($dailyRows[$d]['orders'] ?? 0),
            'sales'  => round((float)($dailyRows[$d]['sales'] ?? 0), 2),
        ];
    }

    /* ---------- Products sold in the selected month ---------- */
    $stmt = $pdo->prepare(
        "SELECT MIN(TRIM(i.product_name)) AS name,
                SUM(i.quantity) AS qty,
                SUM(i.subtotal) AS sales,
                COUNT(DISTINCT i.order_id) AS orders
         FROM order_items i
         JOIN orders o ON o.id = i.order_id
         WHERE o.order_date >= ? AND o.order_date < ? $cond
         GROUP BY LOWER(TRIM(i.product_name))
         ORDER BY qty DESC, sales DESC
         LIMIT 100"
    );
    $stmt->execute([$start->format('Y-m-d'), $end->format('Y-m-d')]);
    $products = [];
    foreach ($stmt->fetchAll() as $r) {
        $products[] = [
            'name'   => (string)$r['name'],
            'qty'    => round((float)$r['qty'], 2),
            'sales'  => round((float)$r['sales'], 2),
            'orders' => (int)$r['orders'],
        ];
    }

    /* ---------- Summary + comparison with previous month ---------- */
    $current = $trend[11];
    $previous = $trend[10];
    $changePct = ($previous['sales'] > 0)
        ? round((($current['sales'] - $previous['sales']) / $previous['sales']) * 100, 1)
        : null;

    jsonResponse([
        'success'  => true,
        'month'    => $month,
        'label'    => $start->format('F Y'),
        'basis'    => $basis,
        'summary'  => [
            'sales'       => $current['sales'],
            'orders'      => $current['orders'],
            'units'       => $current['units'],
            'avg_order'   => $current['orders'] > 0 ? round($current['sales'] / $current['orders'], 2) : 0,
            'prev_sales'  => $previous['sales'],
            'change_pct'  => $changePct,
        ],
        'trend'    => $trend,
        'daily'    => $daily,
        'products' => $products,
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Unable to load the sales report.'], 500);
}
?>
