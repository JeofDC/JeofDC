<?php
// public/api/auth/logout.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';
require_once __DIR__ . '/../../../app/Helpers/Audit.php';

if (isLoggedIn()) {
    logAudit('logout', 'auth', $_SESSION['user_id'] ?? null, "User logged out");
}

session_unset();
session_destroy();

echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
