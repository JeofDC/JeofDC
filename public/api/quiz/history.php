<?php
// public/api/quiz/history.php - Search test attempts by email or name

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Response.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';

$query = Sanitize::string($_GET['query'] ?? '');

try {
    $db = getDbConnection();

    if (!empty($query)) {
        $stmt = $db->prepare("
            SELECT a.id, a.user_name, a.user_email, a.primary_dimension, a.secondary_dimension,
                   a.percentage_d, a.percentage_i, a.percentage_s, a.percentage_c, a.created_at,
                   d.name as primary_name, d.title as primary_title
            FROM test_attempts a
            LEFT JOIN dimensions d ON a.primary_dimension = d.code
            WHERE a.user_email LIKE ? OR a.user_name LIKE ?
            ORDER BY a.created_at DESC
            LIMIT 50
        ");
        $term = '%' . $query . '%';
        $stmt->execute([$term, $term]);
    } else {
        $stmt = $db->query("
            SELECT a.id, a.user_name, a.user_email, a.primary_dimension, a.secondary_dimension,
                   a.percentage_d, a.percentage_i, a.percentage_s, a.percentage_c, a.created_at,
                   d.name as primary_name, d.title as primary_title
            FROM test_attempts a
            LEFT JOIN dimensions d ON a.primary_dimension = d.code
            ORDER BY a.created_at DESC
            LIMIT 50
        ");
    }

    $attempts = $stmt->fetchAll();

    Response::success($attempts);

} catch (Exception $e) {
    Response::error("Failed to load search results: " . $e->getMessage(), 500);
}
