<?php
// public/api/quiz/get.php - Returns quiz info, questions, and options

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../app/Helpers/Response.php';

try {
    $db = getDbConnection();

    // Fetch primary quiz
    $stmtQuiz = $db->query("SELECT * FROM quizzes LIMIT 1");
    $quiz = $stmtQuiz->fetch();

    if (!$quiz) {
        Response::error("Quiz not found.", 404);
    }

    // Fetch questions
    $stmtQ = $db->prepare("
        SELECT id, question_number, question_text
        FROM questions
        WHERE quiz_id = ?
        ORDER BY question_number ASC
    ");
    $stmtQ->execute([$quiz['id']]);
    $questions = $stmtQ->fetchAll();

    // Fetch options for questions
    $stmtOpt = $db->prepare("
        SELECT id, question_id, option_text, display_order
        FROM question_options
        ORDER BY question_id ASC, display_order ASC
    ");
    $stmtOpt->execute();
    $allOptions = $stmtOpt->fetchAll();

    $optionsByQ = [];
    foreach ($allOptions as $opt) {
        $optionsByQ[$opt['question_id']][] = [
            'id' => (int)$opt['id'],
            'option_text' => $opt['option_text'],
            'display_order' => (int)$opt['display_order']
        ];
    }

    $formattedQuestions = [];
    foreach ($questions as $q) {
        $qId = (int)$q['id'];
        $formattedQuestions[] = [
            'id' => $qId,
            'question_number' => (int)$q['question_number'],
            'question_text' => $q['question_text'],
            'options' => $optionsByQ[$qId] ?? []
        ];
    }

    Response::success([
        'quiz' => [
            'id' => (int)$quiz['id'],
            'title' => $quiz['title'],
            'description' => $quiz['description']
        ],
        'total_questions' => count($formattedQuestions),
        'questions' => $formattedQuestions
    ]);

} catch (Exception $e) {
    Response::error("Failed to load quiz: " . $e->getMessage(), 500);
}
