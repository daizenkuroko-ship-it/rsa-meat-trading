<?php
// RSA Meat Trading - Owner registration.
// Requires OWNER_SETUP_KEY (config/owner-auth.php) unless an owner is already signed in.

require __DIR__ . '/../config/owner-auth.php';
sendNoStore();

$message = '';
$type = '';
$done = false;
$fullName = $username = $email = '';
$tablesOk = true;
$ownerExists = false;

try {
    ensureOwnerTables($pdo);
    $ownerExists = (int)$pdo->query('SELECT COUNT(*) FROM owners')->fetchColumn() > 0;
} catch (Throwable $e) {
    $tablesOk = false;
    $message = 'Could not prepare the owner tables. Check your database connection in config/db.php.';
    $type = 'error';
}

// Not signed in as owner -> the registration key (OWNER_SETUP_KEY) is required.
// Signed-in owner -> may add another owner without the key.
$needsKey = !ownerLoggedIn();

$keyConfigured = OWNER_SETUP_KEY !== 'CHANGE-THIS-TO-A-LONG-RANDOM-PHRASE'
    && strlen(OWNER_SETUP_KEY) >= 12;

if ($tablesOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');
    $key      = (string)($_POST['reg_key'] ?? '');
    $ip       = clientIp();

    try {
        $blocked = $needsKey && tooManyAttempts($pdo, 'owner_login_attempts', $ip);

        if ($needsKey && !$keyConfigured) {
            $message = 'Set OWNER_SETUP_KEY in config/owner-auth.php first (at least 12 characters).';
            $type = 'error';
        } elseif ($blocked) {
            $message = 'Too many failed attempts. Please wait 15 minutes and try again.';
            $type = 'error';
        } elseif ($needsKey && !hash_equals(OWNER_SETUP_KEY, $key)) {
            $pdo->prepare('INSERT INTO owner_login_attempts (ip) VALUES (?)')->execute([$ip]);
            $message = 'Registration key is incorrect.';
            $type = 'error';
        } elseif ($fullName === '' || $username === '' || $email === '' || $password === '') {
            $message = 'Please complete all fields.';
            $type = 'error';
        } elseif (strlen($fullName) > 100 || strlen($email) > 120) {
            $message = 'Name or email is too long.';
            $type = 'error';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $message = 'Username must be 3-50 characters: letters, numbers, dot, dash or underscore.';
            $type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $type = 'error';
        } elseif (strlen($password) < 10) {
            $message = 'Password must be at least 10 characters.';
            $type = 'error';
        } elseif ($password !== $confirm) {
            $message = 'Passwords do not match.';
            $type = 'error';
        } else {
            $dup = $pdo->prepare('SELECT id FROM owners WHERE username = ? OR email = ? LIMIT 1');
            $dup->execute([$username, $email]);
            if ($dup->fetch()) {
                $message = 'That username or email is already registered.';
                $type = 'error';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO owners (full_name, username, email, password_hash) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$fullName, $username, $email, password_hash($password, PASSWORD_DEFAULT)]);
                auditLog($pdo, 'owner.register', 'owner', $pdo->lastInsertId(), ['username' => $username],
                    ['role' => $needsKey ? 'guest' : 'owner', 'id' => null, 'name' => $fullName]);
                $done = true;
                $fullName = $username = $email = '';
            }
        }
    } catch (Throwable $e) {
        $message = 'Could not register the owner account.';
        $type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Register Owner — RSA Meat Trading</title>
  <link rel="stylesheet" href="owner.css">
</head>
<body class="owner-login-body">
  <div class="owner-login-card">
    <div class="owner-badge">OWNER REGISTRATION</div>
    <h1>RSA Meat Trading</h1>
    <p class="owner-sub">Register an owner account</p>

    <?php if ($done): ?>
      <div class="owner-alert success">
        Owner account registered. <?= !$needsKey ? '<a href="dashboard.php">Back to dashboard</a>' : '' ?> You can now <a href="index.php">sign in to the Owner Portal</a>.
      </div>
    <?php endif; ?>

    <?php if ($needsKey && !$keyConfigured): ?>
      <div class="owner-alert error">
        Before registering, open <code>config/owner-auth.php</code> and replace
        <code>OWNER_SETUP_KEY</code> with your own long random phrase (12+ characters).
      </div>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
      <div class="owner-alert <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <?php if ($needsKey): ?>
      <div class="owner-field">
        <label>Registration key</label>
        <input type="password" name="reg_key" placeholder="OWNER_SETUP_KEY from config/owner-auth.php" required>
      </div>
      <?php endif; ?>
      <div class="owner-field">
        <label>Full name</label>
        <input type="text" name="full_name" maxlength="100" value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="owner-field">
        <label>Username</label>
        <input type="text" name="username" maxlength="50" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="owner-field">
        <label>Email</label>
        <input type="email" name="email" maxlength="120" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="owner-field">
        <label>Password (10+ characters)</label>
        <input type="password" name="password" required>
      </div>
      <div class="owner-field">
        <label>Confirm password</label>
        <input type="password" name="confirm_password" required>
      </div>
      <button type="submit" class="owner-btn">Register Owner</button>
    </form>

    <p class="owner-note"><?= !$needsKey ? '<a href="dashboard.php">Back to dashboard</a>' : 'Already registered? <a href="index.php">Sign in</a>' ?></p>
  </div>
</body>
</html>