<?php
// public/api/quiz/result.php - Returns attempt results by ID

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Response.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';
require_once __DIR__ . '/../../../app/Services/DISCScoringService.php';

$attemptId = Sanitize::int($_GET['id'] ?? null);

if (!$attemptId) {
    Response::error("Attempt ID is required.");
}

try {
    $result = DISCScoringService::getAttemptResult($attemptId);

    if (!$result) {
        Response::error("Attempt result not found.", 404);
    }

    Response::success($result);

} catch (Exception $e) {
    Response::error("Failed to retrieve result: " . $e->getMessage(), 500);
}
