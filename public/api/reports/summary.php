<?php
// public/api/reports/summary.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';

requirePermission('reports.view');

$pdo = getDbConnection();

// Overall Stats
$stmtOverall = $pdo->query("
    SELECT
        COUNT(DISTINCT id) as total_attempts,
        COUNT(DISTINCT CASE WHEN status = 'Completed' THEN id END) as completed_attempts,
        COUNT(DISTINCT CASE WHEN passed = TRUE THEN id END) as passed_attempts,
        COUNT(DISTINCT CASE WHEN passed = FALSE AND status = 'Completed' THEN id END) as failed_attempts,
        COALESCE(AVG(percentage), 0) as average_score,
        COALESCE(MAX(percentage), 0) as highest_score,
        COALESCE(MIN(percentage), 0) as lowest_score
    FROM exam_attempts
    WHERE status IN ('Completed', 'Timed Out')
");
$overall = $stmtOverall->fetch();

// Exam Summary
$stmtExams = $pdo->query("
    SELECT e.id, e.title, e.exam_code,
           COUNT(ea.id) as total_candidates,
           COUNT(CASE WHEN ea.status = 'Completed' THEN 1 END) as completed,
           COUNT(CASE WHEN ea.passed = TRUE THEN 1 END) as passed,
           COUNT(CASE WHEN ea.passed = FALSE AND ea.status = 'Completed' THEN 1 END) as failed,
           COALESCE(ROUND(AVG(ea.percentage), 2), 0) as avg_percentage,
           COALESCE(MAX(ea.percentage), 0) as max_percentage,
           COALESCE(MIN(ea.percentage), 0) as min_percentage
    FROM exams e
    LEFT JOIN exam_attempts ea ON ea.exam_id = e.id AND ea.status IN ('Completed', 'Timed Out')
    GROUP BY e.id
    ORDER BY e.id DESC
");
$examSummary = $stmtExams->fetchAll();

// Category Performance Summary
$stmtCategories = $pdo->query("
    SELECT c.id, c.name, c.code,
           COUNT(DISTINCT aq.attempt_id) as total_attempts,
           COALESCE(ROUND(AVG(CASE WHEN ca.is_correct = TRUE THEN 100.0 ELSE 0.0 END), 2), 0) as average_percentage
    FROM categories c
    JOIN attempt_questions aq ON aq.category_id = c.id
    JOIN exam_attempts ea ON ea.id = aq.attempt_id AND ea.status IN ('Completed', 'Timed Out')
    LEFT JOIN candidate_answers ca ON ca.attempt_question_id = aq.id
    GROUP BY c.id
    ORDER BY c.display_order ASC
");
$categorySummary = $stmtCategories->fetchAll();

echo json_encode([
    'success' => true,
    'overall' => $overall,
    'exam_summary' => $examSummary,
    'category_summary' => $categorySummary
]);
