<?php
// config/app.php - Application Configuration Settings

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

define('APP_NAME', 'Aptitude Examination System');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8000');
define('UPLOAD_DIR', __DIR__ . '/../public/assets/uploads/');
define('UPLOAD_URL', '/assets/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB

// Session Timeout - 2 hours
define('SESSION_LIFETIME', 7200);

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();
