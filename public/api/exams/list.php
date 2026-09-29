<?php
// public/api/exams/list.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';

requirePermission('exams.view');

$pdo = getDbConnection();

$id = !empty($_GET['id']) ? intval($_GET['id']) : null;

if ($id) {
    // Single Exam detail with categories config
    $stmt = $pdo->prepare("SELECT e.*, u.username as creator_username FROM exams e LEFT JOIN users u ON u.id = e.created_by WHERE e.id = ?");
    $stmt->execute([$id]);
    $exam = $stmt->fetch();

    if (!$exam) {
        echo json_encode(['success' => false, 'message' => 'Exam not found']);
        exit();
    }

    $stmtCats = $pdo->prepare("
        SELECT ec.*, c.name as category_name, c.code as category_code
        FROM exam_categories ec
        JOIN categories c ON c.id = ec.category_id
        WHERE ec.exam_id = ?
        ORDER BY ec.display_order ASC
    ");
    $stmtCats->execute([$id]);
    $exam['categories'] = $stmtCats->fetchAll();

    echo json_encode(['success' => true, 'data' => $exam]);
    exit();
}

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT e.*, u.username as creator_username,
           COALESCE(SUM(ec.question_count), 0) as total_questions,
           COUNT(DISTINCT ea.id) as total_assignments
    FROM exams e
    LEFT JOIN users u ON u.id = e.created_by
    LEFT JOIN exam_categories ec ON ec.exam_id = e.id
    LEFT JOIN exam_assignments ea ON ea.exam_id = e.id
    WHERE 1=1
";

$params = [];

if (!empty($status)) {
    $sql .= " AND e.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $sql .= " AND (e.title ILIKE ? OR e.exam_code ILIKE ? OR e.description ILIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " GROUP BY e.id, u.username ORDER BY e.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$exams = $stmt->fetchAll();

echo json_encode(['success' => true, 'data' => $exams]);
