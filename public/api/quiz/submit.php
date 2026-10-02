<?php
// public/api/quiz/submit.php - Process quiz submission

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Response.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';
require_once __DIR__ . '/../../../app/Services/DISCScoringService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error("Method not allowed.", 405);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    $input = $_POST;
}

$quizId = Sanitize::int($input['quiz_id'] ?? 1);
$userName = Sanitize::string($input['user_name'] ?? '');
$userEmail = Sanitize::email($input['user_email'] ?? '');
$answers = $input['answers'] ?? [];

if (empty($userName)) {
    Response::error("Name is required.");
}

if (empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
    Response::error("A valid email address is required.");
}

if (empty($answers) || !is_array($answers)) {
    Response::error("Please answer all assessment questions.");
}

try {
    $result = DISCScoringService::processSubmission($quizId, $userName, $userEmail, $answers);

    Response::success([
        'attempt_id' => (int)$result['attempt']['id'],
        'result' => $result
    ], "Assessment submitted successfully!");

} catch (Exception $e) {
    Response::error("Failed to process assessment: " . $e->getMessage(), 500);
}
