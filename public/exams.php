<?php
// public/exams.php - Examination Builder and Management View
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requirePermission('exams.view');

$pageTitle = 'Examinations';
$activeNav = 'exams';

$pdo = getDbConnection();
$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY display_order ASC")->fetchAll();

require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Examination Builder</h3>
        <p class="text-muted mb-0">Create, configure, publish, and manage aptitude examinations</p>
    </div>
    <?php if (hasPermission('exams.create')): ?>
        <button class="btn btn-primary" onclick="openExamModal()"><i class="fa-solid fa-plus me-2"></i>Create New Exam</button>
    <?php endif; ?>
</div>

<!-- Exam List Table -->
<div class="card card-mega shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="fa-solid fa-file-signature me-2 text-success"></i>All Examinations
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 140px;">Exam Code</th>
                        <th>Exam Title</th>
                        <th style="width: 100px;">Duration</th>
                        <th style="width: 120px;">Pass Threshold</th>
                        <th style="width: 120px;">Assignments</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 180px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="examTableBody">
                    <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading examinations...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create / Edit Exam Modal -->
<div class="modal fade" id="examModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-success" id="examModalTitle"><i class="fa-solid fa-file-signature me-2"></i>Exam Builder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="examForm" onsubmit="saveExam(event)">
                <div class="modal-body p-4">
                    <input type="hidden" id="examId">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Exam Code *</label>
                            <input type="text" id="examCode" class="form-control" required placeholder="e.g., EXAM-2026-01" style="text-transform: uppercase;">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Exam Title *</label>
                            <input type="text" id="examTitle" class="form-control" required placeholder="e.g., General Aptitude Assessment">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea id="examDesc" class="form-control" rows="2" placeholder="Brief summary of examination context..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Candidate Instructions</label>
                        <textarea id="examInstructions" class="form-control" rows="3" placeholder="Rules, guidelines, and guidelines displayed before starting..."></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Duration (Minutes) *</label>
                            <input type="number" id="examDuration" class="form-control" value="30" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Passing Score (%) *</label>
                            <input type="number" step="0.5" id="examPassing" class="form-control" value="70.0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Attempts *</label>
                            <input type="number" id="examMaxAttempts" class="form-control" value="1" min="1" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch pt-2">
                                <input class="form-check-input" type="checkbox" id="examRandomQ" checked>
                                <label class="form-check-label fw-semibold" for="examRandomQ">Randomize Question Order</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch pt-2">
                                <input class="form-check-input" type="checkbox" id="examRandomC" checked>
                                <label class="form-check-label fw-semibold" for="examRandomC">Randomize Choice Order</label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-layer-group me-2 text-primary"></i>Category Question Configuration</h6>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    <th style="width: 140px;">Question Count</th>
                                    <th style="width: 140px;">Points / Question</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $c): ?>
                                <tr>
                                    <td class="fw-bold">
                                        <?= e($c['name']) ?>
                                        <input type="hidden" class="cat-id-input" value="<?= $c['id'] ?>">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm cat-count-input" value="10" min="0">
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" class="form-control form-control-sm cat-pts-input" value="1.0" min="0.5">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="saveExamBtn" class="btn btn-success fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Save Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadExams);

async function loadExams() {
    const tbody = document.getElementById('examTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading examinations...</td></tr>';

    try {
        const res = await fetch('/api/exams/list.php');
        const json = await res.json();

        if (!json.success || !json.data || json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-folder-open display-6 d-block mb-2 text-secondary"></i>No examinations created yet.</td></tr>';
            return;
        }

        tbody.innerHTML = json.data.map(ex => `
            <tr>
                <td><code>${escapeHtml(ex.exam_code)}</code></td>
                <td>
                    <div class="fw-bold text-dark">${escapeHtml(ex.title)}</div>
                    <small class="text-muted">${escapeHtml(ex.description || '')}</small>
                </td>
                <td><i class="fa-regular fa-clock me-1 text-muted"></i>${ex.duration_minutes} mins</td>
                <td>${ex.passing_percentage}%</td>
                <td><span class="badge bg-light text-dark border">${ex.total_assignments} Assigned</span></td>
                <td>
                    ${ex.status === 'Published' ? '<span class="badge badge-success">Published</span>' : '<span class="badge badge-secondary">' + ex.status + '</span>'}
                </td>
                <td class="text-end">
                    ${ex.status === 'Draft' ? `<button class="btn btn-sm btn-outline-success me-1" onclick="publishExam(${ex.id})"><i class="fa-solid fa-globe me-1"></i>Publish</button>` : ''}
                    <button class="btn btn-sm btn-outline-primary" onclick="editExam(${ex.id})"><i class="fa-solid fa-pen"></i></button>
                </td>
            </tr>
        `).join('');

    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Error loading exams.</td></tr>';
    }
}

function openExamModal() {
    document.getElementById('examId').value = '';
    document.getElementById('examForm').reset();
    document.getElementById('examModalTitle').innerText = 'Create New Exam';
    new bootstrap.Modal(document.getElementById('examModal')).show();
}

async function editExam(id) {
    try {
        const res = await fetch(`/api/exams/list.php?id=${id}`);
        const json = await res.json();
        if (!json.success || !json.data) return alert('Failed to load exam details');

        const ex = json.data;
        document.getElementById('examId').value = ex.id;
        document.getElementById('examCode').value = ex.exam_code;
        document.getElementById('examTitle').value = ex.title;
        document.getElementById('examDesc').value = ex.description || '';
        document.getElementById('examInstructions').value = ex.instructions || '';
        document.getElementById('examDuration').value = ex.duration_minutes;
        document.getElementById('examPassing').value = ex.passing_percentage;
        document.getElementById('examMaxAttempts').value = ex.max_attempts;
        document.getElementById('examRandomQ').checked = ex.randomize_questions;
        document.getElementById('examRandomC').checked = ex.randomize_choices;

        // Set category configs
        if (ex.categories) {
            const catMap = {};
            ex.categories.forEach(c => catMap[c.category_id] = c);

            document.querySelectorAll('.cat-id-input').forEach((input, idx) => {
                const catId = input.value;
                const countInput = document.querySelectorAll('.cat-count-input')[idx];
                const ptsInput = document.querySelectorAll('.cat-pts-input')[idx];

                if (catMap[catId]) {
                    countInput.value = catMap[catId].question_count;
                    ptsInput.value = catMap[catId].points_per_question;
                }
            });
        }

        document.getElementById('examModalTitle').innerText = `Edit Exam: ${ex.title}`;
        new bootstrap.Modal(document.getElementById('examModal')).show();

    } catch(e) {
        alert('Error fetching exam.');
    }
}

async function saveExam(e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveExamBtn');
    saveBtn.disabled = true;
    const csrfToken = await getCsrfToken();

    // Gather categories config
    const categories = [];
    document.querySelectorAll('.cat-id-input').forEach((input, idx) => {
        const catId = input.value;
        const qCount = document.querySelectorAll('.cat-count-input')[idx].value;
        const pts = document.querySelectorAll('.cat-pts-input')[idx].value;
        categories.push({ category_id: catId, question_count: qCount, points_per_question: pts });
    });

    const payload = {
        csrf_token: csrfToken,
        id: document.getElementById('examId').value,
        exam_code: document.getElementById('examCode').value,
        title: document.getElementById('examTitle').value,
        description: document.getElementById('examDesc').value,
        instructions: document.getElementById('examInstructions').value,
        duration_minutes: document.getElementById('examDuration').value,
        passing_percentage: document.getElementById('examPassing').value,
        max_attempts: document.getElementById('examMaxAttempts').value,
        randomize_questions: document.getElementById('examRandomQ').checked,
        randomize_choices: document.getElementById('examRandomC').checked,
        categories: categories
    };

    try {
        const res = await fetch('/api/exams/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();

        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('examModal')).hide();
            loadExams();
        } else {
            alert(json.message);
        }
    } catch(err) {
        alert('Error saving exam.');
    } finally {
        saveBtn.disabled = false;
    }
}

async function publishExam(id) {
    if (!confirm("Are you sure you want to publish this examination? Candidates will be able to take it once assigned.")) return;
    const csrfToken = await getCsrfToken();

    try {
        const res = await fetch('/api/exams/publish.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, csrf_token: csrfToken })
        });
        const json = await res.json();

        if (json.success) {
            loadExams();
        } else {
            alert(json.message);
        }
    } catch(e) {
        alert('Error publishing exam.');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>
