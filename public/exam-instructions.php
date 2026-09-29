<?php
// public/exam-instructions.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requireLogin();

$examId = !empty($_GET['exam_id']) ? intval($_GET['exam_id']) : null;
if (!$examId) {
    header('Location: /candidate-dashboard.php');
    exit();
}

$pdo = getDbConnection();

// Fetch Exam & Categories
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND status = 'Published'");
$stmt->execute([$examId]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Examination not found or not currently available.");
}

$stmtCats = $pdo->prepare("
    SELECT ec.*, c.name as category_name, c.code as category_code, c.description as category_desc
    FROM exam_categories ec
    JOIN categories c ON c.id = ec.category_id
    WHERE ec.exam_id = ?
    ORDER BY ec.display_order ASC
");
$stmtCats->execute([$examId]);
$categories = $stmtCats->fetchAll();

$totalQuestions = 0;
foreach ($categories as $c) {
    $totalQuestions += intval($c['question_count']);
}

$pageTitle = "Exam Instructions - " . $exam['title'];
$hideSidebar = true;
require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-lg-8">
        <div class="card card-mega shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge badge-info mb-1"><code><?= e($exam['exam_code']) ?></code></span>
                        <h4 class="fw-bold mb-0 text-success"><?= e($exam['title']) ?></h4>
                    </div>
                    <a href="/candidate-dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>

            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded text-center border">
                            <span class="text-muted small d-block">Duration</span>
                            <strong class="fs-5 text-dark"><i class="fa-regular fa-clock me-1 text-success"></i><?= e($exam['duration_minutes']) ?> Mins</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded text-center border">
                            <span class="text-muted small d-block">Total Questions</span>
                            <strong class="fs-5 text-dark"><i class="fa-solid fa-list-check me-1 text-primary"></i><?= $totalQuestions ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded text-center border">
                            <span class="text-muted small d-block">Passing Score</span>
                            <strong class="fs-5 text-dark"><i class="fa-solid fa-trophy me-1 text-warning"></i><?= e($exam['passing_percentage']) ?>%</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded text-center border">
                            <span class="text-muted small d-block">Max Attempts</span>
                            <strong class="fs-5 text-dark"><i class="fa-solid fa-rotate me-1 text-info"></i><?= e($exam['max_attempts']) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2"><i class="fa-solid fa-layer-group me-2 text-success"></i>Assessment Breakdown</h5>
                    <div class="list-group">
                        <?php foreach ($categories as $cat): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark"><?= e($cat['category_name']) ?></div>
                                <small class="text-muted"><?= e($cat['category_desc']) ?></small>
                            </div>
                            <span class="badge bg-secondary rounded-pill"><?= e($cat['question_count']) ?> Questions</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <h5 class="fw-bold text-dark border-bottom pb-2"><i class="fa-solid fa-circle-info me-2 text-primary"></i>Important Instructions</h5>
                    <div class="p-3 bg-light rounded border text-muted">
                        <?= nl2br(e($exam['instructions'])) ?>
                    </div>
                </div>

                <div class="form-check p-3 bg-light rounded border mb-4">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="confirmCheckbox" onchange="toggleStartBtn()">
                    <label class="form-check-label fw-semibold text-dark" for="confirmCheckbox">
                        I have read and understood all examination rules and instructions. I am ready to begin.
                    </label>
                </div>

                <div class="text-end">
                    <button id="startExamBtn" class="btn btn-primary btn-lg px-4" disabled onclick="confirmStartModal()">
                        <i class="fa-solid fa-play me-2"></i>Begin Examination
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Start Confirmation Modal -->
<div class="modal fade" id="startModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-success"><i class="fa-solid fa-play me-2"></i>Confirm Start</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-content-body p-4 text-center">
                <p class="fs-5">Are you ready to start <strong><?= e($exam['title']) ?></strong>?</p>
                <p class="text-muted small">Once started, your countdown timer of <strong><?= e($exam['duration_minutes']) ?> minutes</strong> will begin immediately.</p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success fw-bold" onclick="startExamNow()"><i class="fa-solid fa-check me-1"></i>Start Now</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleStartBtn() {
    const chk = document.getElementById('confirmCheckbox');
    document.getElementById('startExamBtn').disabled = !chk.checked;
}

function confirmStartModal() {
    const modal = new bootstrap.Modal(document.getElementById('startModal'));
    modal.show();
}

async function startExamNow() {
    const csrfToken = await getCsrfToken();
    try {
        const res = await fetch('/api/attempts/start.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ exam_id: <?= $examId ?>, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = `/exam-take.php?exam_id=<?= $examId ?>`;
        } else {
            alert(data.message);
        }
    } catch(e) {
        alert("Error starting exam. Please try again.");
    }
}
</script>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>
