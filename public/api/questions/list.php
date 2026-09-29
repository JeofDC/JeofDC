<?php
// public/api/questions/list.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';

requirePermission('questions.view');

$pdo = getDbConnection();

$id = !empty($_GET['id']) ? intval($_GET['id']) : null;

if ($id) {
    // Single Question detail with choices
    $stmt = $pdo->prepare("
        SELECT q.*, c.name as category_name, c.code as category_code
        FROM questions q
        JOIN categories c ON c.id = q.category_id
        WHERE q.id = ?
    ");
    $stmt->execute([$id]);
    $question = $stmt->fetch();

    if (!$question) {
        echo json_encode(['success' => false, 'message' => 'Question not found']);
        exit();
    }

    $stmtChoices = $pdo->prepare("
        SELECT * FROM question_choices WHERE question_id = ? ORDER BY display_order ASC, id ASC
    ");
    $stmtChoices->execute([$id]);
    $question['choices'] = $stmtChoices->fetchAll();

    echo json_encode(['success' => true, 'data' => $question]);
    exit();
}

$search = trim($_GET['search'] ?? '');
$categoryId = !empty($_GET['category_id']) ? intval($_GET['category_id']) : null;
$difficulty = trim($_GET['difficulty'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "
    SELECT q.id, q.category_id, q.question_type, q.question_text, q.question_image,
           q.difficulty, q.points, q.status, q.created_at,
           c.name as category_name, c.code as category_code,
           COUNT(qc.id) as total_choices
    FROM questions q
    JOIN categories c ON c.id = q.category_id
    LEFT JOIN question_choices qc ON qc.question_id = q.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (q.question_text ILIKE ? OR q.explanation ILIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($categoryId) {
    $sql .= " AND q.category_id = ?";
    $params[] = $categoryId;
}

if (!empty($difficulty)) {
    $sql .= " AND q.difficulty = ?";
    $params[] = $difficulty;
}

if (!empty($status)) {
    $sql .= " AND q.status = ?";
    $params[] = $status;
}

$sql .= " GROUP BY q.id, c.name, c.code ORDER BY q.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();

echo json_encode(['success' => true, 'data' => $questions]);
