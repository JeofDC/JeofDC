<?php
// public/api/attempts/start.php - Start Exam Attempt and Persist Snapshot Order
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';
require_once __DIR__ . '/../../../app/Helpers/CSRF.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';
require_once __DIR__ . '/../../../app/Helpers/Audit.php';

requireLogin();
requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$examId = !empty($input['exam_id']) ? intval($input['exam_id']) : null;
$currentUser = getCurrentUser();
$candidateId = $currentUser['id'];

if (!$examId) {
    echo json_encode(['success' => false, 'message' => 'Exam ID is required.']);
    exit();
}

$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    // Verify candidate has an active assignment for this exam
    $stmtAssignment = $pdo->prepare("
        SELECT * FROM exam_assignments
        WHERE exam_id = ? AND candidate_id = ? AND status IN ('Assigned', 'Started')
          AND (available_from IS NULL OR available_from <= NOW())
          AND (available_until IS NULL OR available_until >= NOW())
        LIMIT 1
    ");
    $stmtAssignment->execute([$examId, $candidateId]);
    $assignment = $stmtAssignment->fetch();

    if (!$assignment) {
        // Check if user is Admin/Examiner previewing
        if (!hasRole('Administrator') && !hasRole('Examiner')) {
            echo json_encode(['success' => false, 'message' => 'No active assignment found for this exam or exam link expired.']);
            $pdo->rollBack();
            exit();
        }
    }

    // Check if an existing 'In Progress' attempt already exists
    $stmtAttempt = $pdo->prepare("
        SELECT * FROM exam_attempts
        WHERE exam_id = ? AND candidate_id = ? AND status = 'In Progress' AND expires_at > NOW()
        ORDER BY id DESC LIMIT 1
    ");
    $stmtAttempt->execute([$examId, $candidateId]);
    $existingAttempt = $stmtAttempt->fetch();

    if ($existingAttempt) {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Resuming existing examination attempt.',
            'attempt_id' => $existingAttempt['id']
        ]);
        exit();
    }

    // Fetch Exam config
    $stmtExam = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND status = 'Published'");
    $stmtExam->execute([$examId]);
    $exam = $stmtExam->fetch();

    if (!$exam) {
        echo json_encode(['success' => false, 'message' => 'Exam is not published or available.']);
        $pdo->rollBack();
        exit();
    }

    // Check max attempts limit
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM exam_attempts WHERE exam_id = ? AND candidate_id = ?");
    $stmtCount->execute([$examId, $candidateId]);
    $attemptsCount = intval($stmtCount->fetchColumn());

    if ($attemptsCount >= $exam['max_attempts'] && !hasRole('Administrator')) {
        echo json_encode(['success' => false, 'message' => 'Maximum allowed exam attempts reached.']);
        $pdo->rollBack();
        exit();
    }

    // Calculate expiry time based on duration
    $durationMinutes = intval($exam['duration_minutes']);
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$durationMinutes} minutes"));

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Create Attempt record
    $stmtInsAttempt = $pdo->prepare("
        INSERT INTO exam_attempts (exam_id, candidate_id, assignment_id, attempt_number, started_at, expires_at, status, ip_address, user_agent)
        VALUES (?, ?, ?, ?, NOW(), ?, 'In Progress', ?, ?) RETURNING id
    ");
    $stmtInsAttempt->execute([
        $examId,
        $candidateId,
        $assignment ? $assignment['id'] : null,
        $attemptsCount + 1,
        $expiresAt,
        $ip,
        $userAgent
    ]);
    $attemptId = $stmtInsAttempt->fetchColumn();

    // Fetch exam categories config
    $stmtExamCats = $pdo->prepare("
        SELECT ec.*, c.name as category_name
        FROM exam_categories ec
        JOIN categories c ON c.id = ec.category_id
        WHERE ec.exam_id = ?
        ORDER BY ec.display_order ASC
    ");
    $stmtExamCats->execute([$examId]);
    $examCategories = $stmtExamCats->fetchAll();

    $globalOrder = 1;

    $stmtAQ = $pdo->prepare("
        INSERT INTO attempt_questions (attempt_id, question_id, category_id, question_order, choices_order, points)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmtChoices = $pdo->prepare("SELECT id FROM question_choices WHERE question_id = ? ORDER BY display_order ASC");

    foreach ($examCategories as $ec) {
        $catId = $ec['category_id'];
        $qCount = $ec['question_count'];
        $pts = floatval($ec['points_per_question']);

        // Fetch questions for this category
        $stmtQuestions = $pdo->prepare("
            SELECT id FROM questions
            WHERE category_id = ? AND status = 'active'
            ORDER BY " . ($exam['randomize_questions'] ? "RANDOM()" : "id ASC") . "
            LIMIT ?
        ");
        $stmtQuestions->execute([$catId, $qCount]);
        $questions = $stmtQuestions->fetchAll(PDO::FETCH_COLUMN);

        foreach ($questions as $qId) {
            // Fetch choices
            $stmtChoices->execute([$qId]);
            $choiceIds = $stmtChoices->fetchAll(PDO::FETCH_COLUMN);

            if ($exam['randomize_choices']) {
                shuffle($choiceIds);
            }

            $stmtAQ->execute([
                $attemptId,
                $qId,
                $catId,
                $globalOrder++,
                json_encode($choiceIds),
                $pts
            ]);
        }
    }

    // Update assignment status to Started if present
    if ($assignment) {
        $stmtUpdateAssign = $pdo->prepare("UPDATE exam_assignments SET status = 'Started' WHERE id = ?");
        $stmtUpdateAssign->execute([$assignment['id']]);
    }

    $pdo->commit();

    logAudit('start_exam_attempt', 'exam_attempts', $attemptId, "Started exam attempt ID: {$attemptId} for exam ID: {$examId}");

    echo json_encode([
        'success' => true,
        'message' => 'Examination attempt started successfully.',
        'attempt_id' => $attemptId
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error starting examination: ' . $e->getMessage()]);
}
