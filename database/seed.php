<?php
// database/seed.php - Seeds database schema and DISC questions

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDbConnection();

    // Create tables if using SQLite
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $db->exec("
            CREATE TABLE IF NOT EXISTS dimensions (
                code VARCHAR(10) PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                title VARCHAR(200) NOT NULL,
                tagline VARCHAR(255),
                description TEXT NOT NULL,
                strengths TEXT NOT NULL,
                growth_areas TEXT NOT NULL,
                communication_tips TEXT NOT NULL,
                work_style TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS quizzes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code VARCHAR(50) UNIQUE NOT NULL,
                title VARCHAR(200) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                quiz_id INT NOT NULL,
                question_number INT NOT NULL,
                question_text TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS question_options (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                question_id INT NOT NULL,
                dimension_code VARCHAR(10) NOT NULL,
                option_text TEXT NOT NULL,
                display_order INT DEFAULT 0,
                FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
                FOREIGN KEY (dimension_code) REFERENCES dimensions(code) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS test_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                quiz_id INT NOT NULL,
                user_name VARCHAR(100) NOT NULL,
                user_email VARCHAR(255) NOT NULL,
                primary_dimension VARCHAR(10),
                secondary_dimension VARCHAR(10),
                score_d INT DEFAULT 0,
                score_i INT DEFAULT 0,
                score_s INT DEFAULT 0,
                score_c INT DEFAULT 0,
                percentage_d NUMERIC(5,2) DEFAULT 0.00,
                percentage_i NUMERIC(5,2) DEFAULT 0.00,
                percentage_s NUMERIC(5,2) DEFAULT 0.00,
                percentage_c NUMERIC(5,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
            );

            CREATE TABLE IF NOT EXISTS attempt_answers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                attempt_id INT NOT NULL,
                question_id INT NOT NULL,
                selected_option_id INT NOT NULL,
                dimension_code VARCHAR(10) NOT NULL,
                FOREIGN KEY (attempt_id) REFERENCES test_attempts(id) ON DELETE CASCADE,
                FOREIGN KEY (question_id) REFERENCES questions(id),
                FOREIGN KEY (selected_option_id) REFERENCES question_options(id)
            );
        ");
    }

    // Clear existing data for fresh seed
    $db->exec("DELETE FROM attempt_answers;");
    $db->exec("DELETE FROM test_attempts;");
    $db->exec("DELETE FROM question_options;");
    $db->exec("DELETE FROM questions;");
    $db->exec("DELETE FROM quizzes;");
    $db->exec("DELETE FROM dimensions;");

    // Seed Dimensions
    $stmtDim = $db->prepare("INSERT INTO dimensions (code, name, title, tagline, description, strengths, growth_areas, communication_tips, work_style) VALUES (:code, :name, :title, :tagline, :description, :strengths, :growth_areas, :communication_tips, :work_style)");

    $dimensions = [
        [
            'code' => 'D',
            'name' => 'Dominance',
            'title' => 'The Driver / Leader',
            'tagline' => 'Results-oriented, decisive, competitive, and direct.',
            'description' => 'Individuals with high Dominance are direct, strong-willed, and driven by challenges and results. They take initiative, accept risks, and like being in control of their environment.',
            'strengths' => 'Rapid decision making, visionary leadership, goal achievement, problem solving under pressure, overcoming obstacles.',
            'growth_areas' => 'Patience with team members, active listening, delegation without micro-management, considering emotional impacts of decisions.',
            'communication_tips' => 'Be direct, brief, and focus on outcomes and bottom-line results. Avoid unnecessary small talk.',
            'work_style' => 'Thrives in dynamic, fast-paced environments with autonomy and clear authority.'
        ],
        [
            'code' => 'I',
            'name' => 'Influence',
            'title' => 'The Communicator / Influencer',
            'tagline' => 'Enthusiastic, persuasive, social, and optimistic.',
            'description' => 'Individuals with high Influence are outgoing, expressive, and relationship-driven. They excel at motivating others, building morale, and pitching big ideas with infectious enthusiasm.',
            'strengths' => 'Inspiring others, creative brainstorming, networking, team building, positivity under stress.',
            'growth_areas' => 'Following through on fine details, managing time strictly, focusing on execution over ideation.',
            'communication_tips' => 'Be friendly, open, express optimism, and allow space for creative discussion and social rapport.',
            'work_style' => 'Thrives in collaborative, interactive, and socially engaging environments.'
        ],
        [
            'code' => 'S',
            'name' => 'Steadiness',
            'title' => 'The Supporter / Anchor',
            'tagline' => 'Patient, dependable, empathetic, and cooperative.',
            'description' => 'Individuals with high Steadiness are calm, loyal, and supportive team players. They value harmony, consistency, and predictable processes, often serving as the reliable glue in teams.',
            'strengths' => 'Active listening, steady teamwork, conflict resolution, loyalty, methodical execution.',
            'growth_areas' => 'Adapting rapidly to sudden changes, speaking up with opposing viewpoints, asserting individual priorities.',
            'communication_tips' => 'Be warm, patient, present changes step-by-step, and offer reassurance.',
            'work_style' => 'Thrives in stable, harmonious team settings with predictable structures.'
        ],
        [
            'code' => 'C',
            'name' => 'Conscientiousness',
            'title' => 'The Analyst / Strategist',
            'tagline' => 'Analytical, precise, systematic, and detail-oriented.',
            'description' => 'Individuals with high Conscientiousness value quality, accuracy, and high standards. They approach problems logically, analyze facts thoroughly, and ensure rigor in execution.',
            'strengths' => 'Data analysis, quality control, systematic organization, objective problem solving, risk mitigation.',
            'growth_areas' => 'Overcoming analysis paralysis, compromising on perfectionism, embracing ambiguity.',
            'communication_tips' => 'Provide structured facts, logical reasoning, documentation, and clear metrics.',
            'work_style' => 'Thrives in structured, high-accuracy environments where quality and standards are paramount.'
        ]
    ];

    foreach ($dimensions as $dim) {
        $stmtDim->execute($dim);
    }

    // Seed Quiz
    $stmtQuiz = $db->prepare("INSERT INTO quizzes (code, title, description) VALUES ('disc-standard', 'Standard DISC Personality Assessment', 'Discover your core behavioral profile across Dominance, Influence, Steadiness, and Conscientiousness.')");
    $stmtQuiz->execute();
    $quizId = $db->lastInsertId();

    // 12 High Quality Situational DISC Questions
    $questionsData = [
        [
            'q' => 'When faced with an urgent workplace problem or crisis, how do you typically respond?',
            'opts' => [
                'D' => 'Take immediate charge, issue direct instructions, and solve it fast.',
                'I' => 'Rally the team together, boost morale, and brainstorm creative workarounds.',
                'S' => 'Stay calm, support colleagues, and systematically follow proven safety protocols.',
                'C' => 'Analyze the root cause, review documentation, and devise an accurate solution.'
            ]
        ],
        [
            'q' => 'In group meetings, what role do you naturally fall into?',
            'opts' => [
                'D' => 'Pushing the agenda forward, making tough decisions, and keeping focus on results.',
                'I' => 'Generating excitement, encouraging lively discussion, and sharing big vision ideas.',
                'S' => 'Listening attentively, ensuring everyone feels heard, and building consensus.',
                'C' => 'Asking clarifying questions, taking notes, and verifying facts and feasibility.'
            ]
        ],
        [
            'q' => 'What motivates you the most in your daily professional life?',
            'opts' => [
                'D' => 'Overcoming difficult challenges, competition, and achieving measurable success.',
                'I' => 'Social recognition, inspiring others, and working in a fun, collaborative team.',
                'S' => 'Stability, helping others succeed, and maintaining a harmonious environment.',
                'C' => 'Mastery, solving complex analytical problems, and maintaining high quality standards.'
            ]
        ],
        [
            'q' => 'How do you handle sudden, unexpected organizational changes?',
            'opts' => [
                'D' => 'Embrace the change quickly if it leads to better efficiency and opportunities.',
                'I' => 'Get excited about new possibilities and help energize others for the transition.',
                'S' => 'Prefer time to process, needing clear step-by-step guidance to feel secure.',
                'C' => 'Evaluate how the change impacts systems, procedures, and quality standards.'
            ]
        ],
        [
            'q' => 'When giving feedback or working with a colleague, how do you communicate?',
            'opts' => [
                'D' => 'Directly and candidly—getting straight to the point without sugarcoating.',
                'I' => 'Enthusiastically and warmly—focusing on encouragement and positive rapport.',
                'S' => 'Gently and patiently—ensuring their feelings and perspective are respected.',
                'C' => 'Factually and precisely—referencing specific data, guidelines, or performance metrics.'
            ]
        ],
        [
            'q' => 'Which statement best describes your decision-making style?',
            'opts' => [
                'D' => 'Fast, decisive, and intuitive—I prefer taking bold action over waiting.',
                'I' => 'Spontaneous and collaborative—I go with what feels right for the team.',
                'S' => 'Thoughtful and consultative—I seek consensus and consider long-term harmony.',
                'C' => 'Methodical and analytical—I gather all relevant data before deciding.'
            ]
        ],
        [
            'q' => 'Under high pressure or tight deadlines, how do you react?',
            'opts' => [
                'D' => 'Become more forceful, commanding, and focused strictly on the goal.',
                'I' => 'Rely on optimism and humor, attempting to keep team spirits high.',
                'S' => 'Remain calm on the outside, working steadily to support the team.',
                'C' => 'Double-check work carefully to ensure speed does not compromise accuracy.'
            ]
        ],
        [
            'q' => 'What is your greatest personal concern or frustration at work?',
            'opts' => [
                'D' => 'Wasted time, indecisiveness, and lack of tangible progress.',
                'I' => 'Silos, dull routine, rejection, and lack of social interaction.',
                'S' => 'Conflict, unpredictability, aggression, and sudden disruption of routine.',
                'C' => 'Sloppy work, lack of organization, errors, and unverified assumptions.'
            ]
        ],
        [
            'q' => 'When managing or contributing to a complex project, what is your main focus?',
            'opts' => [
                'D' => 'Hitting key milestones and driving speed to market/completion.',
                'I' => 'Pitching the vision, engaging stakeholders, and keeping momentum energetic.',
                'S' => 'Ensuring steady workflow, good team dynamic, and clear roles for everyone.',
                'C' => 'Maintaining rigorous quality control, specifications, and error-free output.'
            ]
        ],
        [
            'q' => 'How do you approach team conflict or disagreement?',
            'opts' => [
                'D' => 'Confront it directly and resolve it immediately to keep moving.',
                'I' => 'Try to smooth it over with humor, optimism, and persuasion.',
                'S' => 'Listen empathetically and look for a fair compromise that preserves harmony.',
                'C' => 'Look at the facts objectively and refer to established guidelines or rules.'
            ]
        ],
        [
            'q' => 'What type of work environment allows you to perform at your best?',
            'opts' => [
                'D' => 'Fast-paced, autonomous environment where I can lead and take risks.',
                'I' => 'Vibrant, collaborative environment with freedom of expression and teamwork.',
                'S' => 'Predictable, supportive environment with clear expectations and mutual trust.',
                'C' => 'Structured, quiet environment with proper tools, rules, and standards.'
            ]
        ],
        [
            'q' => 'How do others most often describe you?',
            'opts' => [
                'D' => 'Ambitious, direct, assertive, and results-focused.',
                'I' => 'Charismatic, enthusiastic, friendly, and persuasive.',
                'S' => 'Reliable, patient, supportive, and dependable.',
                'C' => 'Detail-oriented, logical, systematic, and thorough.'
            ]
        ]
    ];

    $stmtQ = $db->prepare("INSERT INTO questions (quiz_id, question_number, question_text) VALUES (:quiz_id, :question_number, :question_text)");
    $stmtOpt = $db->prepare("INSERT INTO question_options (question_id, dimension_code, option_text, display_order) VALUES (:question_id, :dimension_code, :option_text, :display_order)");

    $qNum = 1;
    foreach ($questionsData as $item) {
        $stmtQ->execute([
            'quiz_id' => $quizId,
            'question_number' => $qNum,
            'question_text' => $item['q']
        ]);
        $questionId = $db->lastInsertId();

        // Options order: shuffle or consistent D, I, S, C
        $order = 1;
        foreach ($item['opts'] as $dimCode => $optText) {
            $stmtOpt->execute([
                'question_id' => $questionId,
                'dimension_code' => $dimCode,
                'option_text' => $optText,
                'display_order' => $order++
            ]);
        }
        $qNum++;
    }

    echo "Database seeded successfully with " . count($questionsData) . " questions and 4 DISC dimensions!\n";

} catch (Exception $e) {
    echo "Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
