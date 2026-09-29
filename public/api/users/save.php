<?php
// public/api/users/save.php - Create or Update User
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';
require_once __DIR__ . '/../../../app/Helpers/CSRF.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';
require_once __DIR__ . '/../../../app/Helpers/Audit.php';

requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = !empty($input['id']) ? intval($input['id']) : null;

if ($id) {
    requirePermission('users.edit');
} else {
    requirePermission('users.create');
}

$username = sanitizeInput($input['username'] ?? '');
$email = sanitizeInput($input['email'] ?? '');
$firstName = sanitizeInput($input['first_name'] ?? '');
$middleName = sanitizeInput($input['middle_name'] ?? '');
$lastName = sanitizeInput($input['last_name'] ?? '');
$empNum = sanitizeInput($input['employee_number'] ?? '');
$roleName = sanitizeInput($input['role'] ?? 'Candidate');
$status = sanitizeInput($input['status'] ?? 'active');
$password = $input['password'] ?? null;

if (empty($username) || empty($email) || empty($firstName) || empty($lastName)) {
    echo json_encode(['success' => false, 'message' => 'Username, Email, First Name and Last Name are required.']);
    exit();
}

$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    if ($id) {
        // Update User
        if (!empty($password)) {
            $stmt = $pdo->prepare("
                UPDATE users SET username=?, email=?, first_name=?, middle_name=?, last_name=?, employee_number=?, status=?, password_hash=?, updated_at=NOW()
                WHERE id=?
            ");
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt->execute([$username, $email, $firstName, $middleName, $lastName, $empNum, $status, $hash, $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users SET username=?, email=?, first_name=?, middle_name=?, last_name=?, employee_number=?, status=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([$username, $email, $firstName, $middleName, $lastName, $empNum, $status, $id]);
        }
        $userId = $id;
        logAudit('update_user', 'users', $userId, "Updated user: {$username}");
    } else {
        // Create User
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Password is required for new users.']);
            $pdo->rollBack();
            exit();
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, first_name, middle_name, last_name, employee_number, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([$username, $email, $hash, $firstName, $middleName, $lastName, $empNum, $status]);
        $userId = $stmt->fetchColumn();
        logAudit('create_user', 'users', $userId, "Created user: {$username}");
    }

    // Update Role Assignment
    $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
    $stmtRole->execute([$roleName]);
    $roleId = $stmtRole->fetchColumn();

    if ($roleId) {
        $stmtDel = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $stmtDel->execute([$userId]);

        $stmtIns = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        $stmtIns->execute([$userId, $roleId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'User saved successfully.', 'user_id' => $userId]);

} catch (PDOException $e) {
    $pdo->rollBack();
    if (strpos($e->getMessage(), 'users_username_key') !== false) {
        echo json_encode(['success' => false, 'message' => 'Username is already taken.']);
    } elseif (strpos($e->getMessage(), 'users_email_key') !== false) {
        echo json_encode(['success' => false, 'message' => 'Email is already registered.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
