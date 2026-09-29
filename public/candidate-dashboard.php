<?php
// public/candidate-dashboard.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requireLogin();

$currentUser = getCurrentUser();
$candidateId = $currentUser['id'];
$pageTitle = 'My Examinations';
$activeNav = 'candidate_exams';

$pdo = getDbConnection();

// Fetch Candidate Metrics
$stmtAssigned = $pdo->prepare("SELECT COUNT(*) FROM exam_assignments WHERE candidate_id = ? AND status IN ('Assigned', 'Started')");
$stmtAssigned->execute([$candidateId]);
$assignedCount = $stmtAssigned->fetchColumn();

$stmtCompleted = $pdo->prepare("SELECT COUNT(*) FROM exam_attempts WHERE candidate_id = ? AND status = 'Completed'");
$stmtCompleted->execute([$candidateId]);
$completedCount = $stmtCompleted->fetchColumn();

$stmtActiveAttempt = $pdo->prepare("SELECT id, exam_id FROM exam_attempts WHERE candidate_id = ? AND status = 'In Progress' AND expires_at > NOW() LIMIT 1");
$stmtActiveAttempt->execute([$candidateId]);
$activeAttempt = $stmtActiveAttempt->fetch();

// Fetch Assigned Exams
$stmtExams = $pdo->prepare("
    SELECT ea.id as assignment_id, ea.available_from, ea.available_until, ea.status as assignment_status,
           e.id as exam_id, e.exam_code, e.title, e.description, e.duration_minutes, e.passing_percentage,
           att.id as active_attempt_id, att.status as attempt_status, att.percentage, att.passed
    FROM exam_assignments ea
    JOIN exams e ON e.id = ea.exam_id
    LEFT JOIN exam_attempts att ON att.exam_id = e.id AND att.candidate_id = ea.candidate_id AND att.status = 'In Progress'
    WHERE ea.candidate_id = ?
    ORDER BY ea.id DESC
");
$stmtExams->execute([$candidateId]);
$assignments = $stmtExams->fetchAll();

require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Candidate Dashboard</h3>
        <p class="text-muted mb-0">View assigned aptitude tests and take assessments</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Assigned Examinations</span>
                    <h4 class="fw-bold mb-0 mt-1"><?= $assignedCount ?></h4>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-file-signature"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Active Attempt</span>
                    <h4 class="fw-bold mb-0 mt-1 text-warning"><?= $activeAttempt ? '1 In Progress' : 'None' ?></h4>
                </div>
                <div class="stat-icon text-warning" style="background: rgba(255,193,7,0.12);"><i class="fa-solid fa-spinner fa-spin"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Completed Examinations</span>
                    <h4 class="fw-bold mb-0 mt-1 text-success"><?= $completedCount ?></h4>
                </div>
                <div class="stat-icon text-success"><i class="fa-solid fa-circle-check"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="card card-mega">
    <div class="card-header">
        <span><i class="fa-solid fa-clipboard-list me-2 text-success"></i>My Examination List</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Exam Title</th>
                        <th>Duration</th>
                        <th>Passing Score</th>
                        <th>Available Until</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-folder-open display-6 d-block mb-2 text-secondary"></i>
                            No examinations assigned to you yet.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $a): ?>
                        <tr>
                            <td><code><?= e($a['exam_code']) ?></code></td>
                            <td>
                                <div class="fw-bold"><?= e($a['title']) ?></div>
                                <small class="text-muted"><?= e($a['description']) ?></small>
                            </td>
                            <td><i class="fa-regular fa-clock me-1 text-muted"></i><?= e($a['duration_minutes']) ?> mins</td>
                            <td><?= e($a['passing_percentage']) ?>%</td>
                            <td><?= $a['available_until'] ? date('M d, Y H:i', strtotime($a['available_until'])) : 'No Limit' ?></td>
                            <td>
                                <?php if ($a['attempt_status'] === 'In Progress'): ?>
                                    <span class="badge badge-warning">IN PROGRESS</span>
                                <?php elseif ($a['assignment_status'] === 'Completed'): ?>
                                    <span class="badge badge-success">COMPLETED</span>
                                <?php else: ?>
                                    <span class="badge badge-info">ASSIGNED</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($a['attempt_status'] === 'In Progress'): ?>
                                    <a href="/exam-take.php?exam_id=<?= $a['exam_id'] ?>" class="btn btn-warning btn-sm fw-semibold">
                                        <i class="fa-solid fa-play me-1"></i>Continue Exam
                                    </a>
                                <?php elseif ($a['assignment_status'] === 'Completed'): ?>
                                    <a href="/results.php?exam_id=<?= $a['exam_id'] ?>" class="btn btn-outline-success btn-sm">
                                        <i class="fa-solid fa-square-poll-vertical me-1"></i>View Result
                                    </a>
                                <?php else: ?>
                                    <a href="/exam-instructions.php?exam_id=<?= $a['exam_id'] ?>" class="btn btn-primary btn-sm fw-semibold">
                                        <i class="fa-solid fa-right-to-bracket me-1"></i>Start Examination
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>
