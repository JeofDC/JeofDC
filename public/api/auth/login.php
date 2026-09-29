<?php
// public/api/auth/login.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';
require_once __DIR__ . '/../../../app/Helpers/CSRF.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';
require_once __DIR__ . '/../../../app/Helpers/Audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$username = sanitizeInput($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit();
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Regenerate Session ID for security
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];

        loadUserPermissions($user['id']);

        // Update last login
        $stmtUpdate = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$user['id']]);

        logAudit('login', 'auth', $user['id'], "User logged in: {$user['username']}");

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => getCurrentUser(),
                'csrf_token' => getCsrfToken()
            ]
        ]);
    } else {
        logAudit('login_failed', 'auth', null, "Failed login attempt for username: {$username}");
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred during login']);
}
