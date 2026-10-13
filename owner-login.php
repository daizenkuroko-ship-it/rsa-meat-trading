<?php
require __DIR__ . '/../config/owner-auth.php';
sendNoStore();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = jsonInput();
$login = trim((string)($input['username'] ?? ''));
$pass  = (string)($input['password'] ?? '');

if ($login === '' || $pass === '') {
    jsonResponse(['success' => false, 'message' => 'Username and password are required.'], 400);
}

$ip = clientIp();

try {
    ensureOwnerTables($pdo);

    // Throttle: too many failed attempts from one IP in the last 15 minutes.
    if (tooManyAttempts($pdo, 'owner_login_attempts', $ip)) {
        jsonResponse([
            'success' => false,
            'message' => 'Too many failed attempts. Please wait 15 minutes and try again.'
        ], 429);
    }

    $stmt = $pdo->prepare(
        'SELECT id, full_name, password_hash FROM owners
         WHERE username = ? OR email = ? LIMIT 1'
    );
    $stmt->execute([$login, $login]);
    $owner = $stmt->fetch();

    // Always run password_verify so response time does not reveal whether the account exists.
    $dummyHash = '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOPQRSTUVWXYZ01234';
    $hash = $owner ? (string)$owner['password_hash'] : $dummyHash;
    $valid = password_verify($pass, $hash) && $owner;

    if (!$valid) {
        $pdo->prepare('INSERT INTO owner_login_attempts (ip) VALUES (?)')->execute([$ip]);
        $pdo->exec('DELETE FROM owner_login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        auditLog($pdo, 'login.failed', 'owner', '', ['username' => substr($login, 0, 60)],
            ['role' => 'guest', 'id' => null, 'name' => substr($login, 0, 60)]);
        jsonResponse(['success' => false, 'message' => 'Invalid owner credentials.'], 401);
    }

    $pdo->prepare('DELETE FROM owner_login_attempts WHERE ip = ?')->execute([$ip]);

    session_regenerate_id(true);
    $_SESSION['owner_id'] = (int)$owner['id'];
    $_SESSION['owner_name'] = (string)$owner['full_name'];
    $_SESSION['owner_last_activity'] = time();

    auditLog($pdo, 'login.success', 'owner', $owner['id'], [],
        ['role' => 'owner', 'id' => (int)$owner['id'], 'name' => (string)$owner['full_name']]);

    jsonResponse(['success' => true]);
} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Owner login is not available yet. Register the owner first at owner/register-owner.php.'
    ], 500);
}
?>
