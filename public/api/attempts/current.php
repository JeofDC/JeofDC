<?php
// public/api/attempts/current.php - Get Active Exam Attempt details for Candidate
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';

requireLogin();

$attemptId = !empty($_GET['attempt_id']) ? intval($_GET['attempt_id']) : null;
$examId = !empty($_GET['exam_id']) ? intval($_GET['exam_id']) : null;
$currentUser = getCurrentUser();
$candidateId = $currentUser['id'];

$pdo = getDbConnection();

if ($attemptId) {
    $stmtAttempt = $pdo->prepare("
        SELECT ea.*, e.title as exam_title, e.instructions, e.allow_back_navigation, e.show_question_numbers
        FROM exam_attempts ea
        JOIN exams e ON e.id = ea.exam_id
        WHERE ea.id = ? AND ea.candidate_id = ?
    ");
    $stmtAttempt->execute([$attemptId, $candidateId]);
    $attempt = $stmtAttempt->fetch();
} else if ($examId) {
    $stmtAttempt = $pdo->prepare("
        SELECT ea.*, e.title as exam_title, e.instructions, e.allow_back_navigation, e.show_question_numbers
        FROM exam_attempts ea
        JOIN exams e ON e.id = ea.exam_id
        WHERE ea.exam_id = ? AND ea.candidate_id = ? AND ea.status = 'In Progress' AND ea.expires_at > NOW()
        ORDER BY ea.id DESC LIMIT 1
    ");
    $stmtAttempt->execute([$examId, $candidateId]);
    $attempt = $stmtAttempt->fetch();
} else {
    echo json_encode(['success' => false, 'message' => 'Attempt ID or Exam ID required.']);
    exit();
}

if (!$attempt) {
    echo json_encode(['success' => false, 'message' => 'Active attempt not found or access denied.']);
    exit();
}

$attemptId = $attempt['id'];

// Check expiration
$now = time();
$expiresAt = strtotime($attempt['expires_at']);

if ($attempt['status'] === 'In Progress' && $now >= $expiresAt) {
    require_once __DIR__ . '/../../../app/Services/ScoringService.php';
    ScoringService::calculateAttemptScore($attemptId);

    $stmtAttempt = $pdo->prepare("
        SELECT ea.*, e.title as exam_title, e.instructions, e.allow_back_navigation, e.show_question_numbers
        FROM exam_attempts ea
        JOIN exams e ON e.id = ea.exam_id
        WHERE ea.id = ? AND ea.candidate_id = ?
    ");
    $stmtAttempt->execute([$attemptId, $candidateId]);
    $attempt = $stmtAttempt->fetch();
}

if ($attempt['status'] !== 'In Progress') {
    echo json_encode([
        'success' => true,
        'status' => $attempt['status'],
        'message' => 'Attempt is no longer in progress.',
        'redirect' => "/result.php?attempt_id={$attemptId}"
    ]);
    exit();
}

// Fetch Attempt Questions & Candidate Answers
$stmtQuestions = $pdo->prepare("
    SELECT aq.id as attempt_question_id, aq.question_id, aq.question_order, aq.choices_order, aq.is_flagged, aq.points,
           q.question_text, q.question_image, q.question_type, q.difficulty,
           c.id as category_id, c.name as category_name, c.code as category_code,
           ca.selected_choice_id
    FROM attempt_questions aq
    JOIN questions q ON q.id = aq.question_id
    JOIN categories c ON c.id = aq.category_id
    LEFT JOIN candidate_answers ca ON ca.attempt_question_id = aq.id
    WHERE aq.attempt_id = ?
    ORDER BY aq.question_order ASC
");
$stmtQuestions->execute([$attemptId]);
$questions = $stmtQuestions->fetchAll();

// For each question, load choices according to persisted choices_order WITHOUT exposing is_correct or answer key!
$stmtChoices = $pdo->prepare("
    SELECT id, choice_label, choice_text, choice_image, display_order
    FROM question_choices
    WHERE id = ?
");

foreach ($questions as &$q) {
    $choicesOrder = json_decode($q['choices_order'], true) ?? [];
    $choices = [];

    if (!empty($choicesOrder)) {
        foreach ($choicesOrder as $cId) {
            $stmtChoices->execute([$cId]);
            $ch = $stmtChoices->fetch();
            if ($ch) {
                $choices[] = $ch;
            }
        }
    } else {
        $stmtAllChoices = $pdo->prepare("SELECT id, choice_label, choice_text, choice_image, display_order FROM question_choices WHERE question_id = ? ORDER BY display_order ASC");
        $stmtAllChoices->execute([$q['question_id']]);
        $choices = $stmtAllChoices->fetchAll();
    }

    $q['choices'] = $choices;
    unset($q['choices_order']);
}

$remainingSeconds = max(0, $expiresAt - $now);

echo json_encode([
    'success' => true,
    'attempt' => [
        'id' => $attempt['id'],
        'exam_id' => $attempt['exam_id'],
        'exam_title' => $attempt['exam_title'],
        'instructions' => $attempt['instructions'],
        'allow_back_navigation' => $attempt['allow_back_navigation'],
        'show_question_numbers' => $attempt['show_question_numbers'],
        'remaining_seconds' => $remainingSeconds,
        'expires_at' => $attempt['expires_at'],
        'status' => $attempt['status']
    ],
    'questions' => $questions
]);
