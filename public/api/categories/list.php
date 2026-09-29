<?php
// public/api/categories/list.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';

requirePermission('categories.view');

$pdo = getDbConnection();

$stmt = $pdo->prepare("
    SELECT c.*, COUNT(q.id) as question_count
    FROM categories c
    LEFT JOIN questions q ON q.category_id = c.id AND q.status = 'active'
    GROUP BY c.id
    ORDER BY c.display_order ASC, c.id ASC
");
$stmt->execute();
$categories = $stmt->fetchAll();

echo json_encode(['success' => true, 'data' => $categories]);
