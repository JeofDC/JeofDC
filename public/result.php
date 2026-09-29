<?php
// public/result.php - Polished Single Examination Result View
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requireLogin();

$attemptId = !empty($_GET['attempt_id']) ? intval($_GET['attempt_id']) : null;
if (!$attemptId) {
    header('Location: /results.php');
    exit();
}

$currentUser = getCurrentUser();
$isCandidate = hasRole('Candidate') && !hasRole('Administrator') && !hasRole('Examiner');

$pdo = getDbConnection();

// Fetch Attempt Details
$sql = "
    SELECT ea.*, e.title as exam_title, e.exam_code, e.passing_percentage, e.description as exam_desc,
           u.first_name, u.last_name, u.email, u.employee_number
    FROM exam_attempts ea
    JOIN exams e ON e.id = ea.exam_id
    JOIN users u ON u.id = ea.candidate_id
    WHERE ea.id = ?
";
if ($isCandidate) {
    $sql .= " AND ea.candidate_id = " . intval($currentUser['id']);
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$attemptId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    die("Examination result not found or access denied.");
}

// Fetch Category Breakdown
$stmtCatScores = $pdo->prepare("
    SELECT c.id as category_id, c.name as category_name, c.code as category_code,
           COUNT(aq.id) as total_questions,
           SUM(CASE WHEN ca.is_correct = TRUE THEN 1 ELSE 0 END) as correct_count,
           SUM(CASE WHEN ca.is_correct = TRUE THEN aq.points ELSE 0 END) as points_earned,
           SUM(aq.points) as total_points
    FROM attempt_questions aq
    JOIN categories c ON c.id = aq.category_id
    LEFT JOIN candidate_answers ca ON ca.attempt_question_id = aq.id
    WHERE aq.attempt_id = ?
    GROUP BY c.id, c.name, c.code
");
$stmtCatScores->execute([$attemptId]);
$catScores = $stmtCatScores->fetchAll();

$pageTitle = "Result - " . $attempt['exam_title'];
require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="row justify-content-center py-3">
    <div class="col-lg-9">
        <!-- Main Result Header Card -->
        <div class="card card-mega shadow-sm mb-4">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <?php if ($attempt['passed']): ?>
                        <div class="display-1 text-success"><i class="fa-solid fa-circle-check"></i></div>
                        <h2 class="fw-bold text-success mt-2">EXAMINATION PASSED</h2>
                        <span class="badge badge-success fs-6 px-3 py-2">Overall Score: <?= e($attempt['percentage']) ?>%</span>
                    <?php else: ?>
                        <div class="display-1 text-danger"><i class="fa-solid fa-circle-xmark"></i></div>
                        <h2 class="fw-bold text-danger mt-2">EXAMINATION FAILED</h2>
                        <span class="badge badge-danger fs-6 px-3 py-2">Overall Score: <?= e($attempt['percentage']) ?>%</span>
                    <?php endif; ?>
                </div>

                <p class="text-muted mb-0">Passing Threshold: <strong><?= e($attempt['passing_percentage']) ?>%</strong></p>
            </div>
        </div>

        <!-- Candidate & Exam Info -->
        <div class="card card-mega shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="fa-solid fa-id-card me-2 text-primary"></i>Candidate & Assessment Information
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="text-muted small d-block">Candidate Name</span>
                        <strong class="fs-6 text-dark"><?= e($attempt['first_name'] . ' ' . $attempt['last_name']) ?></strong>
                        <small class="text-muted d-block"><?= e($attempt['email']) ?></small>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted small d-block">Examination Title</span>
                        <strong class="fs-6 text-dark"><?= e($attempt['exam_title']) ?></strong>
                        <small class="text-muted d-block">Code: <code><?= e($attempt['exam_code']) ?></code></small>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted small d-block">Started At</span>
                        <span class="text-dark fw-medium"><?= date('M d, Y H:i:s', strtotime($attempt['started_at'])) ?></span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted small d-block">Submitted At</span>
                        <span class="text-dark fw-medium"><?= $attempt['submitted_at'] ? date('M d, Y H:i:s', strtotime($attempt['submitted_at'])) : 'N/A' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Score Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <span class="text-muted small fw-semibold d-block">Total Questions</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= e($attempt['total_questions']) ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <span class="text-muted small fw-semibold d-block">Correct Answers</span>
                    <h3 class="fw-bold mb-0 mt-1 text-success"><?= e($attempt['correct_answers']) ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <span class="text-muted small fw-semibold d-block">Wrong / Skipped</span>
                    <h3 class="fw-bold mb-0 mt-1 text-danger"><?= e($attempt['wrong_answers']) ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <span class="text-muted small fw-semibold d-block">Points Earned</span>
                    <h3 class="fw-bold mb-0 mt-1 text-primary"><?= e($attempt['score']) ?></h3>
                </div>
            </div>
        </div>

        <!-- Category Performance Breakdown -->
        <div class="card card-mega shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="fa-solid fa-chart-pie me-2 text-success"></i>Performance by Aptitude Category
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th>Questions</th>
                                <th>Correct</th>
                                <th>Points Earned</th>
                                <th>Score %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($catScores as $cs):
                                $totPts = floatval($cs['total_points']);
                                $earnedPts = floatval($cs['points_earned']);
                                $catPct = $totPts > 0 ? round(($earnedPts / $totPts) * 100, 2) : 0.0;
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= e($cs['category_name']) ?></td>
                                <td><?= e($cs['total_questions']) ?></td>
                                <td><span class="badge badge-success"><?= e($cs['correct_count']) ?></span></td>
                                <td><?= e($earnedPts) ?> / <?= e($totPts) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 10px;">
                                            <div class="progress-bar <?= $catPct >= $attempt['passing_percentage'] ? 'bg-success' : 'bg-danger' ?>" style="width: <?= $catPct ?>%"></div>
                                        </div>
                                        <span class="fw-bold small"><?= $catPct ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="text-center mb-4">
            <?php if (hasRole('Candidate') && !hasRole('Administrator') && !hasRole('Examiner')): ?>
                <a href="/candidate-dashboard.php" class="btn btn-primary px-4 fw-semibold"><i class="fa-solid fa-house me-2"></i>Return to Candidate Dashboard</a>
            <?php else: ?>
                <a href="/results.php" class="btn btn-primary px-4 fw-semibold"><i class="fa-solid fa-list me-2"></i>Return to All Results</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>
