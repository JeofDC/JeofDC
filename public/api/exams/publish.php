<?php
// public/api/exams/publish.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';
require_once __DIR__ . '/../../../app/Helpers/CSRF.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';
require_once __DIR__ . '/../../../app/Helpers/Audit.php';

requirePermission('exams.publish');
requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = !empty($input['id']) ? intval($input['id']) : null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Exam ID required.']);
    exit();
}

$pdo = getDbConnection();

// Verify exam has category configuration and enough questions
$stmtCheck = $pdo->prepare("
    SELECT ec.category_id, ec.question_count, c.name,
           (SELECT COUNT(*) FROM questions q WHERE q.category_id = ec.category_id AND q.status = 'active') as available_questions
    FROM exam_categories ec
    JOIN categories c ON c.id = ec.category_id
    WHERE ec.exam_id = ?
");
$stmtCheck->execute([$id]);
$configs = $stmtCheck->fetchAll();

if (empty($configs)) {
    echo json_encode(['success' => false, 'message' => 'Cannot publish exam without configured categories and question counts.']);
    exit();
}

foreach ($configs as $cfg) {
    if ($cfg['available_questions'] < $cfg['question_count']) {
        echo json_encode([
            'success' => false,
            'message' => "Category '{$cfg['name']}' requires {$cfg['question_count']} active questions, but only {$cfg['available_questions']} exist."
        ]);
        exit();
    }
}

$stmtPublish = $pdo->prepare("UPDATE exams SET status = 'Published', published_at = NOW(), updated_at = NOW() WHERE id = ?");
$stmtPublish->execute([$id]);

logAudit('publish_exam', 'exams', $id, "Published exam ID: {$id}");

echo json_encode(['success' => true, 'message' => 'Exam published successfully.']);
