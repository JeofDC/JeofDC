<?php
// public/api/auth/csrf-token.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../app/Helpers/CSRF.php';

echo json_encode([
    'success' => true,
    'csrf_token' => getCsrfToken()
]);
