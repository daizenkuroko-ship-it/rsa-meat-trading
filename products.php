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
  <title>Products &amp; Prices — RSA Meat Trading</title>
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
        <h1>Products &amp; Prices</h1>
        <p class="owner-muted">The official price list. Orders always use these prices — admins cannot change them. Every price change is recorded in the Audit Log.</p>
      </div>
    </section>

    <div class="owner-alert" id="msg" style="display:none;"></div>

    <section class="adm-grid">
      <div class="owner-card">
        <h3>Add product</h3>
        <form id="addForm" autocomplete="off">
          <div class="owner-field"><label>Product name</label><input type="text" id="nName" maxlength="100" required></div>
          <div class="owner-field"><label>Price per kg (₱)</label><input type="number" id="nPrice" step="0.01" min="0" required></div>
          <button type="submit" class="owner-btn">Add Product</button>
        </form>
      </div>
      <div class="owner-card">
        <h3>How it works</h3>
        <p class="owner-muted" style="font-size:14px;line-height:1.7;">
          When creating an order, staff pick a product from this list and the price is filled in by the server.
          Past orders keep the price they were saved with, so changing a price here only affects <strong>new</strong> orders.
          Disable a product to hide it from new orders without losing history.
        </p>
      </div>
    </section>

    <section class="owner-card">
      <h3>Price list</h3>
      <div class="table-wrap">
        <table class="owner-table">
          <thead><tr><th>Product</th><th style="width:160px">Price per kg (₱)</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="prodBody"><tr><td colspan="4" class="empty">Loading…</td></tr></tbody>
        </table>
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

    var API = '../api/products.php';
    var products = [];

    async function load() {
      try {
        var d = await call(API + '?all=1&_=' + Date.now(), 'GET');
        products = d.products;
        $('prodBody').innerHTML = products.length ? products.map(function (p) {
          return '<tr data-id="' + p.id + '">' +
            '<td><input class="prod-input" data-f="name" maxlength="100" value="' + esc(p.name) + '"></td>' +
            '<td><input class="prod-input" data-f="price" type="number" step="0.01" min="0" value="' + p.price_per_kg.toFixed(2) + '"></td>' +
            '<td><span class="badge ' + (p.is_active ? 'on">Active' : 'off">Disabled') + '</span></td>' +
            '<td><div class="row-actions">' +
            '<button class="owner-btn-sm" data-act="save">Save</button>' +
            '<button class="owner-btn-sm" data-act="toggle">' + (p.is_active ? 'Disable' : 'Enable') + '</button>' +
            '</div></td></tr>';
        }).join('') : '<tr><td colspan="4" class="empty">No products yet. Add your first product above.</td></tr>';
      } catch (e) { show(e.message, false); }
    }

    $('prodBody').addEventListener('click', async function (e) {
      var b = e.target.closest('button[data-act]');
      if (!b) return;
      var tr = b.closest('tr');
      var id = parseInt(tr.dataset.id, 10);
      var p = products.find(function (x) { return x.id === id; });
      try {
        var r;
        if (b.dataset.act === 'save') {
          r = await call(API, 'POST', {
            action: 'update', id: id,
            name: tr.querySelector('[data-f=name]').value.trim(),
            price_per_kg: tr.querySelector('[data-f=price]').value
          });
        } else {
          r = await call(API, 'POST', { action: 'set_active', id: id, active: !p.is_active });
        }
        show(r.message, true);
        load();
      } catch (err) { show(err.message, false); }
    });

    $('addForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      try {
        var r = await call(API, 'POST', { action: 'create', name: $('nName').value.trim(), price_per_kg: $('nPrice').value });
        show(r.message, true);
        $('addForm').reset();
        load();
      } catch (err) { show(err.message, false); }
    });

    load();
  })();
  </script>
</body>
</html>
