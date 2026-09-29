<?php
// public/dashboard.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requireLogin();

if (hasRole('Candidate') && !hasRole('Administrator') && !hasRole('Examiner')) {
    header('Location: /candidate-dashboard.php');
    exit();
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

$pdo = getDbConnection();

// Metrics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalQuestions = $pdo->query("SELECT COUNT(*) FROM questions WHERE status='active'")->fetchColumn();
$publishedExams = $pdo->query("SELECT COUNT(*) FROM exams WHERE status='Published'")->fetchColumn();
$activeAttempts = $pdo->query("SELECT COUNT(*) FROM exam_attempts WHERE status='In Progress'")->fetchColumn();
$completedExams = $pdo->query("SELECT COUNT(*) FROM exam_attempts WHERE status='Completed'")->fetchColumn();
$passedCandidates = $pdo->query("SELECT COUNT(*) FROM exam_attempts WHERE status='Completed' AND passed=TRUE")->fetchColumn();

// Recent Exams
$recentExams = $pdo->query("
    SELECT e.*, u.username as creator
    FROM exams e
    LEFT JOIN users u ON u.id = e.created_by
    ORDER BY e.id DESC LIMIT 5
")->fetchAll();

// Recent Attempts
$recentAttempts = $pdo->query("
    SELECT ea.*, e.title as exam_title, u.first_name, u.last_name
    FROM exam_attempts ea
    JOIN exams e ON e.id = ea.exam_id
    JOIN users u ON u.id = ea.candidate_id
    ORDER BY ea.id DESC LIMIT 5
")->fetchAll();

require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">System Overview</h3>
        <p class="text-muted mb-0">Real-time statistics & aptitude assessment monitoring</p>
    </div>
    <?php if (hasPermission('exams.create')): ?>
        <a href="/exams.php" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i>Create Exam</a>
    <?php endif; ?>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Total Users</span>
                    <h4 class="fw-bold mb-0 mt-1"><?= $totalUsers ?></h4>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Question Bank</span>
                    <h4 class="fw-bold mb-0 mt-1"><?= $totalQuestions ?></h4>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-database"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Published Exams</span>
                    <h4 class="fw-bold mb-0 mt-1"><?= $publishedExams ?></h4>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-file-signature"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Active Attempts</span>
                    <h4 class="fw-bold mb-0 mt-1 text-warning"><?= $activeAttempts ?></h4>
                </div>
                <div class="stat-icon text-warning" style="background: rgba(255, 193, 7, 0.12);"><i class="fa-solid fa-spinner fa-spin"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Completed</span>
                    <h4 class="fw-bold mb-0 mt-1 text-success"><?= $completedExams ?></h4>
                </div>
                <div class="stat-icon text-success"><i class="fa-solid fa-circle-check"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-lg-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Passed</span>
                    <h4 class="fw-bold mb-0 mt-1 text-primary"><?= $passedCandidates ?></h4>
                </div>
                <div class="stat-icon text-primary" style="background: rgba(13, 110, 253, 0.12);"><i class="fa-solid fa-trophy"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Exams -->
    <div class="col-lg-6 mb-4">
        <div class="card card-mega">
            <div class="card-header">
                <span><i class="fa-solid fa-file-signature me-2 text-success"></i>Recent Examinations</span>
                <a href="/exams.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Title</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentExams as $ex): ?>
                            <tr>
                                <td><code><?= e($ex['exam_code']) ?></code></td>
                                <td class="fw-medium"><?= e($ex['title']) ?></td>
                                <td><?= e($ex['duration_minutes']) ?> mins</td>
                                <td>
                                    <?php if ($ex['status'] === 'Published'): ?>
                                        <span class="badge badge-success">Published</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= e($ex['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Attempts -->
    <div class="col-lg-6 mb-4">
        <div class="card card-mega">
            <div class="card-header">
                <span><i class="fa-solid fa-user-clock me-2 text-primary"></i>Recent Exam Attempts</span>
                <a href="/results.php" class="btn btn-sm btn-outline-secondary">View Results</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Candidate</th>
                                <th>Exam</th>
                                <th>Score</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAttempts as $att): ?>
                            <tr>
                                <td><?= e($att['first_name'] . ' ' . $att['last_name']) ?></td>
                                <td class="fw-medium"><?= e($att['exam_title']) ?></td>
                                <td><?= e($att['percentage']) ?>%</td>
                                <td>
                                    <?php if ($att['status'] === 'In Progress'): ?>
                                        <span class="badge badge-warning">In Progress</span>
                                    <?php elseif ($att['passed']): ?>
                                        <span class="badge badge-success">PASSED</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">FAILED</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>
