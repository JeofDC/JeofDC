<?php
// app/Helpers/CSRF.php - CSRF Protection Utilities

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    // Also check JSON body if token is passed there
    if (!$token && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['csrf_token'] ?? null;
    }

    if (!verifyCsrfToken($token)) {
        if (isApiRequest()) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => 'CSRF token validation failed.']);
            exit();
        } else {
            die('CSRF Validation Failed.');
        }
    }
}
