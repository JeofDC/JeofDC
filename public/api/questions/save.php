<?php
// public/api/questions/save.php - Create/Update Question with Choices and Image Upload
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Auth.php';
require_once __DIR__ . '/../../../app/Helpers/CSRF.php';
require_once __DIR__ . '/../../../app/Helpers/Sanitize.php';
require_once __DIR__ . '/../../../app/Helpers/Audit.php';

requireCsrf();

$isJson = (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
if ($isJson) {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

$id = !empty($input['id']) ? intval($input['id']) : null;

if ($id) {
    requirePermission('questions.edit');
} else {
    requirePermission('questions.create');
}

$categoryId = intval($input['category_id'] ?? 0);
$qType = sanitizeInput($input['question_type'] ?? 'multiple_choice');
$qText = sanitizeInput($input['question_text'] ?? '');
$explanation = sanitizeInput($input['explanation'] ?? '');
$difficulty = sanitizeInput($input['difficulty'] ?? 'Medium');
$points = floatval($input['points'] ?? 1.0);
$status = sanitizeInput($input['status'] ?? 'active');

if (empty($categoryId) || empty($qText)) {
    echo json_encode(['success' => false, 'message' => 'Category and Question Text are required.']);
    exit();
}

$qImage = $input['existing_image'] ?? null;

// Handle File Upload if present
if (!empty($_FILES['question_image'])) {
    try {
        $uploadedPath = validateUploadedImage($_FILES['question_image']);
        if ($uploadedPath) {
            $qImage = $uploadedPath;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE questions
            SET category_id=?, question_type=?, question_text=?, question_image=?, explanation=?, difficulty=?, points=?, status=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->execute([$categoryId, $qType, $qText, $qImage, $explanation, $difficulty, $points, $status, $id]);
        $qId = $id;
        logAudit('update_question', 'questions', $qId, "Updated question ID: {$qId}");
    } else {
        $currentUser = getCurrentUser();
        $creatorId = $currentUser ? $currentUser['id'] : null;
        $stmt = $pdo->prepare("
            INSERT INTO questions (category_id, question_type, question_text, question_image, explanation, difficulty, points, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([$categoryId, $qType, $qText, $qImage, $explanation, $difficulty, $points, $status, $creatorId]);
        $qId = $stmt->fetchColumn();
        logAudit('create_question', 'questions', $qId, "Created new question ID: {$qId}");
    }

    // Process Choices
    $choices = $input['choices'] ?? [];
    if (is_string($choices)) {
        $choices = json_decode($choices, true) ?? [];
    }

    if (!empty($choices) && is_array($choices)) {
        // Clear previous choices
        $stmtDel = $pdo->prepare("DELETE FROM question_choices WHERE question_id = ?");
        $stmtDel->execute([$qId]);

        $stmtInsChoice = $pdo->prepare("
            INSERT INTO question_choices (question_id, choice_label, choice_text, is_correct, display_order)
            VALUES (?, ?, ?, ?, ?)
        ");

        $order = 1;
        $hasCorrect = false;

        foreach ($choices as $ch) {
            $label = sanitizeInput($ch['choice_label'] ?? chr(64 + $order));
            $text = sanitizeInput($ch['choice_text'] ?? '');
            $isCorrect = !empty($ch['is_correct']) && ($ch['is_correct'] === true || $ch['is_correct'] === 'true' || $ch['is_correct'] === 1 || $ch['is_correct'] === '1');

            if ($isCorrect) {
                $hasCorrect = true;
            }

            $stmtInsChoice->execute([
                $qId,
                $label,
                $text,
                $isCorrect ? 'true' : 'false',
                $order++
            ]);
        }

        if (!$hasCorrect) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'At least one choice must be marked as correct.']);
            exit();
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Question saved successfully.', 'question_id' => $qId]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error saving question: ' . $e->getMessage()]);
}
