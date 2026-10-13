<?php
require __DIR__ . '/../config/auth.php';
sendNoStore();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = jsonInput();
$user = trim((string)($input['username'] ?? ''));
$pass = (string)($input['password'] ?? '');

if ($user === '' || $pass === '') {
    jsonResponse(['success' => false, 'message' => 'Username and password are required.'], 400);
}

$ip = clientIp();

try {
    ensureAdminSchema($pdo);

    if (tooManyAttempts($pdo, 'admin_login_attempts', $ip, 10)) {
        jsonResponse(['success' => false, 'message' => 'Too many failed attempts. Please wait 15 minutes and try again.'], 429);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1");
    $stmt->execute([$user, $user]);
    $row = $stmt->fetch();

    $valid = false;
    $needsUpgrade = false;

    if ($row) {
        // Supports old plain-text accounts one time, then upgrades them to a password hash.
        $valid = password_verify($pass, (string)$row['password']);
        if (!$valid && hash_equals((string)$row['password'], $pass)) {
            $valid = true;
            $needsUpgrade = true;
        }
    }

    if (!$valid) {
        $pdo->prepare('INSERT INTO admin_login_attempts (ip) VALUES (?)')->execute([$ip]);
        $pdo->exec('DELETE FROM admin_login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        auditLog($pdo, 'login.failed', 'admin', '', ['username' => substr($user, 0, 60)],
            ['role' => 'guest', 'id' => null, 'name' => substr($user, 0, 60)]);
        jsonResponse(['success' => false, 'message' => 'Invalid credentials.'], 401);
    }

    if ((int)($row['is_active'] ?? 1) !== 1) {
        auditLog($pdo, 'login.blocked', 'admin', $row['id'], ['reason' => 'account disabled'],
            ['role' => 'admin', 'id' => $row['id'], 'name' => (string)$row['full_name']]);
        jsonResponse(['success' => false, 'message' => 'This account has been disabled. Please contact the owner.'], 403);
    }

    if ($needsUpgrade) {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
    }

    $pdo->prepare('DELETE FROM admin_login_attempts WHERE ip = ?')->execute([$ip]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['full_name'] = $row['full_name'];
    $_SESSION['admin_last_activity'] = time();

    auditLog($pdo, 'login.success', 'admin', $row['id'], [],
        ['role' => 'admin', 'id' => $row['id'], 'name' => (string)$row['full_name']]);

    jsonResponse(['success' => true]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Login is temporarily unavailable.'], 500);
}
?>
