<?php
// public/api/exams/save.php - Create / Update Exam & Category Configuration
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
    requirePermission('exams.edit');
} else {
    requirePermission('exams.create');
}

$examCode = strtoupper(sanitizeInput($input['exam_code'] ?? ''));
$title = sanitizeInput($input['title'] ?? '');
$description = sanitizeInput($input['description'] ?? '');
$instructions = sanitizeInput($input['instructions'] ?? '');
$durationMinutes = intval($input['duration_minutes'] ?? 30);
$passingPercentage = floatval($input['passing_percentage'] ?? 70.0);
$randomizeQuestions = !empty($input['randomize_questions']);
$randomizeChoices = !empty($input['randomize_choices']);
$allowBackNav = !empty($input['allow_back_navigation']);
$showQNumbers = !empty($input['show_question_numbers']);
$maxAttempts = intval($input['max_attempts'] ?? 1);
$status = sanitizeInput($input['status'] ?? 'Draft');

if (empty($examCode) || empty($title) || $durationMinutes <= 0) {
    echo json_encode(['success' => false, 'message' => 'Exam code, title and valid duration are required.']);
    exit();
}

$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE exams
            SET exam_code=?, title=?, description=?, instructions=?, duration_minutes=?, passing_percentage=?,
                randomize_questions=?, randomize_choices=?, allow_back_navigation=?, show_question_numbers=?,
                max_attempts=?, status=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([
            $examCode, $title, $description, $instructions, $durationMinutes, $passingPercentage,
            $randomizeQuestions ? 'true' : 'false', $randomizeChoices ? 'true' : 'false',
            $allowBackNav ? 'true' : 'false', $showQNumbers ? 'true' : 'false',
            $maxAttempts, $status, $id
        ]);
        $examId = $id;
        logAudit('update_exam', 'exams', $examId, "Updated exam: {$title}");
    } else {
        $currentUser = getCurrentUser();
        $creatorId = $currentUser ? $currentUser['id'] : null;

        $stmt = $pdo->prepare("
            INSERT INTO exams (exam_code, title, description, instructions, duration_minutes, passing_percentage,
                               randomize_questions, randomize_choices, allow_back_navigation, show_question_numbers,
                               max_attempts, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([
            $examCode, $title, $description, $instructions, $durationMinutes, $passingPercentage,
            $randomizeQuestions ? 'true' : 'false', $randomizeChoices ? 'true' : 'false',
            $allowBackNav ? 'true' : 'false', $showQNumbers ? 'true' : 'false',
            $maxAttempts, $status, $creatorId
        ]);
        $examId = $stmt->fetchColumn();
        logAudit('create_exam', 'exams', $examId, "Created exam: {$title}");
    }

    // Save Category Configuration
    $categories = $input['categories'] ?? [];
    if (is_string($categories)) {
        $categories = json_decode($categories, true) ?? [];
    }

    if (!empty($categories) && is_array($categories)) {
        $stmtDel = $pdo->prepare("DELETE FROM exam_categories WHERE exam_id = ?");
        $stmtDel->execute([$examId]);

        $stmtInsCat = $pdo->prepare("
            INSERT INTO exam_categories (exam_id, category_id, question_count, time_limit_minutes, points_per_question, display_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $order = 1;
        foreach ($categories as $c) {
            $catId = intval($c['category_id'] ?? 0);
            $qCount = intval($c['question_count'] ?? 10);
            $tLimit = !empty($c['time_limit_minutes']) ? intval($c['time_limit_minutes']) : null;
            $pts = floatval($c['points_per_question'] ?? 1.0);

            if ($catId > 0 && $qCount > 0) {
                $stmtInsCat->execute([$examId, $catId, $qCount, $tLimit, $pts, $order++]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Exam saved successfully.', 'id' => $examId]);

} catch (PDOException $e) {
    $pdo->rollBack();
    if (strpos($e->getMessage(), 'exams_exam_code_key') !== false) {
        echo json_encode(['success' => false, 'message' => 'Exam code must be unique.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
