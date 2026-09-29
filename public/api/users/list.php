<?php
// public/api/users/list.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';

requirePermission('users.view');

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$pdo = getDbConnection();

$sql = "
    SELECT u.id, u.username, u.email, u.first_name, u.middle_name, u.last_name,
           u.employee_number, u.status, u.created_at, u.last_login_at,
           ARRAY_AGG(r.name) as roles
    FROM users u
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.username ILIKE ? OR u.email ILIKE ? OR u.first_name ILIKE ? OR u.last_name ILIKE ? OR u.employee_number ILIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if (!empty($statusFilter)) {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
}

$sql .= " GROUP BY u.id ORDER BY u.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Clean up roles array output
foreach ($users as &$u) {
    if ($u['roles']) {
        // Postgres array conversion if needed
        if (is_string($u['roles'])) {
            $u['roles'] = explode(',', str_replace(['{', '}', '"'], '', $u['roles']));
        }
    } else {
        $u['roles'] = [];
    }
}

if (!empty($roleFilter)) {
    $users = array_values(array_filter($users, function($user) use ($roleFilter) {
        return in_array($roleFilter, $user['roles']);
    }));
}

echo json_encode(['success' => true, 'data' => $users]);
