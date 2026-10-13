<?php
require __DIR__ . '/../config/owner-auth.php';
sendNoStore();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

ownerClearSession();
session_regenerate_id(true);
jsonResponse(['success' => true]);
?>
