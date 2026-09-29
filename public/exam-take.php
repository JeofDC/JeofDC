<?php
// public/exam-take.php - Distraction-Free Candidate Examination Interface
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Sanitize.php';

requireLogin();

$examId = !empty($_GET['exam_id']) ? intval($_GET['exam_id']) : null;
$currentUser = getCurrentUser();

$pageTitle = "Examination Session";
$hideSidebar = true;
require_once __DIR__ . '/../app/Views/header.php';
?>

<style>
.exam-container { max-width: 1100px; margin: 0 auto; }
.timer-badge { font-size: 1.25rem; font-weight: 700; background: #28a745; color: #fff; padding: 0.5rem 1rem; border-radius: 8px; }
.timer-warning { background: #dc3545 !important; animation: pulse 1s infinite; }
@keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
.q-nav-btn { width: 38px; height: 38px; font-weight: 600; font-size: 0.85rem; padding: 0; display: inline-flex; align-items: center; justify-content: center; margin: 3px; border-radius: 6px; }
.q-nav-answered { background-color: #28a745 !important; color: #fff !important; border-color: #28a745 !important; }
.q-nav-flagged { background-color: #ffc107 !important; color: #000 !important; border-color: #ffc107 !important; }
.q-nav-current { border: 2px solid #000 !important; font-weight: 800; }
.choice-box { border: 1.5px solid #dee2e6; border-radius: 8px; padding: 0.85rem 1.2rem; cursor: pointer; transition: all 0.2s ease; margin-bottom: 0.75rem; display: flex; align-items: center; }
.choice-box:hover { background-color: #f8f9fa; border-color: #28a745; }
.choice-box.selected { border-color: #28a745; background-color: rgba(40, 167, 69, 0.08); font-weight: 600; }
.choice-radio { margin-right: 1rem; transform: scale(1.2); }
</style>

<div class="exam-container py-3">
    <!-- Top Header Bar -->
    <div class="card card-mega shadow-sm mb-3">
        <div class="card-body py-3 px-4 d-flex align-items-center justify-content-between">
            <div>
                <span class="badge badge-info me-2" id="examCategoryBadge">Category: Loading...</span>
                <h4 class="fw-bold mb-0 text-success d-inline" id="examTitle">Loading Examination...</h4>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small d-none d-md-inline"><i class="fa-solid fa-user me-1"></i><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></span>
                <div id="timerContainer" class="timer-badge">
                    <i class="fa-regular fa-clock me-1"></i><span id="timerText">--:--</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Question Box -->
        <div class="col-lg-8 mb-3">
            <div class="card card-mega shadow-sm">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-muted" id="questionHeader">Question 1 of --</span>
                    <button id="flagBtn" class="btn btn-sm btn-outline-warning" onclick="toggleFlagCurrentQuestion()">
                        <i class="fa-solid fa-flag me-1"></i>Flag for Review
                    </button>
                </div>
                <div class="card-body p-4">
                    <div id="questionText" class="fs-5 fw-medium text-dark mb-3">
                        Loading question...
                    </div>

                    <div id="questionImageContainer" class="mb-4 text-center d-none">
                        <img id="questionImage" src="" class="img-fluid rounded border shadow-sm" style="max-height: 280px;" alt="Question Diagram">
                    </div>

                    <div id="choicesContainer">
                        <!-- Choices injected here -->
                    </div>
                </div>

                <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
                    <button id="prevBtn" class="btn btn-outline-secondary" onclick="navigateQuestion(-1)">
                        <i class="fa-solid fa-arrow-left me-1"></i>Previous
                    </button>
                    <div>
                        <span id="saveStatus" class="small text-muted me-3"><i class="fa-solid fa-check-circle text-success me-1"></i>Auto-saved</span>
                        <button class="btn btn-success fw-bold" onclick="confirmSubmitModal()">
                            <i class="fa-solid fa-paper-plane me-1"></i>Submit Exam
                        </button>
                    </div>
                    <button id="nextBtn" class="btn btn-primary" onclick="navigateQuestion(1)">
                        Next<i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Side Question Navigator -->
        <div class="col-lg-4 mb-3">
            <div class="card card-mega shadow-sm">
                <div class="card-header bg-white py-3 fw-bold">
                    <i class="fa-solid fa-compass me-2 text-primary"></i>Question Navigator
                </div>
                <div class="card-body p-3">
                    <div id="navigatorGrid" class="d-flex flex-wrap align-content-start">
                        <!-- Navigator buttons -->
                    </div>

                    <hr class="my-3">

                    <div class="d-flex flex-wrap gap-2 small text-muted justify-content-between">
                        <div><span class="q-nav-btn btn btn-outline-secondary btn-sm me-1" style="width:20px;height:20px;"></span> Unanswered</div>
                        <div><span class="q-nav-btn btn btn-success btn-sm me-1 q-nav-answered" style="width:20px;height:20px;"></span> Answered</div>
                        <div><span class="q-nav-btn btn btn-warning btn-sm me-1 q-nav-flagged" style="width:20px;height:20px;"></span> Flagged</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Submit Confirmation Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-success"><i class="fa-solid fa-paper-plane me-2"></i>Submit Examination</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="fs-5 fw-semibold">Are you sure you want to submit your examination?</p>
                <div class="p-3 bg-light rounded border my-3">
                    <div class="d-flex justify-content-around">
                        <div><span class="text-muted d-block small">Answered</span><strong id="modalAnswered" class="fs-4 text-success">0</strong></div>
                        <div><span class="text-muted d-block small">Unanswered</span><strong id="modalUnanswered" class="fs-4 text-danger">0</strong></div>
                        <div><span class="text-muted d-block small">Flagged</span><strong id="modalFlagged" class="fs-4 text-warning">0</strong></div>
                    </div>
                </div>
                <p class="text-muted small mb-0">Once submitted, your answers will be locked and automatically scored.</p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Continue Exam</button>
                <button type="button" id="finalSubmitBtn" class="btn btn-success fw-bold" onclick="submitExamNow()">Submit Exam Now</button>
            </div>
        </div>
    </div>
</div>

<script>
let attemptData = null;
let questions = [];
let currentIndex = 0;
let remainingSeconds = 0;
let timerInterval = null;
let csrfToken = '';

document.addEventListener('DOMContentLoaded', async () => {
    csrfToken = await getCsrfToken();
    await loadAttempt();

    // Log anti-cheating browser visibility change
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && attemptData) {
            logAttemptEvent('TAB_HIDDEN', 'User switched tab or minimized window');
        } else if (attemptData) {
            logAttemptEvent('TAB_VISIBLE', 'User returned to exam window');
        }
    });
});

async function loadAttempt() {
    try {
        const res = await fetch(`/api/attempts/current.php?exam_id=<?= $examId ?>`);
        const data = await res.json();

        if (!data.success) {
            alert(data.message || 'Error loading examination.');
            window.location.href = '/candidate-dashboard.php';
            return;
        }

        if (data.redirect) {
            window.location.href = data.redirect;
            return;
        }

        attemptData = data.attempt;
        questions = data.questions;
        remainingSeconds = attemptData.remaining_seconds;

        document.getElementById('examTitle').innerText = attemptData.exam_title;
        startTimer();
        renderNavigator();
        renderQuestion(0);

    } catch(e) {
        alert('Network connection error while loading exam.');
    }
}

function startTimer() {
    clearInterval(timerInterval);
    updateTimerDisplay();

    timerInterval = setInterval(() => {
        remainingSeconds--;
        updateTimerDisplay();

        if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            alert("Time has expired! Submitting your examination automatically.");
            submitExamNow();
        }
    }, 1000);
}

function updateTimerDisplay() {
    const mins = Math.floor(remainingSeconds / 60);
    const secs = remainingSeconds % 60;
    const displayStr = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    const timerElem = document.getElementById('timerText');
    const timerBox = document.getElementById('timerContainer');
    timerElem.innerText = displayStr;

    if (remainingSeconds <= 300) { // 5 minutes warning
        timerBox.classList.add('timer-warning');
    }
}

function renderQuestion(index) {
    if (index < 0 || index >= questions.length) return;
    currentIndex = index;
    const q = questions[currentIndex];

    document.getElementById('questionHeader').innerText = `Question ${currentIndex + 1} of ${questions.length}`;
    document.getElementById('examCategoryBadge').innerText = `Category: ${q.category_name}`;
    document.getElementById('questionText').innerText = q.question_text;

    const imgContainer = document.getElementById('questionImageContainer');
    const imgElem = document.getElementById('questionImage');
    if (q.question_image) {
        imgElem.src = q.question_image;
        imgContainer.classList.remove('d-none');
    } else {
        imgContainer.classList.add('d-none');
    }

    // Flag button update
    const flagBtn = document.getElementById('flagBtn');
    if (q.is_flagged) {
        flagBtn.className = 'btn btn-sm btn-warning text-dark fw-bold';
        flagBtn.innerHTML = '<i class="fa-solid fa-flag me-1"></i>Flagged';
    } else {
        flagBtn.className = 'btn btn-sm btn-outline-warning';
        flagBtn.innerHTML = '<i class="fa-regular fa-flag me-1"></i>Flag for Review';
    }

    // Render choices
    const choicesBox = document.getElementById('choicesContainer');
    choicesBox.innerHTML = '';

    q.choices.forEach(ch => {
        const isSelected = (q.selected_choice_id && parseInt(q.selected_choice_id) === parseInt(ch.id));
        const div = document.createElement('div');
        div.className = `choice-box ${isSelected ? 'selected' : ''}`;
        div.onclick = () => selectChoice(ch.id);

        div.innerHTML = `
            <input type="radio" class="choice-radio" name="choice_radio" ${isSelected ? 'checked' : ''}>
            <span class="fw-bold me-2">${ch.choice_label}.</span>
            <span>${ch.choice_text || ''}</span>
            ${ch.choice_image ? `<br><img src="${ch.choice_image}" class="img-fluid mt-2" style="max-height:100px;">` : ''}
        `;
        choicesBox.appendChild(div);
    });

    // Update Nav buttons state
    document.getElementById('prevBtn').disabled = (currentIndex === 0);
    document.getElementById('nextBtn').disabled = (currentIndex === questions.length - 1);

    renderNavigator();
}

async function selectChoice(choiceId) {
    const q = questions[currentIndex];
    q.selected_choice_id = choiceId;
    renderQuestion(currentIndex);

    // Save choice via AJAX
    document.getElementById('saveStatus').innerHTML = '<i class="fa-solid fa-spinner fa-spin text-warning me-1"></i>Saving...';

    try {
        const res = await fetch('/api/attempts/save-answer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attempt_id: attemptData.id,
                attempt_question_id: q.attempt_question_id,
                selected_choice_id: choiceId,
                csrf_token: csrfToken
            })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('saveStatus').innerHTML = '<i class="fa-solid fa-check-circle text-success me-1"></i>Auto-saved';
        } else {
            document.getElementById('saveStatus').innerHTML = '<i class="fa-solid fa-triangle-exclamation text-danger me-1"></i>Save error';
        }
    } catch(e) {
        document.getElementById('saveStatus').innerHTML = '<i class="fa-solid fa-triangle-exclamation text-danger me-1"></i>Save error';
    }
}

async function toggleFlagCurrentQuestion() {
    const q = questions[currentIndex];
    q.is_flagged = !q.is_flagged;
    renderQuestion(currentIndex);

    try {
        await fetch('/api/attempts/save-answer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attempt_id: attemptData.id,
                attempt_question_id: q.attempt_question_id,
                is_flagged: q.is_flagged,
                csrf_token: csrfToken
            })
        });
    } catch(e) {}
}

function navigateQuestion(delta) {
    renderQuestion(currentIndex + delta);
}

function renderNavigator() {
    const grid = document.getElementById('navigatorGrid');
    grid.innerHTML = '';

    questions.forEach((q, idx) => {
        const btn = document.createElement('button');
        let classes = 'q-nav-btn btn btn-sm ';

        if (idx === currentIndex) {
            classes += 'q-nav-current ';
        }

        if (q.is_flagged) {
            classes += 'q-nav-flagged';
        } else if (q.selected_choice_id) {
            classes += 'q-nav-answered';
        } else {
            classes += 'btn-outline-secondary';
        }

        btn.className = classes;
        btn.innerText = idx + 1;
        btn.onclick = () => renderQuestion(idx);
        grid.appendChild(btn);
    });
}

function confirmSubmitModal() {
    let answered = 0;
    let flagged = 0;
    questions.forEach(q => {
        if (q.selected_choice_id) answered++;
        if (q.is_flagged) flagged++;
    });

    document.getElementById('modalAnswered').innerText = answered;
    document.getElementById('modalUnanswered').innerText = questions.length - answered;
    document.getElementById('modalFlagged').innerText = flagged;

    const modal = new bootstrap.Modal(document.getElementById('submitModal'));
    modal.show();
}

async function submitExamNow() {
    const submitBtn = document.getElementById('finalSubmitBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Submitting...';
    }

    try {
        const res = await fetch('/api/attempts/submit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attempt_id: attemptData.id,
                csrf_token: csrfToken
            })
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = data.redirect;
        } else {
            alert(data.message || 'Error submitting exam');
            if (submitBtn) submitBtn.disabled = false;
        }
    } catch(e) {
        alert('Network connection error while submitting exam.');
        if (submitBtn) submitBtn.disabled = false;
    }
}

async function logAttemptEvent(type, details) {
    if (!attemptData) return;
    try {
        await fetch('/api/attempts/log-event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attempt_id: attemptData.id,
                event_type: type,
                event_data: details
            })
        });
    } catch(e) {}
}
</script>

<?php require_once __DIR__ . '/../app/Views/footer.php'; ?>
