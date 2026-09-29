<?php
// public/api/categories/save.php
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
    requirePermission('categories.edit');
} else {
    requirePermission('categories.create');
}

$name = sanitizeInput($input['name'] ?? '');
$code = strtoupper(sanitizeInput($input['code'] ?? ''));
$description = sanitizeInput($input['description'] ?? '');
$status = sanitizeInput($input['status'] ?? 'active');
$displayOrder = intval($input['display_order'] ?? 0);

if (empty($name) || empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Category name and code are required.']);
    exit();
}

$pdo = getDbConnection();

try {
    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE categories
            SET name=?, code=?, description=?, status=?, display_order=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([$name, $code, $description, $status, $displayOrder, $id]);
        logAudit('update_category', 'categories', $id, "Updated category: {$name}");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO categories (name, code, description, status, display_order)
            VALUES (?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([$name, $code, $description, $status, $displayOrder]);
        $id = $stmt->fetchColumn();
        logAudit('create_category', 'categories', $id, "Created category: {$name}");
    }

    echo json_encode(['success' => true, 'message' => 'Category saved successfully.', 'id' => $id]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'categories_code_key') !== false) {
        echo json_encode(['success' => false, 'message' => 'Category code must be unique.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
