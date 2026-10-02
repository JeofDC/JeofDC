-- DISC Personality Assessment Database Schema

DROP TABLE IF EXISTS attempt_answers CASCADE;
DROP TABLE IF EXISTS test_attempts CASCADE;
DROP TABLE IF EXISTS question_options CASCADE;
DROP TABLE IF EXISTS questions CASCADE;
DROP TABLE IF EXISTS quizzes CASCADE;
DROP TABLE IF EXISTS dimensions CASCADE;

-- Dimensions table
CREATE TABLE dimensions (
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

-- Quizzes metadata
CREATE TABLE quizzes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Questions
CREATE TABLE questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quiz_id INT NOT NULL,
    question_number INT NOT NULL,
    question_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- Question Options (Mapped to DISC dimensions)
CREATE TABLE question_options (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    question_id INT NOT NULL,
    dimension_code VARCHAR(10) NOT NULL,
    option_text TEXT NOT NULL,
    display_order INT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (dimension_code) REFERENCES dimensions(code) ON DELETE CASCADE
);

-- Test Attempts
CREATE TABLE test_attempts (
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

-- Attempt Answer Details
CREATE TABLE attempt_answers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_id INT NOT NULL,
    dimension_code VARCHAR(10) NOT NULL,
    FOREIGN KEY (attempt_id) REFERENCES test_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id),
    FOREIGN KEY (selected_option_id) REFERENCES question_options(id)
);
