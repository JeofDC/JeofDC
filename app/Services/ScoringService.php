<?php
// app/Services/ScoringService.php - Server-side Examination Attempt Scoring

require_once __DIR__ . '/../../config/database.php';

class ScoringService {

    public static function calculateAttemptScore($attemptId) {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        try {
            // Fetch Attempt details
            $stmtAttempt = $pdo->prepare("SELECT * FROM exam_attempts WHERE id = ? FOR UPDATE");
            $stmtAttempt->execute([$attemptId]);
            $attempt = $stmtAttempt->fetch();

            if (!$attempt) {
                throw new Exception("Attempt not found.");
            }

            // Fetch attempt questions with assigned choices
            $stmtAQ = $pdo->prepare("
                SELECT aq.id as attempt_question_id, aq.question_id, aq.points, aq.category_id,
                       ca.selected_choice_id
                FROM attempt_questions aq
                LEFT JOIN candidate_answers ca ON ca.attempt_question_id = aq.id
                WHERE aq.attempt_id = ?
            ");
            $stmtAQ->execute([$attemptId]);
            $attemptQuestions = $stmtAQ->fetchAll();

            $totalQuestions = count($attemptQuestions);
            $answeredQuestions = 0;
            $correctAnswers = 0;
            $wrongAnswers = 0;
            $totalPossiblePoints = 0.00;
            $pointsEarned = 0.00;

            $stmtGetCorrectChoice = $pdo->prepare("SELECT id FROM question_choices WHERE question_id = ? AND is_correct = TRUE LIMIT 1");
            $stmtUpdateAnswer = $pdo->prepare("
                UPDATE candidate_answers
                SET is_correct = ?, points_earned = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmtInsertAnswer = $pdo->prepare("
                INSERT INTO candidate_answers (attempt_id, attempt_question_id, question_id, selected_choice_id, is_correct, points_earned)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($attemptQuestions as $aq) {
                $qPoints = floatval($aq['points']);
                $totalPossiblePoints += $qPoints;

                $selectedChoiceId = $aq['selected_choice_id'];

                // Determine correct choice
                $stmtGetCorrectChoice->execute([$aq['question_id']]);
                $correctChoice = $stmtGetCorrectChoice->fetch();
                $correctChoiceId = $correctChoice ? $correctChoice['id'] : null;

                $isCorrect = false;
                $pEarned = 0.00;

                if ($selectedChoiceId !== null) {
                    $answeredQuestions++;
                    if ($correctChoiceId && (int)$selectedChoiceId === (int)$correctChoiceId) {
                        $isCorrect = true;
                        $pEarned = $qPoints;
                        $correctAnswers++;
                        $pointsEarned += $pEarned;
                    } else {
                        $wrongAnswers++;
                    }

                    // Update existing answer
                    $stmtCheckAns = $pdo->prepare("SELECT id FROM candidate_answers WHERE attempt_id = ? AND attempt_question_id = ?");
                    $stmtCheckAns->execute([$attemptId, $aq['attempt_question_id']]);
                    $existingAnsId = $stmtCheckAns->fetchColumn();

                    if ($existingAnsId) {
                        $stmtUpdateAnswer->execute([$isCorrect ? 'true' : 'false', $pEarned, $existingAnsId]);
                    } else {
                        $stmtInsertAnswer->execute([$attemptId, $aq['attempt_question_id'], $aq['question_id'], $selectedChoiceId, $isCorrect ? 'true' : 'false', $pEarned]);
                    }
                } else {
                    $wrongAnswers++; // Unanswered treated as incorrect
                }
            }

            // Calculate percentage and pass status
            $percentage = $totalPossiblePoints > 0 ? round(($pointsEarned / $totalPossiblePoints) * 100, 2) : 0.00;

            // Fetch Exam passing percentage
            $stmtExam = $pdo->prepare("SELECT passing_percentage FROM exams WHERE id = ?");
            $stmtExam->execute([$attempt['exam_id']]);
            $passingPercentage = floatval($stmtExam->fetchColumn());

            $passed = ($percentage >= $passingPercentage);

            // Update Attempt record
            $stmtFinalUpdate = $pdo->prepare("
                UPDATE exam_attempts
                SET status = 'Completed',
                    submitted_at = NOW(),
                    total_questions = ?,
                    answered_questions = ?,
                    correct_answers = ?,
                    wrong_answers = ?,
                    score = ?,
                    percentage = ?,
                    passed = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtFinalUpdate->execute([
                $totalQuestions,
                $answeredQuestions,
                $correctAnswers,
                $wrongAnswers,
                $pointsEarned,
                $percentage,
                $passed ? 'true' : 'false',
                $attemptId
            ]);

            // Update Exam Assignment status if present
            if ($attempt['assignment_id']) {
                $stmtAssign = $pdo->prepare("UPDATE exam_assignments SET status = 'Completed' WHERE id = ?");
                $stmtAssign->execute([$attempt['assignment_id']]);
            }

            $pdo->commit();

            return [
                'attempt_id' => $attemptId,
                'total_questions' => $totalQuestions,
                'answered_questions' => $answeredQuestions,
                'correct_answers' => $correctAnswers,
                'wrong_answers' => $wrongAnswers,
                'score' => $pointsEarned,
                'percentage' => $percentage,
                'passed' => $passed
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
