<?php
// app/Services/DISCScoringService.php

require_once __DIR__ . '/../../config/database.php';

class DISCScoringService {

    /**
     * Calculate DISC scores from user submitted answers
     *
     * @param int $quizId
     * @param string $userName
     * @param string $userEmail
     * @param array $answers Array of [question_id => selected_option_id]
     * @return array Attempt summary and scores
     */
    public static function processSubmission($quizId, $userName, $userEmail, $answers) {
        $db = getDbConnection();

        if (empty($answers) || !is_array($answers)) {
            throw new InvalidArgumentException("No answers provided.");
        }

        // Fetch valid option details for the quiz questions
        $optionIds = array_values($answers);
        if (empty($optionIds)) {
            throw new InvalidArgumentException("Invalid choices selected.");
        }

        $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
        $stmt = $db->prepare("
            SELECT id, question_id, dimension_code
            FROM question_options
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($optionIds);
        $options = $stmt->fetchAll();

        $optionMap = [];
        foreach ($options as $opt) {
            $optionMap[$opt['id']] = $opt;
        }

        $counts = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
        $totalQuestions = count($answers);
        $answerRecords = [];

        foreach ($answers as $qId => $optId) {
            if (!isset($optionMap[$optId])) {
                continue;
            }
            $dim = $optionMap[$optId]['dimension_code'];
            if (isset($counts[$dim])) {
                $counts[$dim]++;
            }
            $answerRecords[] = [
                'question_id' => (int)$qId,
                'option_id' => (int)$optId,
                'dimension' => $dim
            ];
        }

        $validTotal = array_sum($counts);
        if ($validTotal === 0) {
            throw new RuntimeException("Could not calculate scores from submission.");
        }

        // Calculate percentages
        $percentages = [
            'D' => round(($counts['D'] / $validTotal) * 100, 2),
            'I' => round(($counts['I'] / $validTotal) * 100, 2),
            'S' => round(($counts['S'] / $validTotal) * 100, 2),
            'C' => round(($counts['C'] / $validTotal) * 100, 2),
        ];

        // Sort dimensions by score descending
        arsort($counts);
        $sortedDims = array_keys($counts);
        $primaryDim = $sortedDims[0];
        $secondaryDim = $sortedDims[1];

        // Begin DB Transaction to store attempt
        $db->beginTransaction();

        try {
            $stmtAttempt = $db->prepare("
                INSERT INTO test_attempts (
                    quiz_id, user_name, user_email, primary_dimension, secondary_dimension,
                    score_d, score_i, score_s, score_c,
                    percentage_d, percentage_i, percentage_s, percentage_c
                ) VALUES (
                    :quiz_id, :user_name, :user_email, :primary, :secondary,
                    :sd, :si, :ss, :sc,
                    :pd, :pi, :ps, :pc
                )
            ");

            $stmtAttempt->execute([
                'quiz_id' => $quizId,
                'user_name' => $userName,
                'user_email' => $userEmail,
                'primary' => $primaryDim,
                'secondary' => $secondaryDim,
                'sd' => $counts['D'],
                'si' => $counts['I'],
                'ss' => $counts['S'],
                'sc' => $counts['C'],
                'pd' => $percentages['D'],
                'pi' => $percentages['I'],
                'ps' => $percentages['S'],
                'pc' => $percentages['C'],
            ]);

            $attemptId = $db->lastInsertId();

            $stmtAns = $db->prepare("
                INSERT INTO attempt_answers (attempt_id, question_id, selected_option_id, dimension_code)
                VALUES (:attempt_id, :question_id, :selected_option_id, :dimension_code)
            ");

            foreach ($answerRecords as $rec) {
                $stmtAns->execute([
                    'attempt_id' => $attemptId,
                    'question_id' => $rec['question_id'],
                    'selected_option_id' => $rec['option_id'],
                    'dimension_code' => $rec['dimension']
                ]);
            }

            $db->commit();

            return self::getAttemptResult($attemptId);

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Retrieve full attempt details including dimension report
     */
    public static function getAttemptResult($attemptId) {
        $db = getDbConnection();

        $stmt = $db->prepare("SELECT * FROM test_attempts WHERE id = ?");
        $stmt->execute([$attemptId]);
        $attempt = $stmt->fetch();

        if (!$attempt) {
            return null;
        }

        // Fetch dimensions info
        $stmtDim = $db->query("SELECT * FROM dimensions");
        $dimensionsList = $stmtDim->fetchAll();
        $dimensions = [];
        foreach ($dimensionsList as $d) {
            $dimensions[$d['code']] = $d;
        }

        $primaryInfo = $dimensions[$attempt['primary_dimension']] ?? null;
        $secondaryInfo = $dimensions[$attempt['secondary_dimension']] ?? null;

        return [
            'attempt' => $attempt,
            'scores' => [
                'D' => (int)$attempt['score_d'],
                'I' => (int)$attempt['score_i'],
                'S' => (int)$attempt['score_s'],
                'C' => (int)$attempt['score_c']
            ],
            'percentages' => [
                'D' => (float)$attempt['percentage_d'],
                'I' => (float)$attempt['percentage_i'],
                'S' => (float)$attempt['percentage_s'],
                'C' => (float)$attempt['percentage_c']
            ],
            'primary_dimension' => $primaryInfo,
            'secondary_dimension' => $secondaryInfo,
            'all_dimensions' => $dimensions
        ];
    }
}
