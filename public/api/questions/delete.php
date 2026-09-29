<?php
// public/api/questions/delete.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';
require_once __DIR__ . '/../../../app/Helpers/CSRF.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';
require_once __DIR__ . '/../../../app/Helpers/Audit.php';

requirePermission('questions.delete');
requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = !empty($input['id']) ? intval($input['id']) : null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Question ID required.']);
    exit();
}

$pdo = getDbConnection();

try {
    $stmt = $pdo->prepare("UPDATE questions SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$id]);

    logAudit('deactivate_question', 'questions', $id, "Deactivated question ID: {$id}");

    echo json_encode(['success' => true, 'message' => 'Question deactivated successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error deactivating question: ' . $e->getMessage()]);
}
