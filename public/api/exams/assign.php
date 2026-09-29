<?php
// public/api/exams/assign.php - Single or Bulk Exam Assignment to Candidates
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';
require_once __DIR__ . '/../../../app/Helpers/CSRF.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';
require_once __DIR__ . '/../../../app/Helpers/Audit.php';

requirePermission('exam.assign');
requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$examId = !empty($input['exam_id']) ? intval($input['exam_id']) : null;
$candidateIds = $input['candidate_ids'] ?? [];

if (is_string($candidateIds)) {
    $candidateIds = json_decode($candidateIds, true) ?? [];
}
if (!empty($input['candidate_id'])) {
    $candidateIds[] = intval($input['candidate_id']);
}

$candidateIds = array_unique(array_filter(array_map('intval', $candidateIds)));

$availableFrom = !empty($input['available_from']) ? $input['available_from'] : date('Y-m-d H:i:s');
$availableUntil = !empty($input['available_until']) ? $input['available_until'] : date('Y-m-d H:i:s', strtotime('+30 days'));

if (!$examId || empty($candidateIds)) {
    echo json_encode(['success' => false, 'message' => 'Exam ID and at least one candidate are required.']);
    exit();
}

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$assignedBy = $currentUser ? $currentUser['id'] : null;

try {
    $pdo->beginTransaction();

    $stmtAssign = $pdo->prepare("
        INSERT INTO exam_assignments (exam_id, candidate_id, available_from, available_until, status, assigned_by)
        VALUES (?, ?, ?, ?, 'Assigned', ?)
    ");

    $count = 0;
    foreach ($candidateIds as $candId) {
        // Check if active assignment exists
        $stmtCheck = $pdo->prepare("
            SELECT id FROM exam_assignments
            WHERE exam_id = ? AND candidate_id = ? AND status IN ('Assigned', 'Started')
        ");
        $stmtCheck->execute([$examId, $candId]);
        if (!$stmtCheck->fetch()) {
            $stmtAssign->execute([$examId, $candId, $availableFrom, $availableUntil, $assignedBy]);
            $count++;
        }
    }

    $pdo->commit();

    logAudit('assign_exam', 'exams', $examId, "Assigned exam ID {$examId} to {$count} candidates.");

    echo json_encode(['success' => true, 'message' => "Successfully assigned exam to {$count} candidate(s)."]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error assigning exam: ' . $e->getMessage()]);
}
