<?php
require __DIR__ . '/../config/owner-auth.php';
requireOwnerPage();

$ownerName = htmlspecialchars((string)($_SESSION['owner_name'] ?? 'Owner'), ENT_QUOTES, 'UTF-8');
$currentMonth = date('Y-m');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Owner Dashboard — RSA Meat Trading</title>
  <script src="../js/auth-guard.js"></script>
  <link rel="stylesheet" href="owner.css">
</head>
<body class="owner-body">

  <aside class="sidebar" id="sidebar">
    <div class="brand">RSA Meat Trading</div>
    <nav class="nav"></nav>
    <div class="logout"><a href="#" id="logoutBtn">Logout</a></div>
  </aside>

  <main class="main">
    <button class="menu-toggle" id="menuToggle" type="button" aria-label="Open menu">☰</button>

    <div class="print-only">
      <h2>RSA Meat Trading — Owner Sales Report</h2>
      <p>Generated: <span id="printDate"></span></p>
    </div>

    <section class="owner-controls">
      <div>
        <h1 id="reportTitle">Monthly Sales Report</h1>
        <p class="owner-muted" id="reportSub">Loading…</p>
      </div>
      <div class="owner-filters">
        <label>Month
          <input type="month" id="monthPicker" value="<?= $currentMonth ?>" max="<?= $currentMonth ?>">
        </label>
        <label>Count
          <select id="basisPicker">
            <option value="all">All orders</option>
            <option value="completed">Completed orders only</option>
          </select>
        </label>
        <button class="owner-btn-sm" id="printBtn" type="button" style="align-self:flex-end;">Print</button>
      </div>
    </section>

    <div class="owner-alert error" id="reportError" style="display:none;"></div>

    <!-- KPI cards -->
    <section class="kpi-grid">
      <div class="kpi-card">
        <span class="kpi-label">Total Sales</span>
        <span class="kpi-value" id="kpiSales">—</span>
        <span class="kpi-sub" id="kpiChange">&nbsp;</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Orders</span>
        <span class="kpi-value" id="kpiOrders">—</span>
        <span class="kpi-sub">in the month</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Average Order Value</span>
        <span class="kpi-value" id="kpiAvg">—</span>
        <span class="kpi-sub">sales ÷ orders</span>
      </div>
      <div class="kpi-card">
        <span class="kpi-label">Quantity Sold</span>
        <span class="kpi-value" id="kpiUnits">—</span>
        <span class="kpi-sub">kg across all products</span>
      </div>
    </section>

    <!-- Charts -->
    <section class="chart-grid">
      <div class="owner-card">
        <h3>Monthly Sales — Last 12 Months</h3>
        <div class="chart-box"><canvas id="trendChart"></canvas></div>
      </div>
      <div class="owner-card">
        <h3>Daily Sales — <span class="month-name">this month</span></h3>
        <div class="chart-box"><canvas id="dailyChart"></canvas></div>
      </div>
      <div class="owner-card">
        <div class="card-head">
          <h3>Best Sellers — <span class="month-name">this month</span></h3>
          <select id="rankPicker" class="owner-select-sm">
            <option value="qty">Rank by quantity (kg)</option>
            <option value="sales">Rank by sales (₱)</option>
          </select>
        </div>
        <div class="chart-box"><canvas id="bestChart"></canvas></div>
      </div>
      <div class="owner-card">
        <h3>Share of Sales by Product</h3>
        <div class="chart-box"><canvas id="shareChart"></canvas></div>
      </div>
    </section>

    <!-- Best sellers table -->
    <section class="owner-card">
      <h3>Best-Selling Products — <span class="month-name">this month</span></h3>
      <div class="table-wrap">
        <table class="owner-table">
          <thead>
            <tr><th>#</th><th>Product</th><th class="num">Quantity (kg)</th><th class="num">Sales</th><th class="num">Orders</th></tr>
          </thead>
          <tbody id="bestBody"><tr><td colspan="5" class="empty">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>

    <!-- Monthly report table -->
    <section class="owner-card">
      <div class="card-head">
        <h3>Monthly Sales Report</h3>
        <button class="owner-btn-sm" id="csvBtn" type="button">Download CSV</button>
      </div>
      <div class="table-wrap">
        <table class="owner-table">
          <thead>
            <tr><th>Month</th><th class="num">Orders</th><th class="num">Quantity (kg)</th><th class="num">Sales</th><th class="num">Change</th></tr>
          </thead>
          <tbody id="monthlyBody"><tr><td colspan="5" class="empty">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>

  </main>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <script src="owner.js"></script>
</body>
</html>
