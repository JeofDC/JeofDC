<?php
// public/api/attempts/save-answer.php - AJAX Auto-Save Candidate Answer & Flag Status
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';
require_once __DIR__ . '/../../../app/Helpers/CSRF.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';

requireLogin();
requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$attemptId = !empty($input['attempt_id']) ? intval($input['attempt_id']) : null;
$attemptQuestionId = !empty($input['attempt_question_id']) ? intval($input['attempt_question_id']) : null;
$selectedChoiceId = !empty($input['selected_choice_id']) ? intval($input['selected_choice_id']) : null;
$isFlagged = isset($input['is_flagged']) ? (!empty($input['is_flagged']) && $input['is_flagged'] !== 'false') : null;

$currentUser = getCurrentUser();
$candidateId = $currentUser['id'];

if (!$attemptId || !$attemptQuestionId) {
    echo json_encode(['success' => false, 'message' => 'Attempt ID and Question ID are required.']);
    exit();
}

$pdo = getDbConnection();

// Verify attempt ownership and active status
$stmtAttempt = $pdo->prepare("SELECT status, expires_at FROM exam_attempts WHERE id = ? AND candidate_id = ?");
$stmtAttempt->execute([$attemptId, $candidateId]);
$attempt = $stmtAttempt->fetch();

if (!$attempt || $attempt['status'] !== 'In Progress' || strtotime($attempt['expires_at']) <= time()) {
    echo json_encode(['success' => false, 'message' => 'Attempt is no longer active or time expired.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Verify attempt question belongs to this attempt
    $stmtAQ = $pdo->prepare("SELECT id, question_id FROM attempt_questions WHERE id = ? AND attempt_id = ?");
    $stmtAQ->execute([$attemptQuestionId, $attemptId]);
    $aq = $stmtAQ->fetch();

    if (!$aq) {
        echo json_encode(['success' => false, 'message' => 'Invalid attempt question.']);
        $pdo->rollBack();
        exit();
    }

    // Update flagged status if provided
    if ($isFlagged !== null) {
        $stmtFlag = $pdo->prepare("UPDATE attempt_questions SET is_flagged = ? WHERE id = ?");
        $stmtFlag->execute([$isFlagged ? 'true' : 'false', $attemptQuestionId]);
    }

    // Save answer choice if provided
    if ($selectedChoiceId !== null) {
        // Check if candidate answer record exists
        $stmtCheck = $pdo->prepare("SELECT id FROM candidate_answers WHERE attempt_id = ? AND attempt_question_id = ?");
        $stmtCheck->execute([$attemptId, $attemptQuestionId]);
        $ansId = $stmtCheck->fetchColumn();

        if ($ansId) {
            $stmtUpdate = $pdo->prepare("
                UPDATE candidate_answers
                SET selected_choice_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([$selectedChoiceId, $ansId]);
        } else {
            $stmtInsert = $pdo->prepare("
                INSERT INTO candidate_answers (attempt_id, attempt_question_id, question_id, selected_choice_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmtInsert->execute([$attemptId, $attemptQuestionId, $aq['question_id'], $selectedChoiceId]);
        }
    }

    // Update answered count on attempt
    $stmtCount = $pdo->prepare("
        SELECT COUNT(DISTINCT attempt_question_id)
        FROM candidate_answers
        WHERE attempt_id = ? AND selected_choice_id IS NOT NULL
    ");
    $stmtCount->execute([$attemptId]);
    $answeredCount = $stmtCount->fetchColumn();

    $stmtUpdAtt = $pdo->prepare("UPDATE exam_attempts SET answered_questions = ?, updated_at = NOW() WHERE id = ?");
    $stmtUpdAtt->execute([$answeredCount, $attemptId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Answer saved successfully.',
        'answered_questions' => $answeredCount
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error saving answer: ' . $e->getMessage()]);
}
