<?php
require __DIR__ . '/../config/owner-auth.php';
sendNoStore();

if (ownerLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Owner Portal — RSA Meat Trading</title>
  <link rel="stylesheet" href="owner.css">
</head>
<body class="owner-login-body">
  <div class="owner-login-card">
    <div class="owner-badge">OWNER ACCESS ONLY</div>
    <h1>RSA Meat Trading</h1>
    <p class="owner-sub">Owner Portal — Sales &amp; Performance</p>

    <div class="owner-alert error" id="ownerError" style="display:none;"></div>

    <form id="ownerLoginForm" autocomplete="off">
      <div class="owner-field">
        <label for="ownerUser">Owner username / email</label>
        <input type="text" id="ownerUser" required autofocus>
      </div>
      <div class="owner-field">
        <label for="ownerPass">Password</label>
        <input type="password" id="ownerPass" required>
      </div>
      <button type="submit" class="owner-btn" id="ownerLoginBtn">Sign in as Owner</button>
    </form>

    <p class="owner-note">Restricted to the business owner. Staff accounts cannot sign in here.</p>
  </div>

  <script>
    document.getElementById('ownerLoginForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      const box = document.getElementById('ownerError');
      const btn = document.getElementById('ownerLoginBtn');
      box.style.display = 'none';
      btn.disabled = true;
      btn.textContent = 'Signing in…';

      try {
        const res = await fetch('../api/owner-login.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            username: document.getElementById('ownerUser').value.trim(),
            password: document.getElementById('ownerPass').value
          })
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.success) {
          window.location.href = 'dashboard.php';
          return;
        }
        box.textContent = data.message || 'Sign in failed.';
      } catch (err) {
        box.textContent = 'Could not reach the server. Please try again.';
      }
      box.style.display = 'block';
      btn.disabled = false;
      btn.textContent = 'Sign in as Owner';
    });
  </script>
</body>
</html>
