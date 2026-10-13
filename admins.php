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
  <title>Manage Admins — RSA Meat Trading</title>
  <script src="../js/auth-guard.js"></script>
  <link rel="stylesheet" href="owner.css">
  <style>
    .adm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: start; }
    @media (max-width: 800px) { .adm-grid { grid-template-columns: 1fr; } }
    .adm-card form .owner-field input { width: 100%; }
    .badge { display: inline-block; padding: 2px 9px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .badge.on { background: #e5f7eb; color: #08752b; }
    .badge.off { background: #ffe6eb; color: #b00020; }
    .row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .access-list { font-size: 14px; line-height: 1.7; color: #444; padding-left: 18px; }
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
        <h1>Admin Accounts</h1>
        <p class="owner-muted">Only the owner can create, disable or reset admin accounts.</p>
      </div>
    </section>

    <div class="owner-alert" id="msg" style="display:none;"></div>

    <section class="adm-grid">
      <div class="owner-card adm-card">
        <h3>Create admin account</h3>
        <form id="createForm" autocomplete="off">
          <div class="owner-field"><label>Full name</label><input type="text" id="fFull" maxlength="100" required></div>
          <div class="owner-field"><label>Username</label><input type="text" id="fUser" maxlength="50" required></div>
          <div class="owner-field"><label>Email</label><input type="email" id="fEmail" maxlength="120" required></div>
          <div class="owner-field"><label>Password (8+ characters)</label><input type="password" id="fPass" minlength="8" required></div>
          <button type="submit" class="owner-btn">Create Admin</button>
        </form>
      </div>

      <div class="owner-card">
        <h3>What an admin can access</h3>
        <ul class="access-list">
          <li><strong>Can:</strong> Dashboard (order counts), view orders, create orders, update order status, view customers</li>
          <li><strong>Cannot:</strong> Sales reports &amp; revenue analytics, Owner Portal, creating or managing accounts</li>
        </ul>
        <p class="owner-muted" style="margin-top:10px;font-size:13px;">A disabled admin is signed out immediately and cannot log in again until you enable the account.</p>
      </div>
    </section>

    <section class="owner-card">
      <h3>Admins</h3>
      <div class="table-wrap">
        <table class="owner-table">
          <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="adminBody"><tr><td colspan="5" class="empty">Loading…</td></tr></tbody>
        </table>
      </div>
    </section>
  </main>

  <script>
  (function () {
    var API = '../api/admins.php';
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
    async function call(method, body) {
      var res = await fetch(API + (method === 'GET' ? '?_=' + Date.now() : ''), {
        method: method, credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined
      });
      if (res.status === 401) { window.location.href = 'index.php'; throw new Error('Session expired.'); }
      var data = await res.json().catch(function () { return {}; });
      if (!res.ok || !data.success) throw new Error(data.message || 'Request failed.');
      return data;
    }

    var admins = [];
    async function load() {
      try {
        var d = await call('GET');
        admins = d.admins;
        var body = $('adminBody');
        if (!admins.length) { body.innerHTML = '<tr><td colspan="5" class="empty">No admin accounts yet.</td></tr>'; return; }
        body.innerHTML = admins.map(function (a) {
          return '<tr><td>' + esc(a.full_name) + '</td><td>' + esc(a.username) + '</td><td>' + esc(a.email) + '</td>' +
            '<td><span class="badge ' + (a.is_active ? 'on">Active' : 'off">Disabled') + '</span></td>' +
            '<td><div class="row-actions">' +
            '<button class="owner-btn-sm" data-act="toggle" data-id="' + a.id + '">' + (a.is_active ? 'Disable' : 'Enable') + '</button>' +
            '<button class="owner-btn-sm" data-act="reset" data-id="' + a.id + '">Reset password</button>' +
            '</div></td></tr>';
        }).join('');
      } catch (e) { show(e.message, false); }
    }

    $('adminBody').addEventListener('click', async function (e) {
      var b = e.target.closest('button[data-act]');
      if (!b) return;
      var id = parseInt(b.dataset.id, 10);
      var a = admins.find(function (x) { return x.id === id; });
      if (!a) return;
      try {
        if (b.dataset.act === 'toggle') {
          var verb = a.is_active ? 'Disable' : 'Enable';
          if (!confirm(verb + ' the account of ' + a.full_name + '?')) return;
          var r = await call('POST', { action: 'set_active', id: id, active: !a.is_active });
          show(r.message, true);
        } else {
          var pw = prompt('New password for ' + a.username + ' (at least 8 characters):');
          if (pw === null) return;
          var r2 = await call('POST', { action: 'reset_password', id: id, password: pw });
          show(r2.message, true);
        }
        load();
      } catch (err) { show(err.message, false); }
    });

    $('createForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      try {
        var r = await call('POST', {
          action: 'create',
          full_name: $('fFull').value.trim(), username: $('fUser').value.trim(),
          email: $('fEmail').value.trim(), password: $('fPass').value
        });
        show(r.message, true);
        $('createForm').reset();
        load();
      } catch (err) { show(err.message, false); }
    });

    $('logoutBtn').addEventListener('click', async function () {
      try { await fetch('../api/owner-logout.php', { method: 'POST', credentials: 'same-origin' }); } catch (e) {}
      window.location.href = 'index.php';
    });

    load();
  })();
  </script>
</body>
</html>
