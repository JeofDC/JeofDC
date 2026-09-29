<?php
// public/api/results/list.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';

requireLogin();

$currentUser = getCurrentUser();
$isCandidate = hasRole('Candidate') && !hasRole('Administrator') && !hasRole('Examiner');

$pdo = getDbConnection();

$attemptId = !empty($_GET['attempt_id']) ? intval($_GET['attempt_id']) : null;

if ($attemptId) {
    // Detailed single attempt result
    $sql = "
        SELECT ea.*, e.title as exam_title, e.exam_code, e.passing_percentage,
               u.first_name, u.last_name, u.email, u.employee_number
        FROM exam_attempts ea
        JOIN exams e ON e.id = ea.exam_id
        JOIN users u ON u.id = ea.candidate_id
        WHERE ea.id = ?
    ";
    if ($isCandidate) {
        $sql .= " AND ea.candidate_id = " . intval($currentUser['id']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        echo json_encode(['success' => false, 'message' => 'Result not found or access denied.']);
        exit();
    }

    // Category breakdown
    $stmtCatScores = $pdo->prepare("
        SELECT c.id as category_id, c.name as category_name, c.code as category_code,
               COUNT(aq.id) as total_questions,
               SUM(CASE WHEN ca.is_correct = TRUE THEN 1 ELSE 0 END) as correct_count,
               SUM(CASE WHEN ca.is_correct = TRUE THEN aq.points ELSE 0 END) as points_earned,
               SUM(aq.points) as total_points
        FROM attempt_questions aq
        JOIN categories c ON c.id = aq.category_id
        LEFT JOIN candidate_answers ca ON ca.attempt_question_id = aq.id
        WHERE aq.attempt_id = ?
        GROUP BY c.id, c.name, c.code
    ");
    $stmtCatScores->execute([$attemptId]);
    $catScores = $stmtCatScores->fetchAll();

    foreach ($catScores as &$cs) {
        $totPts = floatval($cs['total_points']);
        $earnedPts = floatval($cs['points_earned']);
        $cs['percentage'] = $totPts > 0 ? round(($earnedPts / $totPts) * 100, 2) : 0.0;
    }

    echo json_encode([
        'success' => true,
        'attempt' => $attempt,
        'category_scores' => $catScores
    ]);
    exit();
}

// List all results
$examId = !empty($_GET['exam_id']) ? intval($_GET['exam_id']) : null;
$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT ea.id, ea.started_at, ea.submitted_at, ea.status, ea.score, ea.percentage, ea.passed,
           ea.total_questions, ea.answered_questions, ea.correct_answers, ea.wrong_answers,
           e.title as exam_title, e.exam_code,
           u.first_name, u.last_name, u.username, u.employee_number
    FROM exam_attempts ea
    JOIN exams e ON e.id = ea.exam_id
    JOIN users u ON u.id = ea.candidate_id
    WHERE ea.status IN ('Completed', 'Timed Out')
";

$params = [];

if ($isCandidate) {
    $sql .= " AND ea.candidate_id = ?";
    $params[] = $currentUser['id'];
}

if ($examId) {
    $sql .= " AND ea.exam_id = ?";
    $params[] = $examId;
}

if (!empty($status)) {
    if ($status === 'passed') {
        $sql .= " AND ea.passed = TRUE";
    } elseif ($status === 'failed') {
        $sql .= " AND ea.passed = FALSE";
    }
}

if (!empty($search)) {
    $sql .= " AND (u.first_name ILIKE ? OR u.last_name ILIKE ? OR u.username ILIKE ? OR e.title ILIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

$sql .= " ORDER BY ea.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

echo json_encode(['success' => true, 'data' => $results]);
