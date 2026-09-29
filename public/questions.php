<?php
// public/questions.php - Question Bank Management View
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requirePermission('questions.view');

$pageTitle = 'Question Bank';
$activeNav = 'questions';

$pdo = getDbConnection();
$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY display_order ASC")->fetchAll();

require_once __DIR__ . '/../app/Views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Question Bank Repository</h3>
        <p class="text-muted mb-0">Manage Abstract, Verbal, and Numerical reasoning question items</p>
    </div>
    <?php if (hasPermission('questions.create')): ?>
        <button class="btn btn-primary" onclick="openQuestionModal()"><i class="fa-solid fa-plus me-2"></i>Create New Question</button>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="card card-mega shadow-sm mb-4">
    <div class="card-body p-3">
        <form id="filterForm" class="row g-3" onsubmit="event.preventDefault(); loadQuestions();">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search question text or explanation...">
                </div>
            </div>
            <div class="col-md-3">
                <select id="categorySelect" class="form-select" onchange="loadQuestions()">
                    <option value="">All Aptitude Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="difficultySelect" class="form-select" onchange="loadQuestions()">
                    <option value="">All Difficulties</option>
                    <option value="Easy">Easy</option>
                    <option value="Medium">Medium</option>
                    <option value="Hard">Hard</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-semibold"><i class="fa-solid fa-filter me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Questions Table -->
<div class="card card-mega shadow-sm">
    <div class="card-header bg-white fw-bold">
        <i class="fa-solid fa-database me-2 text-success"></i>Question Bank Items
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 180px;">Category</th>
                        <th>Question Snippet</th>
                        <th style="width: 100px;">Type</th>
                        <th style="width: 100px;">Difficulty</th>
                        <th style="width: 80px;">Points</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 120px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="questionsTableBody">
                    <tr><td colspan="8" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading question bank...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Question Modal -->
<div class="modal fade" id="questionModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-success" id="qModalTitle"><i class="fa-solid fa-pen-to-square me-2"></i>Question Editor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="qForm" onsubmit="saveQuestion(event)" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" id="qId">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Aptitude Category *</label>
                            <select id="qCategory" class="form-select" required>
                                <option value="">Select Category...</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Difficulty *</label>
                            <select id="qDifficulty" class="form-select" required>
                                <option value="Easy">Easy</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="Hard">Hard</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Points *</label>
                            <input type="number" step="0.5" id="qPoints" class="form-control" value="1.0" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Question Text / Prompt *</label>
                        <textarea id="qText" class="form-control" rows="3" placeholder="Enter question statement or prompt..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Question Image / Diagram (Optional)</label>
                        <input type="file" id="qImage" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div id="qImagePreview" class="mt-2 text-muted small"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Explanation (Optional)</label>
                        <textarea id="qExplanation" class="form-control" rows="2" placeholder="Explanation for correct answer..."></textarea>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i>Answer Choices *</h6>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="addChoiceRow()">
                            <i class="fa-solid fa-plus me-1"></i>Add Choice
                        </button>
                    </div>

                    <div id="choicesContainer">
                        <!-- Dynamic choices -->
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="saveQBtn" class="btn btn-success fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i>Save Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', loadQuestions);

async function loadQuestions() {
    const tbody = document.getElementById('questionsTableBody');
    const search = document.getElementById('searchInput').value;
    const cat = document.getElementById('categorySelect').value;
    const diff = document.getElementById('difficultySelect').value;

    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading question bank...</td></tr>';

    try {
        const res = await fetch(`/api/questions/list.php?search=${encodeURIComponent(search)}&category_id=${cat}&difficulty=${diff}`);
        const json = await res.json();

        if (!json.success || !json.data || json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fa-solid fa-folder-open display-6 d-block mb-2 text-secondary"></i>No questions found matching criteria.</td></tr>';
            return;
        }

        tbody.innerHTML = json.data.map(q => `
            <tr>
                <td><code>#${q.id}</code></td>
                <td><span class="badge badge-info">${escapeHtml(q.category_name)}</span></td>
                <td>
                    <div class="fw-medium text-dark text-truncate" style="max-width: 320px;">${escapeHtml(q.question_text)}</div>
                    ${q.question_image ? '<small class="text-success"><i class="fa-solid fa-image me-1"></i>Has Diagram</small>' : ''}
                </td>
                <td><span class="badge bg-light text-dark border">${q.question_type}</span></td>
                <td>
                    <span class="badge ${q.difficulty === 'Easy' ? 'badge-success' : (q.difficulty === 'Medium' ? 'badge-warning' : 'badge-danger')}">
                        ${q.difficulty}
                    </span>
                </td>
                <td>${q.points}</td>
                <td><span class="badge ${q.status === 'active' ? 'badge-success' : 'badge-secondary'}">${q.status}</span></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editQuestion(${q.id})"><i class="fa-solid fa-pen"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteQuestion(${q.id})"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>
        `).join('');

    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Error loading questions.</td></tr>';
    }
}

function openQuestionModal() {
    document.getElementById('qId').value = '';
    document.getElementById('qForm').reset();
    document.getElementById('qModalTitle').innerText = 'Create New Question';
    document.getElementById('qImagePreview').innerHTML = '';

    const container = document.getElementById('choicesContainer');
    container.innerHTML = '';

    // Add 4 default choice rows
    ['A', 'B', 'C', 'D'].forEach((label, idx) => {
        addChoiceRow(label, '', idx === 0);
    });

    const modal = new bootstrap.Modal(document.getElementById('questionModal'));
    modal.show();
}

function addChoiceRow(label = '', text = '', isCorrect = false) {
    const container = document.getElementById('choicesContainer');
    const index = container.children.length + 1;
    if (!label) label = String.fromCharCode(64 + index);

    const div = document.createElement('div');
    div.className = 'input-group mb-2 choice-row';
    div.innerHTML = `
        <span class="input-group-text bg-light fw-bold label-span">${label}</span>
        <div class="input-group-text bg-light">
            <input class="form-check-input mt-0 correct-radio" type="radio" name="correct_choice" ${isCorrect ? 'checked' : ''} required>
            <span class="ms-1 small fw-semibold text-success">Correct</span>
        </div>
        <input type="text" class="form-control choice-text" placeholder="Choice option text..." value="${escapeHtml(text)}" required>
        <button type="button" class="btn btn-outline-danger" onclick="this.closest('.choice-row').remove()"><i class="fa-solid fa-xmark"></i></button>
    `;
    container.appendChild(div);
}

async function editQuestion(id) {
    try {
        const res = await fetch(`/api/questions/list.php?id=${id}`);
        const json = await res.json();
        if (!json.success || !json.data) return alert('Failed to fetch question details');

        const q = json.data;
        document.getElementById('qId').value = q.id;
        document.getElementById('qCategory').value = q.category_id;
        document.getElementById('qDifficulty').value = q.difficulty;
        document.getElementById('qPoints').value = q.points;
        document.getElementById('qText').value = q.question_text;
        document.getElementById('qExplanation').value = q.explanation || '';
        document.getElementById('qModalTitle').innerText = `Edit Question #${q.id}`;

        if (q.question_image) {
            document.getElementById('qImagePreview').innerHTML = `<img src="${q.question_image}" height="80" class="rounded border">`;
        } else {
            document.getElementById('qImagePreview').innerHTML = '';
        }

        const container = document.getElementById('choicesContainer');
        container.innerHTML = '';

        if (q.choices && q.choices.length > 0) {
            q.choices.forEach(ch => {
                addChoiceRow(ch.choice_label, ch.choice_text, ch.is_correct);
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('questionModal'));
        modal.show();

    } catch(e) {
        alert('Error loading question details.');
    }
}

async function saveQuestion(e) {
    e.preventDefault();
    const saveBtn = document.getElementById('saveQBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Saving...';

    const csrfToken = await getCsrfToken();
    const formData = new FormData();

    formData.append('csrf_token', csrfToken);
    formData.append('id', document.getElementById('qId').value);
    formData.append('category_id', document.getElementById('qCategory').value);
    formData.append('difficulty', document.getElementById('qDifficulty').value);
    formData.append('points', document.getElementById('qPoints').value);
    formData.append('question_text', document.getElementById('qText').value);
    formData.append('explanation', document.getElementById('qExplanation').value);

    const imgFile = document.getElementById('qImage').files[0];
    if (imgFile) {
        formData.append('question_image', imgFile);
    }

    // Collect choices
    const rows = document.querySelectorAll('.choice-row');
    const choices = [];
    rows.forEach((row, idx) => {
        const label = row.querySelector('.label-span').innerText;
        const text = row.querySelector('.choice-text').value;
        const isCorrect = row.querySelector('.correct-radio').checked;
        choices.push({ choice_label: label, choice_text: text, is_correct: isCorrect });
    });

    formData.append('choices', JSON.stringify(choices));

    try {
        const res = await fetch('/api/questions/save.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('questionModal')).hide();
            loadQuestions();
        } else {
            alert(json.message || 'Error saving question');
        }
    } catch(err) {
        alert('Error submitting request.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i>Save Question';
    }
}

async function deleteQuestion(id) {
    if (!confirm(`Are you sure you want to deactivate question #${id}?`)) return;
    const csrfToken = await getCsrfToken();

    try {
        const res = await fetch('/api/questions/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, csrf_token: csrfToken })
        });
        const json = await res.json();
        if (json.success) {
            loadQuestions();
        } else {
            alert(json.message);
        }
    } catch(e) {
        alert('Error deactivating question.');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>
