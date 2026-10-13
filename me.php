<?php
// Tells the pages who is signed in and which sections they may open.
require __DIR__ . '/../config/auth.php';
sendNoStore();

$role = currentRole();
if ($role === null) {
    jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

jsonResponse([
    'success' => true,
    'role'    => $role,
    'name'    => currentName(),
    'can'     => [
        'orders'    => true,
        'create'    => true,
        'customers' => true,
        'reports'   => $role === 'owner',
        'admins'    => $role === 'owner',
    ],
]);
?>
