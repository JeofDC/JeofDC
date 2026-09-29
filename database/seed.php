<?php
// database/seed.php - Seed initial data for Aptitude Exam Web Application

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'aptitude_db';
$user = getenv('DB_USER') ?: 'aptitude';
$pass = getenv('DB_PASS') ?: 'aptitude_pass';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "Running Database Schema SQL...\n";
$schemaSql = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($schemaSql);

echo "Seeding Roles & Permissions...\n";

// Insert Roles
$roles = [
    'Administrator' => 'Full system access',
    'Examiner' => 'Can manage questions, exams, assignments, monitor and view results',
    'Candidate' => 'Can view assigned exams, take exams, and view allowed results'
];

$roleIds = [];
$stmtRole = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?) ON CONFLICT (name) DO UPDATE SET description = EXCLUDED.description RETURNING id, name");
foreach ($roles as $name => $desc) {
    $stmtRole->execute([$name, $desc]);
    $row = $stmtRole->fetch();
    $roleIds[$row['name']] = $row['id'];
}

// Insert Permissions
$permissions = [
    ['users.view', 'View users', 'users'],
    ['users.create', 'Create users', 'users'],
    ['users.edit', 'Edit users', 'users'],
    ['users.delete', 'Delete users', 'users'],

    ['roles.view', 'View roles', 'roles'],
    ['roles.create', 'Create roles', 'roles'],
    ['roles.edit', 'Edit roles', 'roles'],
    ['roles.delete', 'Delete roles', 'roles'],

    ['questions.view', 'View questions', 'questions'],
    ['questions.create', 'Create questions', 'questions'],
    ['questions.edit', 'Edit questions', 'questions'],
    ['questions.delete', 'Delete questions', 'questions'],

    ['categories.view', 'View categories', 'categories'],
    ['categories.create', 'Create categories', 'categories'],
    ['categories.edit', 'Edit categories', 'categories'],
    ['categories.delete', 'Delete categories', 'categories'],

    ['exams.view', 'View exams', 'exams'],
    ['exams.create', 'Create exams', 'exams'],
    ['exams.edit', 'Edit exams', 'exams'],
    ['exams.delete', 'Delete exams', 'exams'],
    ['exams.publish', 'Publish exams', 'exams'],

    ['exam.assign', 'Assign exams', 'exams'],
    ['exam.monitor', 'Monitor active exam attempts', 'exams'],

    ['results.view', 'View exam results', 'results'],
    ['results.export', 'Export exam results', 'results'],

    ['reports.view', 'View examination reports', 'reports'],
    ['audit.view', 'View system audit logs', 'audit'],
    ['settings.manage', 'Manage system settings', 'settings'],
];

$permIds = [];
$stmtPerm = $pdo->prepare("INSERT INTO permissions (name, description, module) VALUES (?, ?, ?) ON CONFLICT (name) DO UPDATE SET description = EXCLUDED.description RETURNING id, name");
foreach ($permissions as $p) {
    $stmtPerm->execute([$p[0], $p[1], $p[2]]);
    $row = $stmtPerm->fetch();
    $permIds[$row['name']] = $row['id'];
}

// Assign Permissions to Roles
// Admin gets all
$stmtRolePerm = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
foreach ($permIds as $pName => $pId) {
    $stmtRolePerm->execute([$roleIds['Administrator'], $pId]);
}

// Examiner permissions
$examinerPerms = [
    'questions.view', 'questions.create', 'questions.edit', 'questions.delete',
    'categories.view', 'categories.create', 'categories.edit',
    'exams.view', 'exams.create', 'exams.edit', 'exams.publish',
    'exam.assign', 'exam.monitor',
    'results.view', 'results.export', 'reports.view'
];
foreach ($examinerPerms as $pName) {
    if (isset($permIds[$pName])) {
        $stmtRolePerm->execute([$roleIds['Examiner'], $permIds[$pName]]);
    }
}

echo "Seeding Default Users...\n";
$users = [
    [
        'username' => 'admin',
        'email' => 'admin@aptitude.test',
        'password' => 'Admin123!',
        'first_name' => 'System',
        'last_name' => 'Administrator',
        'role' => 'Administrator',
        'emp_num' => 'EMP-001'
    ],
    [
        'username' => 'examiner',
        'email' => 'examiner@aptitude.test',
        'password' => 'Examiner123!',
        'first_name' => 'John',
        'last_name' => 'Examiner',
        'role' => 'Examiner',
        'emp_num' => 'EMP-002'
    ],
    [
        'username' => 'candidate',
        'email' => 'candidate@aptitude.test',
        'password' => 'Candidate123!',
        'first_name' => 'Jane',
        'last_name' => 'Candidate',
        'role' => 'Candidate',
        'emp_num' => 'CAND-001'
    ]
];

$userIds = [];
$stmtUser = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, employee_number, status) VALUES (?, ?, ?, ?, ?, ?, 'active') RETURNING id, username");
$stmtUserRole = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?) ON CONFLICT DO NOTHING");

foreach ($users as $u) {
    $hash = password_hash($u['password'], PASSWORD_BCRYPT);
    $stmtUser->execute([$u['username'], $u['email'], $hash, $u['first_name'], $u['last_name'], $u['emp_num']]);
    $row = $stmtUser->fetch();
    $userIds[$row['username']] = $row['id'];
    $stmtUserRole->execute([$row['id'], $roleIds[$u['role']]]);
}

echo "Seeding Aptitude Categories...\n";
$categories = [
    [
        'name' => 'Abstract Reasoning',
        'code' => 'ABSTRACT',
        'desc' => 'Tests ability to identify patterns, shape sequences, matrix reasoning, and visual analogies.',
        'order' => 1
    ],
    [
        'name' => 'Verbal Reasoning',
        'code' => 'VERBAL',
        'desc' => 'Tests comprehension, vocabulary, language logic, reading comprehension, and relationships between words.',
        'order' => 2
    ],
    [
        'name' => 'Numerical Reasoning',
        'code' => 'NUMERIC',
        'desc' => 'Tests arithmetic, number sequences, percentages, ratios, data interpretation, and mathematical problem solving.',
        'order' => 3
    ]
];

$catIds = [];
$stmtCat = $pdo->prepare("INSERT INTO categories (name, code, description, display_order) VALUES (?, ?, ?, ?) RETURNING id, code");
foreach ($categories as $c) {
    $stmtCat->execute([$c['name'], $c['code'], $c['desc'], $c['order']]);
    $row = $stmtCat->fetch();
    $catIds[$row['code']] = $row['id'];
}

echo "Seeding 30 Sample Questions...\n";

// Helper to insert question and choices
function addQuestion($pdo, $catId, $creatorId, $text, $type, $diff, $points, $explanation, $choices) {
    $stmt = $pdo->prepare("INSERT INTO questions (category_id, question_type, question_text, explanation, difficulty, points, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active') RETURNING id");
    $stmt->execute([$catId, $type, $text, $explanation, $diff, $points, $creatorId]);
    $qId = $stmt->fetchColumn();

    $stmtChoice = $pdo->prepare("INSERT INTO question_choices (question_id, choice_label, choice_text, is_correct, display_order) VALUES (?, ?, ?, ?, ?)");
    $order = 1;
    foreach ($choices as $ch) {
        $stmtChoice->execute([$qId, $ch['label'], $ch['text'], $ch['is_correct'] ? 'true' : 'false', $order++]);
    }
    return $qId;
}

$adminId = $userIds['admin'];

// 1. ABSTRACT REASONING QUESTIONS (10)
$abstractQ = [
    [
        "Which figure comes next in the sequence of rotation: [Circle top-left -> Circle top-right -> Circle bottom-right -> ?]",
        "Easy", 1.0, "The circle moves clockwise through the corners of the square.",
        [
            ['label' => 'A', 'text' => 'Circle bottom-left', 'is_correct' => true],
            ['label' => 'B', 'text' => 'Circle top-left', 'is_correct' => false],
            ['label' => 'C', 'text' => 'Circle center', 'is_correct' => false],
            ['label' => 'D', 'text' => 'Circle bottom-right', 'is_correct' => false]
        ]
    ],
    [
        "Look at the series: 3, 6, 12, 24, ? What number pattern analogy completes the rule?",
        "Easy", 1.0, "Each number is multiplied by 2.",
        [
            ['label' => 'A', 'text' => '30', 'is_correct' => false],
            ['label' => 'B', 'text' => '36', 'is_correct' => false],
            ['label' => 'C', 'text' => '48', 'is_correct' => true],
            ['label' => 'D', 'text' => '52', 'is_correct' => false]
        ]
    ],
    [
        "If Triangle is to 3 sides, as Pentagon is to:",
        "Easy", 1.0, "A pentagon has 5 sides.",
        [
            ['label' => 'A', 'text' => '4 sides', 'is_correct' => false],
            ['label' => 'B', 'text' => '5 sides', 'is_correct' => true],
            ['label' => 'C', 'text' => '6 sides', 'is_correct' => false],
            ['label' => 'D', 'text' => '8 sides', 'is_correct' => false]
        ]
    ],
    [
        "In a 3x3 matrix where each row alternates shaded shapes: [Row 1: 1 Shaded, Row 2: 2 Shaded, Row 3: ?]",
        "Medium", 1.0, "Row 3 follows the increment pattern to contain 3 shaded shapes.",
        [
            ['label' => 'A', 'text' => '1 Shaded shape', 'is_correct' => false],
            ['label' => 'B', 'text' => '2 Shaded shapes', 'is_correct' => false],
            ['label' => 'C', 'text' => '3 Shaded shapes', 'is_correct' => true],
            ['label' => 'D', 'text' => 'No Shaded shapes', 'is_correct' => false]
        ]
    ],
    [
        "Identify the odd shape out among geometric polygons: Square, Hexagon, Octagon, Sphere.",
        "Easy", 1.0, "Sphere is a 3D shape while the others are 2D polygons.",
        [
            ['label' => 'A', 'text' => 'Square', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Hexagon', 'is_correct' => false],
            ['label' => 'C', 'text' => 'Octagon', 'is_correct' => false],
            ['label' => 'D', 'text' => 'Sphere', 'is_correct' => true]
        ]
    ],
    [
        "Mirror symmetry rule: A right-pointing arrow flipped horizontally points to:",
        "Easy", 1.0, "Horizontal reflection reverses direction from right to left.",
        [
            ['label' => 'A', 'text' => 'Up', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Down', 'is_correct' => false],
            ['label' => 'C', 'text' => 'Left', 'is_correct' => true],
            ['label' => 'D', 'text' => 'Right', 'is_correct' => false]
        ]
    ],
    [
        "Overlapping Venn Pattern: Set A contains circles, Set B contains blue shapes. The intersection contains:",
        "Medium", 1.0, "The intersection contains elements having both attributes (Blue circles).",
        [
            ['label' => 'A', 'text' => 'Red squares', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Blue circles', 'is_correct' => true],
            ['label' => 'C', 'text' => 'Blue triangles', 'is_correct' => false],
            ['label' => 'D', 'text' => 'Yellow circles', 'is_correct' => false]
        ]
    ],
    [
        "Pattern sequence: Square (4 sides), Pentagon (5 sides), Hexagon (6 sides), ? (7 sides)",
        "Medium", 1.0, "A 7-sided polygon is a Heptagon.",
        [
            ['label' => 'A', 'text' => 'Octagon', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Heptagon', 'is_correct' => true],
            ['label' => 'C', 'text' => 'Nonagon', 'is_correct' => false],
            ['label' => 'D', 'text' => 'Decagon', 'is_correct' => false]
        ]
    ],
    [
        "Grid Transformation: If an icon rotates 90 degrees clockwise at each step, after 4 steps it returns to:",
        "Medium", 1.0, "4 rotations of 90 degrees equals 360 degrees (original position).",
        [
            ['label' => 'A', 'text' => 'Original position', 'is_correct' => true],
            ['label' => 'B', 'text' => 'Inverted position (180 deg)', 'is_correct' => false],
            ['label' => 'C', 'text' => 'Left rotated position (270 deg)', 'is_correct' => false],
            ['label' => 'D', 'text' => 'Right rotated position (90 deg)', 'is_correct' => false]
        ]
    ],
    [
        "Complex visual analogy: Black square in white circle is to White square in black circle, as Black triangle in white star is to:",
        "Hard", 1.0, "Both inner/outer shapes and shading colors are inverted.",
        [
            ['label' => 'A', 'text' => 'Black triangle in black star', 'is_correct' => false],
            ['label' => 'B', 'text' => 'White triangle in black star', 'is_correct' => true],
            ['label' => 'C', 'text' => 'White star in black triangle', 'is_correct' => false],
            ['label' => 'D', 'text' => 'Black star in white triangle', 'is_correct' => false]
        ]
    ]
];

foreach ($abstractQ as $q) {
    addQuestion($pdo, $catIds['ABSTRACT'], $adminId, $q[0], 'multiple_choice', $q[1], $q[2], $q[3], $q[4]);
}

// 2. VERBAL REASONING QUESTIONS (10)
$verbalQ = [
    [
        "Choose the word closest in meaning (synonym) to CANDID:",
        "Easy", 1.0, "Candid means truthful and straightforward.",
        [
            ['label' => 'A', 'text' => 'Deceitful', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Frank', 'is_correct' => true],
            ['label' => 'C', 'text' => 'Secretive', 'is_correct' => false],
            ['label' => 'D', 'text' => 'Ambiguous', 'is_correct' => false]
        ]
    ],
    [
        "Choose the word opposite in meaning (antonym) to MITIGATE:",
        "Medium", 1.0, "Mitigate means to make less severe; aggravate means to make worse.",
        [
            ['label' => 'A', 'text' => 'Alleviate', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Aggravate', 'is_correct' => true],
            ['label' => 'C', 'text' => 'Soothe', 'is_correct' => false],
            ['label' => 'D', 'text' => 'Reduce', 'is_correct' => false]
        ]
    ],
    [
        "Analogy: ARCHITECT is to BUILDING as SCULPTOR is to:",
        "Easy", 1.0, "An architect creates buildings; a sculptor creates statues.",
        [
            ['label' => 'A', 'text' => 'Museum', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Statue', 'is_correct' => true],
            ['label' => 'C', 'text' => 'Clay', 'is_correct' => false],
            ['label' => 'D', 'text' => 'Chisel', 'is_correct' => false]
        ]
    ],
    [
        "Statement: 'All managers are leaders. Some leaders are visionaries.' Which conclusion LOGICALLY follows?",
        "Hard", 1.0, "Neither 'All managers are visionaries' nor 'No managers are visionaries' strictly follows without further information.",
        [
            ['label' => 'A', 'text' => 'All managers are visionaries.', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Some managers may be visionaries.', 'is_correct' => true],
            ['label' => 'C', 'text' => 'No leaders are managers.', 'is_correct' => false],
            ['label' => 'D', 'text' => 'All visionaries are managers.', 'is_correct' => false]
        ]
    ],
    [
        "Sentence Completion: 'Despite the storm's severity, the resilient team remained _______ and completed the project on time.'",
        "Medium", 1.0, "Undeterred best fits the sentence context of perseverance.",
        [
            ['label' => 'A', 'text' => 'undeterred', 'is_correct' => true],
            ['label' => 'B', 'text' => 'hesitant', 'is_correct' => false],
            ['label' => 'C', 'text' => 'bewildered', 'is_correct' => false],
            ['label' => 'D', 'text' => 'defeated', 'is_correct' => false]
        ]
    ],
    [
        "Which of the following is an antonym for MUNDANE?",
        "Medium", 1.0, "Mundane means dull/ordinary; extraordinary is its opposite.",
        [
            ['label' => 'A', 'text' => 'Ordinary', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Routine', 'is_correct' => false],
            ['label' => 'C', 'text' => 'Extraordinary', 'is_correct' => true],
            ['label' => 'D', 'text' => 'Commonplace', 'is_correct' => false]
        ]
    ],
    [
        "Find the odd word out: Apple, Banana, Carrot, Date",
        "Easy", 1.0, "Carrot is a vegetable while the others are fruits.",
        [
            ['label' => 'A', 'text' => 'Apple', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Banana', 'is_correct' => false],
            ['label' => 'C', 'text' => 'Carrot', 'is_correct' => true],
            ['label' => 'D', 'text' => 'Date', 'is_correct' => false]
        ]
    ],
    [
        "Analogy: LETHARGIC is to ENERGY as PENNILESS is to:",
        "Medium", 1.0, "Lethargic describes lacking energy; penniless describes lacking money.",
        [
            ['label' => 'A', 'text' => 'Coins', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Money', 'is_correct' => true],
            ['label' => 'C', 'text' => 'Bank', 'is_correct' => false],
            ['label' => 'D', 'text' => 'Wealthy', 'is_correct' => false]
        ]
    ],
    [
        "Comprehension: 'Innovation thrives in environments where failure is treated as a learning opportunity rather than a offense.' What is the author's main point?",
        "Medium", 1.0, "The passage asserts that psychological safety regarding failure encourages innovation.",
        [
            ['label' => 'A', 'text' => 'Failure should be punished severely.', 'is_correct' => false],
            ['label' => 'B', 'text' => 'Innovation requires accepting failure as learning.', 'is_correct' => true],
            ['label' => 'C', 'text' => 'Innovation prevents all failures.', 'is_correct' => false],
            ['label' => 'D', 'text' => 'Learning is impossible without offenses.', 'is_correct' => false]
        ]
    ],
    [
        "True or False statement: 'A synonym for PRUDENT is RECKLESS.'",
        "Easy", 1.0, "Prudent means cautious/wise, which is the opposite of reckless.",
        [
            ['label' => 'A', 'text' => 'True', 'is_correct' => false],
            ['label' => 'B', 'text' => 'False', 'is_correct' => true]
        ]
    ]
];

foreach ($verbalQ as $q) {
    $type = (count($q[4]) === 2 && $q[4][0]['text'] === 'True') ? 'true_false' : 'multiple_choice';
    addQuestion($pdo, $catIds['VERBAL'], $adminId, $q[0], $type, $q[1], $q[2], $q[3], $q[4]);
}

// 3. NUMERICAL REASONING QUESTIONS (10)
$numericQ = [
    [
        "Find the next number in the sequence: 5, 10, 20, 40, ?",
        "Easy", 1.0, "Double the previous number: 40 x 2 = 80.",
        [
            ['label' => 'A', 'text' => '60', 'is_correct' => false],
            ['label' => 'B', 'text' => '70', 'is_correct' => false],
            ['label' => 'C', 'text' => '80', 'is_correct' => true],
            ['label' => 'D', 'text' => '100', 'is_correct' => false]
        ]
    ],
    [
        "A product costs $200. It is discounted by 15%. What is the final sale price?",
        "Easy", 1.0, "15% of $200 = $30. Sale price = $200 - $30 = $170.",
        [
            ['label' => 'A', 'text' => '$170', 'is_correct' => true],
            ['label' => 'B', 'text' => '$180', 'is_correct' => false],
            ['label' => 'C', 'text' => '$185', 'is_correct' => false],
            ['label' => 'D', 'text' => '$150', 'is_correct' => false]
        ]
    ],
    [
        "If 4 workers can assemble 20 units in 5 hours, how many units can 8 workers assemble in 5 hours at the same rate?",
        "Medium", 1.0, "Doubling workers from 4 to 8 doubles the output from 20 to 40 units.",
        [
            ['label' => 'A', 'text' => '30 units', 'is_correct' => false],
            ['label' => 'B', 'text' => '40 units', 'is_correct' => true],
            ['label' => 'C', 'text' => '50 units', 'is_correct' => false],
            ['label' => 'D', 'text' => '60 units', 'is_correct' => false]
        ]
    ],
    [
        "What is the average (mean) of 12, 18, 24, and 30?",
        "Easy", 1.0, "Sum = 84. Average = 84 / 4 = 21.",
        [
            ['label' => 'A', 'text' => '20', 'is_correct' => false],
            ['label' => 'B', 'text' => '21', 'is_correct' => true],
            ['label' => 'C', 'text' => '22', 'is_correct' => false],
            ['label' => 'D', 'text' => '24', 'is_correct' => false]
        ]
    ],
    [
        "Solve for x: 3x + 15 = 45",
        "Easy", 1.0, "3x = 30 => x = 10.",
        [
            ['label' => 'A', 'text' => 'x = 8', 'is_correct' => false],
            ['label' => 'B', 'text' => 'x = 10', 'is_correct' => true],
            ['label' => 'C', 'text' => 'x = 12', 'is_correct' => false],
            ['label' => 'D', 'text' => 'x = 15', 'is_correct' => false]
        ]
    ],
    [
        "Find the missing number in the pattern: 2, 3, 5, 8, 12, ?",
        "Medium", 1.0, "Differences are +1, +2, +3, +4. Next difference is +5: 12 + 5 = 17.",
        [
            ['label' => 'A', 'text' => '15', 'is_correct' => false],
            ['label' => 'B', 'text' => '16', 'is_correct' => false],
            ['label' => 'C', 'text' => '17', 'is_correct' => true],
            ['label' => 'D', 'text' => '18', 'is_correct' => false]
        ]
    ],
    [
        "A train travels 240 miles in 4 hours. What is its average speed in miles per hour?",
        "Easy", 1.0, "Speed = Distance / Time = 240 / 4 = 60 mph.",
        [
            ['label' => 'A', 'text' => '50 mph', 'is_correct' => false],
            ['label' => 'B', 'text' => '55 mph', 'is_correct' => false],
            ['label' => 'C', 'text' => '60 mph', 'is_correct' => true],
            ['label' => 'D', 'text' => '65 mph', 'is_correct' => false]
        ]
    ],
    [
        "In a company of 150 employees, 60% are female. How many male employees are there?",
        "Medium", 1.0, "Male percentage = 40%. 40% of 150 = 60.",
        [
            ['label' => 'A', 'text' => '50', 'is_correct' => false],
            ['label' => 'B', 'text' => '60', 'is_correct' => true],
            ['label' => 'C', 'text' => '75', 'is_correct' => false],
            ['label' => 'D', 'text' => '90', 'is_correct' => false]
        ]
    ],
    [
        "Ratio of A to B is 3:5. If B is 35, what is A?",
        "Medium", 1.0, "5 parts = 35 => 1 part = 7. 3 parts = 3 * 7 = 21.",
        [
            ['label' => 'A', 'text' => '18', 'is_correct' => false],
            ['label' => 'B', 'text' => '21', 'is_correct' => true],
            ['label' => 'C', 'text' => '24', 'is_correct' => false],
            ['label' => 'D', 'text' => '25', 'is_correct' => false]
        ]
    ],
    [
        "If a box contains 3 red balls, 5 blue balls, and 2 green balls, what is the probability of picking a blue ball?",
        "Hard", 1.0, "Total balls = 10. Blue balls = 5. Probability = 5/10 = 0.50 (50%).",
        [
            ['label' => 'A', 'text' => '30%', 'is_correct' => false],
            ['label' => 'B', 'text' => '40%', 'is_correct' => false],
            ['label' => 'C', 'text' => '50%', 'is_correct' => true],
            ['label' => 'D', 'text' => '60%', 'is_correct' => false]
        ]
    ]
];

foreach ($numericQ as $q) {
    addQuestion($pdo, $catIds['NUMERIC'], $adminId, $q[0], 'multiple_choice', $q[1], $q[2], $q[3], $q[4]);
}

echo "Seeding Sample Examination 'General Aptitude Assessment 2026'...\n";

$stmtExam = $pdo->prepare("INSERT INTO exams (exam_code, title, description, instructions, duration_minutes, passing_percentage, randomize_questions, randomize_choices, status, created_by, published_at) VALUES (?, ?, ?, ?, ?, ?, true, true, 'Published', ?, NOW()) RETURNING id");

$examInstructions = "Please read the following instructions carefully before starting the exam:\n"
    . "1. This assessment consists of 30 questions across 3 sections: Abstract Reasoning, Verbal Reasoning, and Numerical Reasoning.\n"
    . "2. You have 45 minutes to complete the exam.\n"
    . "3. The passing score is 70%.\n"
    . "4. Your answers will be automatically saved as you select them.\n"
    . "5. Do not refresh or close your browser tab during the exam.";

$stmtExam->execute([
    'EXAM-2026-GAA',
    'General Aptitude Assessment 2026',
    'Standard aptitude assessment evaluating logical, verbal, and numerical skills.',
    $examInstructions,
    45,
    70.00,
    $adminId
]);
$examId = $stmtExam->fetchColumn();

// Add Exam Categories Configuration
$stmtExamCat = $pdo->prepare("INSERT INTO exam_categories (exam_id, category_id, question_count, time_limit_minutes, points_per_question, display_order) VALUES (?, ?, ?, ?, 1.00, ?)");

$stmtExamCat->execute([$examId, $catIds['ABSTRACT'], 10, 15, 1]);
$stmtExamCat->execute([$examId, $catIds['VERBAL'], 10, 15, 2]);
$stmtExamCat->execute([$examId, $catIds['NUMERIC'], 10, 15, 3]);

echo "Assigning Exam to Candidate...\n";
$candidateId = $userIds['candidate'];
$stmtAssign = $pdo->prepare("INSERT INTO exam_assignments (exam_id, candidate_id, available_from, available_until, status, assigned_by) VALUES (?, ?, NOW() - INTERVAL '1 day', NOW() + INTERVAL '30 days', 'Assigned', ?)");
$stmtAssign->execute([$examId, $candidateId, $adminId]);

echo "Database Seed Completed Successfully!\n";
