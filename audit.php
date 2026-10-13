<?php
require __DIR__ . '/../config/owner-auth.php';
requireOwnerPage();

$ownerName = htmlspecialchars((string)($_SESSION['owner_name'] ?? 'Owner'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Audit Log — RSA Meat Trading</title>
  <script src="../js/auth-guard.js"></script>
  <link rel="stylesheet" href="owner.css">
  <style>
    .adm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: start; margin-bottom: 20px; }
    @media (max-width: 800px) { .adm-grid { grid-template-columns: 1fr; } }
    .badge { display: inline-block; padding: 2px 9px; border-radius: 12px; font-size: 12px; font-weight: 600; background:#eee; color:#444; }
    .badge.on { background: #e5f7eb; color: #08752b; }
    .badge.off { background: #ffe6eb; color: #b00020; }
    .row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .filters { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:14px; }
    .filters label { font-size:12px; color:#555; display:flex; flex-direction:column; gap:4px; }
    .filters input, .filters select { padding:8px; border:1px solid #ccc; border-radius:6px; font-size:14px; }
    .pager { display:flex; gap:10px; align-items:center; justify-content:flex-end; margin-top:12px; font-size:13px; }
    td.det { font-size:13px; color:#444; max-width:380px; }
    .prod-input { width:100%; padding:7px; border:1px solid #ccc; border-radius:6px; font-size:14px; }
  </style>
</head>
<body class="owner-body">

  <aside class="sidebar" id="sidebar">
    <div class="brand">RSA Meat Trading</div>
    <nav class="nav"></nav>
    <div class="logout"><a href="#" id="logoutBtn">Logout</a></div>
  </aside>

  <main class="main">
    <button class="menu-toggle" id="menuToggle" type="button" aria-label="Open menu">☰</button>
    <section class="owner-controls">
      <div>
        <h1>Audit Log</h1>
        <p class="owner-muted">Who did what, and when. Only the owner can see this.</p>
      </div>
    </section>

    <div class="owner-alert" id="msg" style="display:none;"></div>

    <section class="owner-card">
      <form class="filters" id="filterForm">
        <label>Type
          <select id="fGroup">
            <option value="">Everything</option>
            <option value="order">Orders</option>
            <option value="login">Logins</option>
            <option value="product">Products &amp; prices</option>
            <option value="admin">Admin accounts</option>
            <option value="owner">Owner accounts</option>
          </select>
        </label>
        <label>Who
          <select id="fRole">
            <option value="">Anyone</option>
            <option value="owner">Owner</option>
            <option value="admin">Admin</option>
            <option value="guest">Not signed in</option>
          </select>
        </label>
        <label>From <input type="date" id="fFrom"></label>
        <label>To <input type="date" id="fTo"></label>
        <label>Search <input type="text" id="fQ" placeholder="name, order no., product"></label>
        <button type="submit" class="owner-btn-sm">Filter</button>
      </form>

      <div class="table-wrap">
        <table class="owner-table">
          <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
          <tbody id="logBody"><tr><td colspan="5" class="empty">Loading…</td></tr></tbody>
        </table>
      </div>
      <div class="pager">
        <button class="owner-btn-sm" id="prevBtn" type="button">Previous</button>
        <span id="pageInfo"></span>
        <button class="owner-btn-sm" id="nextBtn" type="button">Next</button>
      </div>
    </section>
  </main>

  <script>
  (function () {
    function $(id) { return document.getElementById(id); }
    function esc(v) {
      return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function show(text, ok) {
      var m = $('msg');
      m.className = 'owner-alert ' + (ok ? 'success' : 'error');
      m.textContent = text;
      m.style.display = 'block';
    }
    async function call(url, method, body) {
      var res = await fetch(url, {
        method: method, credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined
      });
      if (res.status === 401) { window.location.href = 'index.php'; throw new Error('Session expired.'); }
      var data = await res.json().catch(function () { return {}; });
      if (!res.ok || !data.success) throw new Error(data.message || 'Request failed.');
      return data;
    }
    $('logoutBtn').addEventListener('click', async function () {
      try { await fetch('../api/owner-logout.php', { method: 'POST', credentials: 'same-origin' }); } catch (e) {}
      window.location.href = 'index.php';
    });

    var LABELS = {
      'order.create': 'Created order', 'order.status_change': 'Changed order status',
      'login.success': 'Signed in', 'login.failed': 'Failed sign-in', 'login.blocked': 'Blocked sign-in',
      'product.create': 'Added product', 'product.update': 'Edited product', 'product.price_change': 'Changed price',
      'product.enable': 'Enabled product', 'product.disable': 'Disabled product',
      'admin.create': 'Created admin', 'admin.enable': 'Enabled admin', 'admin.disable': 'Disabled admin',
      'admin.reset_password': 'Reset admin password', 'owner.register': 'Registered owner'
    };
    var ROLES = { owner: 'Owner', admin: 'Admin', guest: 'Not signed in' };

    function peso(n) { return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function describe(r) {
      var d = r.details || {};
      switch (r.action) {
        case 'order.create':
          return esc(d.order_number) + ' · ' + esc(d.company) + ' · ' + esc(d.items) + ' item(s) · ' + peso(d.total);
        case 'order.status_change':
          return esc(d.order_number) + ': ' + esc(d.from) + ' → ' + esc(d.to);
        case 'product.price_change':
          return esc(d.product) + ': ' + peso(d.price && d.price[0]) + ' → ' + peso(d.price && d.price[1]);
        case 'product.create':
          return esc(d.name) + ' at ' + peso(d.price);
        case 'login.failed':
          return 'Username tried: ' + esc(d.username);
        case 'login.blocked':
          return esc(d.reason);
        default:
          var parts = [];
          Object.keys(d).forEach(function (k) {
            var v = d[k];
            parts.push(esc(k) + ': ' + esc(Array.isArray(v) ? v.join(' → ') : v));
          });
          return parts.join(' · ') || esc(r.entity_id);
      }
    }

    var page = 1, pages = 1;
    async function load() {
      var qs = new URLSearchParams({
        group: $('fGroup').value, role: $('fRole').value, from: $('fFrom').value,
        to: $('fTo').value, q: $('fQ').value.trim(), page: page, _: Date.now()
      });
      try {
        var d = await call('../api/audit.php?' + qs.toString(), 'GET');
        pages = d.pages;
        $('msg').style.display = 'none';
        $('logBody').innerHTML = d.rows.length ? d.rows.map(function (r) {
          var bad = r.action === 'login.failed' || r.action === 'login.blocked';
          return '<tr><td>' + esc(r.created_at) + '</td>' +
            '<td>' + esc(r.actor_name) + ' <span class="badge ' + (r.actor_role === 'guest' ? 'off' : '') + '">' + esc(ROLES[r.actor_role] || r.actor_role) + '</span></td>' +
            '<td><span class="badge ' + (bad ? 'off' : 'on') + '">' + esc(LABELS[r.action] || r.action) + '</span></td>' +
            '<td class="det">' + describe(r) + '</td><td>' + esc(r.ip) + '</td></tr>';
        }).join('') : '<tr><td colspan="5" class="empty">Nothing recorded yet.</td></tr>';
        $('pageInfo').textContent = 'Page ' + d.page + ' of ' + d.pages + ' · ' + d.total + ' record(s)';
        $('prevBtn').disabled = d.page <= 1;
        $('nextBtn').disabled = d.page >= d.pages;
      } catch (e) { show(e.message, false); }
    }

    $('filterForm').addEventListener('submit', function (e) { e.preventDefault(); page = 1; load(); });
    $('prevBtn').addEventListener('click', function () { if (page > 1) { page--; load(); } });
    $('nextBtn').addEventListener('click', function () { if (page < pages) { page++; load(); } });
    load();
  })();
  </script>
</body>
</html>
