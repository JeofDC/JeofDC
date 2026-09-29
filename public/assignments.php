<?php
// public/assignments.php - Exam Assignment Management (Single & Bulk Assignment)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requirePermission('exam.assign');

$pageTitle = 'Exam Assignments';
$activeNav = 'assignments';

$pdo = getDbConnection();

// Fetch Published Exams
$exams = $pdo->query("SELECT id, exam_code, title FROM exams WHERE status='Published' ORDER BY id DESC")->fetchAll();

// Fetch Candidates
$candidates = $pdo->query("
    SELECT u.id, u.username, u.first_name, u.last_name, u.email
    FROM users u
    JOIN user_roles ur ON ur.user_id = u.id
    JOIN roles r ON r.id = ur.role_id
    WHERE r.name = 'Candidate' AND u.status = 'active'
    ORDER BY u.first_name ASC
")->fetchAll();

// Fetch Recent Assignments
$assignments = $pdo->query("
    SELECT ea.id, ea.available_from, ea.available_until, ea.status, ea.created_at,
           e.title as exam_title, e.exam_code,
           u.first_name, u.last_name, u.username
    FROM exam_assignments ea
    JOIN exams e ON e.id = ea.exam_id
    JOIN users u ON u.id = ea.candidate_id
    ORDER BY ea.id DESC
")->fetchAll();

require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Examination Assignments</h3>
        <p class="text-muted mb-0">Assign examinations to individual candidates or bulk select</p>
    </div>
    <button class="btn btn-primary" onclick="openAssignModal()"><i class="fa-solid fa-user-plus me-2"></i>Assign Exam</button>
</div>

<!-- Assignments Table -->
<div class="card card-mega shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="fa-solid fa-clipboard-check me-2 text-success"></i>Current Assignments List
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Candidate</th>
                        <th>Exam Title</th>
                        <th>Available From</th>
                        <th>Available Until</th>
                        <th>Status</th>
                        <th>Assigned Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-folder-open display-6 d-block mb-2 text-secondary"></i>No exam assignments created yet.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $a): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= e($a['first_name'] . ' ' . $a['last_name']) ?></div>
                                <small class="text-muted">@<?= e($a['username']) ?></small>
                            </td>
                            <td>
                                <div class="fw-medium"><?= e($a['exam_title']) ?></div>
                                <small class="text-muted">Code: <code><?= e($a['exam_code']) ?></code></small>
                            </td>
                            <td><?= $a['available_from'] ? date('M d, Y H:i', strtotime($a['available_from'])) : 'Immediate' ?></td>
                            <td><?= $a['available_until'] ? date('M d, Y H:i', strtotime($a['available_until'])) : 'No Expiry' ?></td>
                            <td>
                                <?php if ($a['status'] === 'Completed'): ?>
                                    <span class="badge badge-success">COMPLETED</span>
                                <?php elseif ($a['status'] === 'Started'): ?>
                                    <span class="badge badge-warning">STARTED</span>
                                <?php else: ?>
                                    <span class="badge badge-info"><?= e($a['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Assign Exam Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-success"><i class="fa-solid fa-user-plus me-2"></i>Assign Examination</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignForm" onsubmit="submitAssignment(event)">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Published Exam *</label>
                        <select id="assignExam" class="form-select" required>
                            <option value="">Choose Examination...</option>
                            <?php foreach ($exams as $ex): ?>
                                <option value="<?= $ex['id'] ?>"><?= e($ex['title']) ?> (<?= e($ex['exam_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Candidate(s) * (Hold Ctrl/Cmd to select multiple)</label>
                        <select id="assignCandidates" class="form-select" multiple style="height: 180px;" required>
                            <?php foreach ($candidates as $cand): ?>
                                <option value="<?= $cand['id'] ?>"><?= e($cand['first_name'] . ' ' . $cand['last_name']) ?> (@<?= e($cand['username']) ?> - <?= e($cand['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select individual or multiple candidates for bulk assignment.</small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Available From</label>
                            <input type="datetime-local" id="assignFrom" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Available Until</label>
                            <input type="datetime-local" id="assignUntil" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="assignSubmitBtn" class="btn btn-success fw-bold"><i class="fa-solid fa-check me-1"></i>Confirm Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAssignModal() {
    document.getElementById('assignForm').reset();
    new bootstrap.Modal(document.getElementById('assignModal')).show();
}

async function submitAssignment(e) {
    e.preventDefault();
    const btn = document.getElementById('assignSubmitBtn');
    btn.disabled = true;
    const csrfToken = await getCsrfToken();

    const examId = document.getElementById('assignExam').value;
    const candSelect = document.getElementById('assignCandidates');
    const selectedCandIds = Array.from(candSelect.selectedOptions).map(opt => parseInt(opt.value));

    const fromVal = document.getElementById('assignFrom').value;
    const untilVal = document.getElementById('assignUntil').value;

    const payload = {
        csrf_token: csrfToken,
        exam_id: examId,
        candidate_ids: selectedCandIds,
        available_from: fromVal ? fromVal.replace('T', ' ') + ':00' : null,
        available_until: untilVal ? untilVal.replace('T', ' ') + ':00' : null
    };

    try {
        const res = await fetch('/api/exams/assign.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();

        if (json.success) {
            alert(json.message);
            window.location.reload();
        } else {
            alert(json.message);
        }
    } catch(err) {
        alert('Error assigning exam.');
    } finally {
        btn.disabled = false;
    }
}
</script>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>
