<?php
// public/api/attempts/submit.php - Submit Exam Attempt & Calculate Score
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';
require_once __DIR__ . '/../../../app/Helpers/CSRF.php';
require_once __DIR__ . '/../../../app/Services/ScoringService.php';
require_once __DIR__ . '/../../../app/Helpers/Audit.php';

requireLogin();
requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$attemptId = !empty($input['attempt_id']) ? intval($input['attempt_id']) : null;
$currentUser = getCurrentUser();
$candidateId = $currentUser['id'];

if (!$attemptId) {
    echo json_encode(['success' => false, 'message' => 'Attempt ID is required.']);
    exit();
}

$pdo = getDbConnection();

// Verify attempt ownership
$stmtCheck = $pdo->prepare("SELECT status FROM exam_attempts WHERE id = ? AND candidate_id = ?");
$stmtCheck->execute([$attemptId, $candidateId]);
$attempt = $stmtCheck->fetch();

if (!$attempt) {
    echo json_encode(['success' => false, 'message' => 'Attempt not found or access denied.']);
    exit();
}

if ($attempt['status'] === 'Completed') {
    echo json_encode([
        'success' => true,
        'message' => 'Exam attempt already submitted.',
        'redirect' => "/result.php?attempt_id={$attemptId}"
    ]);
    exit();
}

try {
    $result = ScoringService::calculateAttemptScore($attemptId);

    logAudit('submit_exam_attempt', 'exam_attempts', $attemptId, "Submitted exam attempt ID: {$attemptId}. Score: {$result['score']} ({$result['percentage']}%)");

    echo json_encode([
        'success' => true,
        'message' => 'Exam submitted successfully.',
        'data' => $result,
        'redirect' => "/result.php?attempt_id={$attemptId}"
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error submitting examination: ' . $e->getMessage()]);
}
