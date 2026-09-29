<?php
// app/Helpers/Auth.php - Authentication and Permission Helper Functions

require_once __DIR__ . '/../../config/database.php';

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'roles' => $_SESSION['roles'] ?? [],
        'permissions' => $_SESSION['permissions'] ?? []
    ];
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        if (isApiRequest()) {
            header('Content-Type: application/json', true, 401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
            exit();
        } else {
            header('Location: /login.php');
            exit();
        }
    }
}

function hasRole($roleName) {
    $roles = $_SESSION['roles'] ?? [];
    return in_array($roleName, $roles, true);
}

function hasPermission($permissionName) {
    if (hasRole('Administrator')) {
        return true;
    }
    $permissions = $_SESSION['permissions'] ?? [];
    return in_array($permissionName, $permissions, true);
}

function requirePermission($permissionName) {
    requireLogin();
    if (!hasPermission($permissionName)) {
        if (isApiRequest()) {
            header('Content-Type: application/json', true, 403);
            echo json_encode(['success' => false, 'message' => 'Forbidden. Insufficient permissions.']);
            exit();
        } else {
            header('Location: /dashboard.php?error=forbidden');
            exit();
        }
    }
}

function loadUserPermissions($userId) {
    $pdo = getDbConnection();

    // Fetch Roles
    $stmtRoles = $pdo->prepare("
        SELECT r.name
        FROM roles r
        JOIN user_roles ur ON ur.role_id = r.id
        WHERE ur.user_id = ?
    ");
    $stmtRoles->execute([$userId]);
    $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

    // Fetch Permissions
    $stmtPerms = $pdo->prepare("
        SELECT DISTINCT p.name
        FROM permissions p
        JOIN role_permissions rp ON rp.permission_id = p.id
        JOIN user_roles ur ON ur.role_id = rp.role_id
        WHERE ur.user_id = ?
    ");
    $stmtPerms->execute([$userId]);
    $permissions = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);

    $_SESSION['roles'] = $roles;
    $_SESSION['permissions'] = $permissions;
}

function isApiRequest() {
    return (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false)
    );
}
