<?php
// public/api/attempts/log-event.php - Log Browser Focus/Tab Event
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';

requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$attemptId = !empty($input['attempt_id']) ? intval($input['attempt_id']) : null;
$eventType = sanitizeInput($input['event_type'] ?? '');
$eventData = sanitizeInput($input['event_data'] ?? '');

if (!$attemptId || empty($eventType)) {
    echo json_encode(['success' => false, 'message' => 'Attempt ID and Event Type required.']);
    exit();
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT INTO attempt_events (attempt_id, event_type, event_data) VALUES (?, ?, ?)");
    $stmt->execute([$attemptId, $eventType, $eventData]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error logging event']);
}
