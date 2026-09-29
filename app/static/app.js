// Applicant Aptitude Exam Hub - Frontend Application Engine

let state = {
    currentUser: null,
    allUsers: [],
    activeView: 'dashboard',

    // Candidate Test Runner State
    testRunner: {
        attemptId: null,
        examTitle: '',
        instructions: '',
        durationMinutes: 0,
        remainingSeconds: 0,
        totalQuestions: 0,
        questions: [],
        savedAnswers: {}, // qid -> {selected_choice, numeric_answer}
        markedForReview: new Set(),
        currentIndex: 0,
        timerInterval: null
    },

    // Chart instances
    charts: {}
};

// Global Initialization
document.addEventListener('DOMContentLoaded', async () => {
    await loadUsers();
    setupEventListeners();
    routeView();
});

async function loadUsers() {
    try {
        const [meRes, usersRes] = await Promise.all([
            fetch('/api/users/me'),
            fetch('/api/users')
        ]);

        state.currentUser = await meRes.json();
        state.allUsers = await usersRes.json();

        updateUserBar();
        renderNav();
    } catch (err) {
        showToast('Error initializing user session', 'error');
    }
}

function updateUserBar() {
    const userSelect = document.getElementById('user-switcher');
    if (!userSelect) return;

    userSelect.innerHTML = state.allUsers.map(u => `
        <option value="${u.id}" ${u.id === state.currentUser.id ? 'selected' : ''}>
            ${u.full_name} (${u.role.toUpperCase()})
        </option>
    `).join('');

    document.getElementById('current-user-name').innerText = state.currentUser.full_name;
    document.getElementById('current-user-role').innerText = state.currentUser.role;
}

function renderNav() {
    const navMenu = document.getElementById('nav-menu');
    const mobileNav = document.getElementById('mobile-nav');
    if (!navMenu) return;

    let links = [];
    if (state.currentUser.role === 'admin') {
        links = [
            { id: 'admin-analytics', label: 'Analytics', icon: 'fa-chart-pie' },
            { id: 'admin-applicants', label: 'Applicants', icon: 'fa-users' },
            { id: 'admin-exams', label: 'Exam Builder', icon: 'fa-layer-group' },
            { id: 'admin-questions', label: 'Question Bank', icon: 'fa-database' },
            { id: 'admin-results', label: 'Results', icon: 'fa-square-poll-vertical' },
        ];
    } else {
        links = [
            { id: 'applicant-dashboard', label: 'My Dashboard', icon: 'fa-house' },
            { id: 'applicant-assessments', label: 'Assigned Exams', icon: 'fa-file-signature' },
            { id: 'applicant-results', label: 'My Results', icon: 'fa-award' },
        ];
    }

    navMenu.innerHTML = links.map(l => `
        <button onclick="navigateTo('${l.id}')" class="nav-btn px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 flex items-center space-x-2 transition ${state.activeView === l.id ? 'bg-slate-800 text-white font-semibold border-b-2 border-blue-500' : ''}">
            <i class="fa-solid ${l.icon}"></i>
            <span>${l.label}</span>
        </button>
    `).join('');

    if (mobileNav) {
        mobileNav.innerHTML = links.map(l => `
            <button onclick="navigateTo('${l.id}')" class="px-3 py-1.5 rounded-full text-xs font-medium border ${state.activeView === l.id ? 'bg-blue-600 text-white border-blue-500' : 'bg-slate-800 text-slate-300 border-slate-700'} whitespace-nowrap">
                <i class="fa-solid ${l.icon} mr-1"></i>${l.label}
            </button>
        `).join('');
    }
}

function setupEventListeners() {
    document.getElementById('user-switcher').addEventListener('change', async (e) => {
        const uid = parseInt(e.target.value);
        try {
            const res = await fetch('/api/auth/switch-user', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: uid })
            });
            const data = await res.json();
            state.currentUser = data.user;
            showToast(`Switched to ${data.user.full_name}`, 'success');

            updateUserBar();

            // Set default view by role
            state.activeView = (state.currentUser.role === 'admin') ? 'admin-analytics' : 'applicant-dashboard';
            renderNav();
            routeView();
        } catch (err) {
            showToast('Failed to switch user', 'error');
        }
    });
}

function navigateTo(viewId) {
    // Clear runner timer if navigating away from runner
    if (state.activeView === 'candidate-runner' && viewId !== 'candidate-runner') {
        if (!confirm('Navigating away will leave your active exam session running in the background. Continue?')) {
            return;
        }
        clearInterval(state.testRunner.timerInterval);
    }
    state.activeView = viewId;
    renderNav();
    routeView();
}

function routeView() {
    const container = document.getElementById('app-container');
    container.innerHTML = '';

    // Route guards
    if (state.currentUser.role === 'admin' && state.activeView.startsWith('applicant-') && state.activeView !== 'applicant-results') {
        state.activeView = 'admin-analytics';
        renderNav();
    }
    if (state.currentUser.role === 'applicant' && state.activeView.startsWith('admin-')) {
        state.activeView = 'applicant-dashboard';
        renderNav();
    }

    switch (state.activeView) {
        case 'applicant-dashboard':
        case 'applicant-assessments':
            renderApplicantDashboard(container);
            break;
        case 'candidate-runner':
            renderCandidateRunnerView(container);
            break;
        case 'candidate-result':
            renderCandidateResultView(container);
            break;
        case 'applicant-results':
            renderApplicantResultsList(container);
            break;
        case 'admin-analytics':
            renderAdminAnalyticsView(container);
            break;
        case 'admin-applicants':
            renderAdminApplicantsView(container);
            break;
        case 'admin-exams':
            renderAdminExamsView(container);
            break;
        case 'admin-questions':
            renderAdminQuestionsView(container);
            break;
        case 'admin-results':
            renderAdminResultsView(container);
            break;
        default:
            renderApplicantDashboard(container);
            break;
    }
}


// =========================================================================
// APPLICANT VIEWS
// =========================================================================

async function renderApplicantDashboard(container) {
    container.innerHTML = `
        <div class="space-y-6">
            <!-- Welcome Header -->
            <div class="bg-gradient-to-r from-navy-900 to-slate-800 rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
                <div>
                    <h1 class="text-2xl font-bold">Welcome back, ${state.currentUser.full_name}!</h1>
                    <p class="text-slate-300 text-sm mt-1">Select an assigned pre-employment aptitude assessment below or enter an access code.</p>
                </div>
                <!-- Direct Code Access Card -->
                <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-700/80 flex items-center space-x-2 w-full md:w-auto">
                    <input type="text" id="access-code-input" placeholder="Enter Access Code e.g. EVAL-JANE-2024" class="bg-slate-800 text-white text-xs px-3 py-2 rounded-lg border border-slate-600 focus:outline-none focus:border-blue-500 uppercase tracking-wider w-full md:w-64">
                    <button onclick="launchByAccessCode()" class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition whitespace-nowrap">
                        <i class="fa-solid fa-play mr-1"></i>Start
                    </button>
                </div>
            </div>

            <!-- Assigned Assessments List -->
            <div>
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-slate-800"><i class="fa-solid fa-list-check text-blue-600 mr-2"></i>My Assigned Assessments</h2>
                </div>
                <div id="assigned-cards-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div class="col-span-full py-8 text-center text-slate-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading assigned assessments...</div>
                </div>
            </div>
        </div>
    `;

    try {
        const res = await fetch(`/api/assignments?applicant_id=${state.currentUser.id}`);
        const assignments = await res.json();
        const grid = document.getElementById('assigned-cards-grid');

        if (!assignments || assignments.length === 0) {
            grid.innerHTML = `
                <div class="col-span-full bg-white rounded-xl p-8 text-center border border-slate-200">
                    <i class="fa-solid fa-clipboard-check text-4xl text-slate-300 mb-3"></i>
                    <h3 class="text-slate-700 font-semibold">No assigned assessments found</h3>
                    <p class="text-slate-500 text-xs mt-1">If you were given a direct access code, enter it in the box above.</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = assignments.map(a => {
            let statusBadge = '';
            let actionBtn = '';

            if (a.status === 'completed') {
                statusBadge = `<span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-1 rounded-full"><i class="fa-solid fa-circle-check mr-1"></i>Completed</span>`;
                actionBtn = `<button onclick="viewResult(${a.attempt_id})" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs py-2 rounded-lg transition"><i class="fa-solid fa-chart-bar mr-1"></i>View Results</button>`;
            } else if (a.status === 'in_progress') {
                statusBadge = `<span class="bg-amber-100 text-amber-800 text-xs font-semibold px-2.5 py-1 rounded-full"><i class="fa-solid fa-clock mr-1"></i>In Progress</span>`;
                actionBtn = `<button onclick="launchByAccessCode('${a.access_code}')" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs py-2 rounded-lg shadow transition"><i class="fa-solid fa-play mr-1"></i>Resume Exam</button>`;
            } else if (a.status === 'expired') {
                statusBadge = `<span class="bg-red-100 text-red-800 text-xs font-semibold px-2.5 py-1 rounded-full"><i class="fa-solid fa-circle-xmark mr-1"></i>Expired</span>`;
                actionBtn = `<button disabled class="w-full bg-slate-100 text-slate-400 font-semibold text-xs py-2 rounded-lg cursor-not-allowed">Expired</button>`;
            } else {
                statusBadge = `<span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-full"><i class="fa-solid fa-circle-dot mr-1"></i>Not Started</span>`;
                actionBtn = `<button onclick="launchByAccessCode('${a.access_code}')" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs py-2 rounded-lg shadow transition"><i class="fa-solid fa-play mr-1"></i>Start Assessment</button>`;
            }

            return `
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between space-y-4 hover:shadow-md transition">
                    <div class="space-y-2">
                        <div class="flex justify-between items-start">
                            <span class="text-[11px] font-mono bg-slate-100 text-slate-600 px-2 py-0.5 rounded border border-slate-200">${a.access_code}</span>
                            ${statusBadge}
                        </div>
                        <h3 class="font-bold text-slate-900 text-base leading-tight">${a.exam_title}</h3>
                        <div class="flex flex-wrap gap-2 text-[11px] text-slate-500 pt-1">
                            <span><i class="fa-regular fa-clock mr-1 text-blue-500"></i>${a.duration_minutes} Mins</span>
                            <span><i class="fa-regular fa-circle-question mr-1 text-teal-500"></i>${a.question_count} Questions</span>
                        </div>
                        <div class="flex flex-wrap gap-1 pt-1">
                            ${a.categories.map(c => `<span class="bg-slate-100 text-slate-700 text-[10px] px-2 py-0.5 rounded">${c}</span>`).join('')}
                        </div>
                    </div>
                    <div class="border-t border-slate-100 pt-3">
                        ${actionBtn}
                    </div>
                </div>
            `;
        }).join('');

    } catch (err) {
        showToast('Error loading assignments', 'error');
    }
}

async function launchByAccessCode(providedCode = null) {
    const code = providedCode || document.getElementById('access-code-input').value.trim();
    if (!code) {
        showToast('Please enter an access code', 'warning');
        return;
    }

    try {
        const res = await fetch('/api/assessments/start', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ access_code: code })
        });

        if (!res.ok) {
            const err = await res.json();
            showToast(err.detail || 'Unable to start assessment', 'error');
            return;
        }

        const data = await res.json();

        // Initialize test runner state
        state.testRunner = {
            attemptId: data.attempt_id,
            examTitle: data.exam_title,
            instructions: data.instructions,
            durationMinutes: data.duration_minutes,
            remainingSeconds: data.remaining_seconds,
            totalQuestions: data.total_questions,
            questions: data.questions,
            savedAnswers: data.saved_answers || {},
            markedForReview: new Set(data.marked_for_review || []),
            currentIndex: 0,
            timerInterval: null
        };

        state.activeView = 'candidate-runner';
        routeView();

    } catch (err) {
        showToast('Network error starting assessment', 'error');
    }
}


// =========================================================================
// CANDIDATE TEST RUNNER (DISTRACTION-FREE VIEW)
// =========================================================================

function renderCandidateRunnerView(container) {
    const tr = state.testRunner;

    container.innerHTML = `
        <div class="max-w-6xl mx-auto space-y-4">

            <!-- Header Bar: Title, Category, Timer, Progress -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">${tr.examTitle}</h1>
                    <div class="flex items-center space-x-3 text-xs text-slate-500 mt-1">
                        <span><i class="fa-solid fa-layer-group text-blue-600 mr-1"></i><span id="current-category-badge">Category</span></span>
                        <span>&bull;</span>
                        <span><i class="fa-solid fa-list-ol text-teal-600 mr-1"></i>Question <span id="q-num-label">1</span> of ${tr.totalQuestions}</span>
                    </div>
                </div>

                <!-- Timer & Autosave Status -->
                <div class="flex items-center space-x-6">
                    <div id="autosave-status" class="text-xs text-slate-400 flex items-center">
                        <i class="fa-solid fa-cloud-arrow-up text-green-500 mr-1"></i>Autosaved
                    </div>
                    <div class="bg-slate-900 text-white px-4 py-2 rounded-xl flex items-center space-x-2 shadow">
                        <i class="fa-regular fa-clock text-blue-400"></i>
                        <span id="timer-display" class="font-mono text-lg font-bold">00:00</span>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                <div id="exam-progress-bar" class="bg-blue-600 h-full transition-all duration-300" style="width: 0%;"></div>
            </div>

            <!-- Main Question & Navigation Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

                <!-- Left 3 Cols: Question Display Card -->
                <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-200 p-6 test-runner-card flex flex-col justify-between min-h-[480px]">
                    <div id="question-content-area" class="space-y-4">
                        <!-- Rendered by renderCurrentQuestion() -->
                    </div>

                    <!-- Navigation Action Controls -->
                    <div class="border-t border-slate-200 pt-5 mt-6 flex justify-between items-center">
                        <button onclick="navigateQuestion(-1)" id="btn-prev-q" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition flex items-center">
                            <i class="fa-solid fa-arrow-left mr-2"></i>Previous
                        </button>

                        <button onclick="toggleMarkReviewCurrent()" id="btn-review-q" class="px-4 py-2 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 font-semibold text-xs transition flex items-center">
                            <i class="fa-solid fa-bookmark mr-2"></i><span id="review-btn-text">Mark for Review</span>
                        </button>

                        <button onclick="navigateQuestion(1)" id="btn-next-q" class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs shadow transition flex items-center">
                            Next<i class="fa-solid fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Right 1 Col: Question Navigation Panel & Submit -->
                <div class="space-y-4">
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4 shadow-sm">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-sm">Question Matrix</h3>
                            <span class="text-[11px] text-slate-500"><span id="answered-count-label">0</span>/${tr.totalQuestions} Answered</span>
                        </div>

                        <!-- Matrix Grid -->
                        <div id="question-matrix-grid" class="grid grid-cols-5 gap-2 max-h-64 overflow-y-auto p-1">
                            <!-- Matrix buttons -->
                        </div>

                        <!-- Legend -->
                        <div class="pt-2 text-[11px] text-slate-500 space-y-1.5 border-t border-slate-100">
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-blue-600 mr-2"></span>Answered</div>
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-amber-500 mr-2"></span>Marked for Review</div>
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-white border border-slate-400 mr-2"></span>Unanswered</div>
                        </div>

                        <button onclick="confirmSubmitModal()" class="w-full mt-4 bg-green-600 hover:bg-green-500 text-white font-bold py-3 rounded-xl shadow-lg transition flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Submit Assessment</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    `;

    startTimer();
    renderCurrentQuestion();
    renderQuestionMatrix();
}

function startTimer() {
    const tr = state.testRunner;
    clearInterval(tr.timerInterval);

    const timerDisplay = document.getElementById('timer-display');

    function update() {
        if (tr.remainingSeconds <= 0) {
            clearInterval(tr.timerInterval);
            if (timerDisplay) timerDisplay.innerText = "00:00";
            showToast('Time limit expired! Submitting assessment...', 'warning');
            submitAssessment(true);
            return;
        }

        const mins = Math.floor(tr.remainingSeconds / 60);
        const secs = tr.remainingSeconds % 60;
        const formatted = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;

        if (timerDisplay) {
            timerDisplay.innerText = formatted;
            if (tr.remainingSeconds <= 180) {
                timerDisplay.parentElement.classList.add('timer-warning');
            }
        }
        tr.remainingSeconds--;
    }

    update();
    tr.timerInterval = setInterval(update, 1000);
}

function renderCurrentQuestion() {
    const tr = state.testRunner;
    const q = tr.questions[tr.currentIndex];
    if (!q) return;

    // Update labels
    document.getElementById('current-category-badge').innerText = q.category;
    document.getElementById('q-num-label').innerText = tr.currentIndex + 1;

    // Progress bar
    const pct = Math.round(((tr.currentIndex + 1) / tr.totalQuestions) * 100);
    document.getElementById('exam-progress-bar').style.width = `${pct}%`;

    // Category / Difficulty badge
    const area = document.getElementById('question-content-area');

    let categoryColor = 'bg-blue-100 text-blue-800';
    if (q.category === 'Verbal Reasoning') categoryColor = 'bg-teal-100 text-teal-800';
    if (q.category === 'Numerical Reasoning') categoryColor = 'bg-purple-100 text-purple-800';

    const saved = tr.savedAnswers[q.id] || {};
    const isMarked = tr.markedForReview.has(q.id);

    document.getElementById('review-btn-text').innerText = isMarked ? 'Unmark Review' : 'Mark for Review';
    document.getElementById('btn-review-q').className = isMarked
        ? "px-4 py-2 rounded-lg bg-amber-500 text-white font-semibold text-xs transition flex items-center shadow"
        : "px-4 py-2 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 font-semibold text-xs transition flex items-center";

    // Disable prev/next at bounds
    document.getElementById('btn-prev-q').disabled = (tr.currentIndex === 0);
    document.getElementById('btn-prev-q').style.opacity = (tr.currentIndex === 0) ? '0.5' : '1';

    // Choices or numeric UI
    let inputHtml = '';
    if (q.question_type === 'numeric') {
        const val = saved.numeric_answer || '';
        inputHtml = `
            <div class="pt-3 max-w-md">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Your Numeric Answer:</label>
                <input type="number" step="any" oninput="handleNumericInput(${q.id}, this.value)" value="${val}" placeholder="Type exact numeric value e.g. 127" class="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-lg p-3 text-base focus:outline-none focus:border-blue-600 font-mono">
            </div>
        `;
    } else {
        const choices = q.choices || [];
        inputHtml = `
            <div class="space-y-3 pt-3">
                ${choices.map(c => {
                    const checked = (saved.selected_choice === c.id) ? 'checked' : '';
                    const borderClass = checked ? 'border-blue-600 bg-blue-50/50' : 'border-slate-200 hover:border-slate-300 bg-white';
                    return `
                        <label onclick="handleChoiceSelect(${q.id}, '${c.id}')" class="flex items-center p-3.5 border rounded-xl cursor-pointer transition ${borderClass}">
                            <input type="radio" name="q_choice_${q.id}" value="${c.id}" ${checked} class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                            <span class="ml-3 text-sm font-medium text-slate-800">${c.text}</span>
                        </label>
                    `;
                }).join('')}
            </div>
        `;
    }

    // Passage component if present
    let passageHtml = '';
    if (q.passage_text) {
        passageHtml = `
            <div class="passage-box mb-4">
                <div class="text-xs font-bold uppercase text-blue-600 tracking-wider mb-1"><i class="fa-solid fa-align-left mr-1"></i>Reading Passage</div>
                <div class="text-sm text-slate-700 leading-relaxed">${q.passage_text}</div>
            </div>
        `;
    }

    // Diagram component if present
    let diagramHtml = '';
    if (q.diagram_svg) {
        diagramHtml = `
            <div class="svg-diagram-container bg-slate-50 p-4 rounded-xl border border-slate-200 my-4 text-center">
                ${q.diagram_svg}
            </div>
        `;
    }

    area.innerHTML = `
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex space-x-2">
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full ${categoryColor}">${q.category}</span>
                <span class="text-[11px] font-medium bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full"><i class="fa-solid fa-gauge mr-1"></i>${q.difficulty}</span>
            </div>
            <span class="text-xs text-slate-400 font-semibold">${q.points} Point(s)</span>
        </div>

        ${passageHtml}
        ${diagramHtml}

        <div class="text-base font-semibold text-slate-900 leading-snug pt-1">
            ${q.question_text}
        </div>

        ${inputHtml}
    `;
}

function renderQuestionMatrix() {
    const tr = state.testRunner;
    const grid = document.getElementById('question-matrix-grid');
    if (!grid) return;

    let answeredCount = 0;

    grid.innerHTML = tr.questions.map((q, idx) => {
        const saved = tr.savedAnswers[q.id];
        const hasAns = saved && (saved.selected_choice || (saved.numeric_answer !== null && saved.numeric_answer !== ''));
        if (hasAns) answeredCount++;

        const isMarked = tr.markedForReview.has(q.id);
        const isActive = (idx === tr.currentIndex);

        let btnClass = 'q-btn-unanswered';
        if (isMarked) {
            btnClass = 'q-btn-review';
        } else if (hasAns) {
            btnClass = 'q-btn-answered';
        }

        const activeBorder = isActive ? 'ring-2 ring-navy-900 font-bold scale-105' : '';

        return `
            <button onclick="jumpToQuestion(${idx})" class="h-9 w-9 rounded-lg border text-xs flex items-center justify-center font-medium q-btn ${btnClass} ${activeBorder}">
                ${idx + 1}
            </button>
        `;
    }).join('');

    const countLabel = document.getElementById('answered-count-label');
    if (countLabel) countLabel.innerText = answeredCount;
}

function jumpToQuestion(index) {
    state.testRunner.currentIndex = index;
    renderCurrentQuestion();
    renderQuestionMatrix();
}

function navigateQuestion(direction) {
    const tr = state.testRunner;
    const newIndex = tr.currentIndex + direction;
    if (newIndex >= 0 && newIndex < tr.totalQuestions) {
        tr.currentIndex = newIndex;
        renderCurrentQuestion();
        renderQuestionMatrix();
    }
}

async function handleChoiceSelect(qid, choiceId) {
    const tr = state.testRunner;
    tr.savedAnswers[qid] = { selected_choice: choiceId, numeric_answer: null };
    renderQuestionMatrix();
    showAutosaveStatus();

    await fetch('/api/assessments/save-answer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            attempt_id: tr.attemptId,
            question_id: qid,
            selected_choice: choiceId
        })
    });
}

async function handleNumericInput(qid, val) {
    const tr = state.testRunner;
    tr.savedAnswers[qid] = { selected_choice: null, numeric_answer: val };
    renderQuestionMatrix();
    showAutosaveStatus();

    await fetch('/api/assessments/save-answer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            attempt_id: tr.attemptId,
            question_id: qid,
            numeric_answer: val
        })
    });
}

async function toggleMarkReviewCurrent() {
    const tr = state.testRunner;
    const q = tr.questions[tr.currentIndex];
    const isMarked = tr.markedForReview.has(q.id);

    if (isMarked) {
        tr.markedForReview.delete(q.id);
    } else {
        tr.markedForReview.add(q.id);
    }

    renderCurrentQuestion();
    renderQuestionMatrix();

    await fetch('/api/assessments/toggle-review', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            attempt_id: tr.attemptId,
            question_id: q.id,
            is_marked_for_review: !isMarked
        })
    });
}

function showAutosaveStatus() {
    const status = document.getElementById('autosave-status');
    if (!status) return;
    status.innerHTML = `<i class="fa-solid fa-spinner fa-spin text-blue-500 mr-1"></i>Saving...`;
    setTimeout(() => {
        status.innerHTML = `<i class="fa-solid fa-cloud-arrow-up text-green-500 mr-1"></i>Autosaved`;
    }, 400);
}

function confirmSubmitModal() {
    const tr = state.testRunner;
    let answered = 0;
    tr.questions.forEach(q => {
        const ans = tr.savedAnswers[q.id];
        if (ans && (ans.selected_choice || (ans.numeric_answer !== null && ans.numeric_answer !== ''))) {
            answered++;
        }
    });

    const unanswered = tr.totalQuestions - answered;
    const marked = tr.markedForReview.size;

    showModal(`
        <div class="space-y-4">
            <div class="text-center">
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-3 text-xl">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Confirm Assessment Submission</h3>
                <p class="text-slate-500 text-xs mt-1">Are you sure you want to finalize and submit your assessment?</p>
            </div>

            <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 grid grid-cols-3 gap-2 text-center text-xs">
                <div>
                    <div class="font-bold text-blue-600 text-lg">${answered}</div>
                    <div class="text-slate-500">Answered</div>
                </div>
                <div>
                    <div class="font-bold text-amber-600 text-lg">${marked}</div>
                    <div class="text-slate-500">Review Marked</div>
                </div>
                <div>
                    <div class="font-bold text-red-600 text-lg">${unanswered}</div>
                    <div class="text-slate-500">Unanswered</div>
                </div>
            </div>

            ${unanswered > 0 ? `
                <div class="p-3 bg-amber-50 text-amber-800 rounded-lg text-xs flex items-center">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 mr-2 text-base"></i>
                    <span>You still have <strong>${unanswered} unanswered question(s)</strong>. Unanswered questions will receive 0 points.</span>
                </div>
            ` : ''}

            <div class="flex space-x-3 pt-2">
                <button onclick="closeModal()" class="w-1/2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 rounded-lg text-xs">Continue Test</button>
                <button onclick="submitAssessment(false)" class="w-1/2 bg-green-600 hover:bg-green-500 text-white font-bold py-2.5 rounded-lg text-xs shadow">Confirm & Submit</button>
            </div>
        </div>
    `);
}

async function submitAssessment(isTimeout = false) {
    const tr = state.testRunner;
    clearInterval(tr.timerInterval);
    closeModal();

    try {
        const res = await fetch('/api/assessments/submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attempt_id: tr.attemptId,
                is_timeout: isTimeout
            })
        });

        const data = await res.json();
        showToast('Assessment submitted successfully', 'success');

        // Show candidate result view
        renderCandidateResultView(document.getElementById('app-container'), tr.attemptId, data.summary);

    } catch (err) {
        showToast('Error submitting assessment', 'error');
    }
}


// =========================================================================
// CANDIDATE RESULTS VIEW
// =========================================================================

async function viewResult(attemptId) {
    state.activeView = 'candidate-result';
    routeView();
    renderCandidateResultView(document.getElementById('app-container'), attemptId);
}

async function renderApplicantResultsList(container) {
    container.innerHTML = `
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-slate-900"><i class="fa-solid fa-award text-teal-600 mr-2"></i>My Results History</h1>
            <div id="results-history-list" class="space-y-4">
                <div class="text-slate-500 text-center py-8"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading results history...</div>
            </div>
        </div>
    `;

    try {
        const res = await fetch(`/api/results?applicant_id=${state.currentUser.id}`);
        const results = await res.json();
        const list = document.getElementById('results-history-list');

        if (!results || results.length === 0) {
            list.innerHTML = `
                <div class="bg-white rounded-xl p-8 text-center border border-slate-200 text-slate-500">
                    No completed assessment results found.
                </div>
            `;
            return;
        }

        list.innerHTML = results.map(r => `
            <div class="bg-white rounded-xl border border-slate-200 p-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 shadow-sm">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">${r.exam_title}</h3>
                    <p class="text-xs text-slate-500 mt-1">Submitted on: ${r.submitted_at}</p>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="text-right">
                        <div class="text-lg font-bold text-blue-600">${r.percentage_score}%</div>
                        <div class="text-xs text-slate-500">${r.total_score} / ${r.max_score} pts</div>
                    </div>
                    <div>
                        ${r.passed ? '<span class="bg-green-100 text-green-800 text-xs font-bold px-3 py-1 rounded-full">PASSED</span>' : '<span class="bg-red-100 text-red-800 text-xs font-bold px-3 py-1 rounded-full">FAILED</span>'}
                    </div>
                    <button onclick="viewResult(${r.attempt_id})" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs px-4 py-2 rounded-lg">
                        Details
                    </button>
                </div>
            </div>
        `).join('');

    } catch (err) {
        showToast('Error loading results', 'error');
    }
}

async function renderCandidateResultView(container, attemptId = null, preloadedSummary = null) {
    container.innerHTML = `
        <div class="max-w-4xl mx-auto space-y-6">
            <div id="result-detail-content" class="text-center py-12 text-slate-500">
                <i class="fa-solid fa-spinner fa-spin text-2xl mr-2"></i>Loading result details...
            </div>
        </div>
    `;

    if (!attemptId && state.testRunner.attemptId) {
        attemptId = state.testRunner.attemptId;
    }

    try {
        const res = await fetch(`/api/results/${attemptId}`);
        const data = await res.json();
        const content = document.getElementById('result-detail-content');

        const summary = data.summary;
        const passBadge = summary.passed
            ? `<span class="bg-green-100 text-green-800 text-sm font-bold px-4 py-1.5 rounded-full border border-green-200"><i class="fa-solid fa-circle-check mr-1.5"></i>PASSED</span>`
            : `<span class="bg-red-100 text-red-800 text-sm font-bold px-4 py-1.5 rounded-full border border-red-200"><i class="fa-solid fa-circle-xmark mr-1.5"></i>FAILED</span>`;

        // Category breakdown HTML
        const catStats = summary.category_scores || {};
        const catHtml = Object.entries(catStats).map(([cat, info]) => `
            <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 space-y-2">
                <div class="flex justify-between items-center text-xs font-semibold text-slate-700">
                    <span>${cat}</span>
                    <span class="text-blue-600 font-bold">${info.percentage}% (${info.score}/${info.max} pts)</span>
                </div>
                <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                    <div class="bg-blue-600 h-full rounded-full" style="width: ${info.percentage}%;"></div>
                </div>
            </div>
        `).join('');

        content.className = "space-y-6 text-left";
        content.innerHTML = `
            <!-- Result Top Hero -->
            <div class="bg-white rounded-2xl border border-slate-200 p-8 shadow-sm space-y-6 text-center">
                <div class="inline-block">
                    ${passBadge}
                </div>

                <div>
                    <h1 class="text-2xl font-bold text-slate-900">${data.exam_title}</h1>
                    <p class="text-xs text-slate-500 mt-1">Candidate: <strong>${data.applicant_name}</strong> (${data.applicant_email}) &bull; Submitted: ${data.submitted_at}</p>
                </div>

                <!-- Big Score Gauge -->
                <div class="flex justify-center items-center space-x-8 py-2">
                    <div>
                        <div class="text-4xl font-black text-blue-600">${summary.percentage_score}%</div>
                        <div class="text-xs text-slate-500 font-medium mt-1">Percentage Score</div>
                    </div>
                    <div class="h-10 w-px bg-slate-200"></div>
                    <div>
                        <div class="text-4xl font-black text-slate-800">${summary.total_score} <span class="text-lg text-slate-400 font-normal">/ ${summary.max_score}</span></div>
                        <div class="text-xs text-slate-500 font-medium mt-1">Raw Score</div>
                    </div>
                    <div class="h-10 w-px bg-slate-200"></div>
                    <div>
                        <div class="text-4xl font-black text-teal-600">${Math.round(summary.time_taken_seconds / 60)} <span class="text-lg text-slate-400 font-normal">m</span></div>
                        <div class="text-xs text-slate-500 font-medium mt-1">Time Taken</div>
                    </div>
                </div>
            </div>

            <!-- Category Performance Breakdown -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                <h3 class="font-bold text-slate-900 text-base"><i class="fa-solid fa-chart-bar text-blue-600 mr-2"></i>Category Performance Breakdown</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    ${catHtml}
                </div>
            </div>

            <!-- Question Review (Detailed explanations) -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
                <h3 class="font-bold text-slate-900 text-base"><i class="fa-solid fa-square-check text-teal-600 mr-2"></i>Question Review & Explanations</h3>
                <div class="space-y-4">
                    ${data.question_review.map((q, idx) => {
                        const isCorrect = q.is_correct;
                        const icon = isCorrect
                            ? `<span class="text-green-600 font-bold"><i class="fa-solid fa-check mr-1"></i>Correct (+${q.score_awarded} pts)</span>`
                            : `<span class="text-red-600 font-bold"><i class="fa-solid fa-xmark mr-1"></i>Incorrect (0 pts)</span>`;

                        return `
                            <div class="p-4 rounded-xl border ${isCorrect ? 'border-green-200 bg-green-50/30' : 'border-red-200 bg-red-50/30'} space-y-2">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="font-bold text-slate-700">Q${idx + 1}. [${q.category}]</span>
                                    ${icon}
                                </div>
                                <div class="text-sm font-semibold text-slate-900">${q.question_text}</div>
                                <div class="text-xs text-slate-600 pt-1">
                                    <div><strong>Your Answer:</strong> ${q.user_choice || q.user_numeric || 'Unanswered'}</div>
                                    <div><strong>Correct Answer:</strong> ${q.correct_answer}</div>
                                </div>
                                ${q.explanation ? `
                                    <div class="text-xs text-slate-500 bg-white p-2.5 rounded-lg border border-slate-200 mt-2">
                                        <i class="fa-solid fa-circle-info text-blue-500 mr-1"></i><strong>Explanation:</strong> ${q.explanation}
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>

            <div class="text-center">
                <button onclick="navigateTo('applicant-dashboard')" class="bg-navy-900 hover:bg-slate-800 text-white font-semibold text-xs px-6 py-3 rounded-xl shadow">
                    Back to Dashboard
                </button>
            </div>
        `;

    } catch (err) {
        showToast('Error loading result details', 'error');
    }
}


// =========================================================================
// ADMINISTRATOR VIEWS
// =========================================================================

async function renderAdminAnalyticsView(container) {
    container.innerHTML = `
        <div class="space-y-6">
            <!-- Header & Filter Bar -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900"><i class="fa-solid fa-chart-pie text-blue-600 mr-2"></i>Assessment Analytics</h1>
                    <p class="text-xs text-slate-500 mt-0.5">Comprehensive performance analytics and score distribution across applicants.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button onclick="downloadCSVExport()" class="bg-teal-600 hover:bg-teal-500 text-white font-semibold text-xs px-4 py-2.5 rounded-xl shadow transition flex items-center">
                        <i class="fa-solid fa-file-csv mr-2 text-sm"></i>Export CSV Results
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                    <div class="text-slate-500 text-xs font-medium">Total Applicants</div>
                    <div id="kpi-applicants" class="text-2xl font-black text-slate-900 mt-1">0</div>
                </div>
                <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                    <div class="text-slate-500 text-xs font-medium">Assigned Exams</div>
                    <div id="kpi-assigned" class="text-2xl font-black text-blue-600 mt-1">0</div>
                </div>
                <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                    <div class="text-slate-500 text-xs font-medium">Completed Rate</div>
                    <div id="kpi-completion" class="text-2xl font-black text-teal-600 mt-1">0%</div>
                </div>
                <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                    <div class="text-slate-500 text-xs font-medium">Average Score</div>
                    <div id="kpi-avg-score" class="text-2xl font-black text-indigo-600 mt-1">0%</div>
                </div>
                <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm col-span-2 md:col-span-1">
                    <div class="text-slate-500 text-xs font-medium">Overall Pass Rate</div>
                    <div id="kpi-pass-rate" class="text-2xl font-black text-green-600 mt-1">0%</div>
                </div>
            </div>

            <!-- Charts Row 1: Category Performance & Score Distribution -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Category Performance Bar Chart -->
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-3">
                    <h3 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-chart-column text-blue-600 mr-2"></i>Category Performance (%)</h3>
                    <div class="h-64 relative">
                        <canvas id="chart-categories"></canvas>
                    </div>
                </div>

                <!-- Score Distribution Doughnut Chart -->
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-3">
                    <h3 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-chart-pie text-teal-600 mr-2"></i>Score Distribution Range</h3>
                    <div class="h-64 relative">
                        <canvas id="chart-distribution"></canvas>
                    </div>
                </div>
            </div>
        </div>
    `;

    try {
        const res = await fetch('/api/analytics');
        const data = await res.json();

        document.getElementById('kpi-applicants').innerText = data.total_applicants;
        document.getElementById('kpi-assigned').innerText = data.total_assigned;
        document.getElementById('kpi-completion').innerText = `${data.completion_rate}%`;
        document.getElementById('kpi-avg-score').innerText = `${data.average_score}%`;
        document.getElementById('kpi-pass-rate').innerText = `${data.pass_rate}%`;

        // Render Category Chart
        const catCtx = document.getElementById('chart-categories').getContext('2d');
        const catLabels = Object.keys(data.category_performance);
        const catValues = Object.values(data.category_performance).map(c => c.avg_percentage);

        new Chart(catCtx, {
            type: 'bar',
            data: {
                labels: catLabels,
                datasets: [{
                    label: 'Avg Score %',
                    data: catValues,
                    backgroundColor: ['#2563eb', '#0d9488', '#8b5cf6'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, max: 100 }
                }
            }
        });

        // Render Distribution Chart
        const distCtx = document.getElementById('chart-distribution').getContext('2d');
        const distLabels = Object.keys(data.score_distribution);
        const distValues = Object.values(data.score_distribution);

        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: distLabels,
                datasets: [{
                    data: distValues,
                    backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

    } catch (err) {
        showToast('Error loading analytics', 'error');
    }
}

async function downloadCSVExport() {
    window.location.href = '/api/results/export/csv';
}


// --- APPLICANTS MANAGEMENT VIEW ---
async function renderAdminApplicantsView(container) {
    container.innerHTML = `
        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900"><i class="fa-solid fa-users text-blue-600 mr-2"></i>Applicant Management</h1>
                    <p class="text-xs text-slate-500 mt-0.5">Manage job candidates and assign aptitude assessments.</p>
                </div>
                <button onclick="openRegisterApplicantModal()" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-4 py-2.5 rounded-xl shadow transition flex items-center">
                    <i class="fa-solid fa-user-plus mr-2"></i>Register Applicant
                </button>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 font-bold uppercase text-slate-500 text-[10px] tracking-wider">
                        <tr>
                            <th class="p-4">Applicant Name</th>
                            <th class="p-4">Email Address</th>
                            <th class="p-4">Applied Position</th>
                            <th class="p-4">Assignments</th>
                            <th class="p-4">Action</th>
                        </tr>
                    </thead>
                    <tbody id="applicants-table-body">
                        <tr><td colspan="5" class="p-8 text-center text-slate-400">Loading applicants...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;

    try {
        const res = await fetch('/api/applicants');
        const applicants = await res.json();

        const tbody = document.getElementById('applicants-table-body');
        if (!applicants || applicants.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="p-8 text-center text-slate-400">No applicants registered yet.</td></tr>`;
            return;
        }

        tbody.innerHTML = applicants.map(a => `
            <tr class="border-b border-slate-100 hover:bg-slate-50/80 transition">
                <td class="p-4 font-bold text-slate-900">${a.full_name}</td>
                <td class="p-4 text-slate-600 font-mono">${a.email}</td>
                <td class="p-4"><span class="bg-slate-100 text-slate-700 font-medium px-2 py-1 rounded">${a.applied_position || 'N/A'}</span></td>
                <td class="p-4 font-semibold text-blue-600">${a.completed_count} / ${a.assignment_count} Completed</td>
                <td class="p-4">
                    <button onclick="openAssignExamModal(${a.id}, '${a.full_name}')" class="bg-teal-600 hover:bg-teal-500 text-white font-semibold px-3 py-1.5 rounded-lg transition text-[11px]">
                        <i class="fa-solid fa-paper-plane mr-1"></i>Assign Exam
                    </button>
                </td>
            </tr>
        `).join('');

    } catch (err) {
        showToast('Error loading applicants', 'error');
    }
}

function openRegisterApplicantModal() {
    showModal(`
        <div class="space-y-4">
            <h3 class="text-lg font-bold text-slate-900">Register New Applicant</h3>
            <form onsubmit="submitNewApplicant(event)" class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Full Name</label>
                    <input type="text" id="reg-name" required placeholder="e.g. Robert Vance" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs focus:outline-none focus:border-blue-600">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address</label>
                    <input type="email" id="reg-email" required placeholder="e.g. robert.vance@example.com" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs focus:outline-none focus:border-blue-600">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Applied Position</label>
                    <input type="text" id="reg-position" placeholder="e.g. Financial Analyst" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs focus:outline-none focus:border-blue-600">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Phone Number</label>
                    <input type="text" id="reg-phone" placeholder="e.g. 555-0199" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs focus:outline-none focus:border-blue-600">
                </div>
                <div class="flex justify-end space-x-3 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg text-xs font-semibold">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-xs font-semibold shadow">Create Applicant</button>
                </div>
            </form>
        </div>
    `);
}

async function submitNewApplicant(e) {
    e.preventDefault();
    const payload = {
        full_name: document.getElementById('reg-name').value,
        email: document.getElementById('reg-email').value,
        applied_position: document.getElementById('reg-position').value,
        phone: document.getElementById('reg-phone').value
    };

    try {
        const res = await fetch('/api/applicants', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok) {
            const err = await res.json();
            showToast(err.detail || 'Error creating applicant', 'error');
            return;
        }

        showToast('Applicant registered successfully', 'success');
        closeModal();
        await loadUsers();
        routeView();

    } catch (err) {
        showToast('Error registering applicant', 'error');
    }
}

async function openAssignExamModal(applicantId, applicantName) {
    const res = await fetch('/api/exams');
    const exams = await res.json();

    showModal(`
        <div class="space-y-4">
            <h3 class="text-lg font-bold text-slate-900">Assign Assessment to ${applicantName}</h3>
            <form onsubmit="submitAssignExam(event, ${applicantId})" class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Select Exam</label>
                    <select id="assign-exam-id" required class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs focus:outline-none focus:border-blue-600">
                        ${exams.map(e => `<option value="${e.id}">${e.title} (${e.duration_minutes} Mins, ${e.question_count} Qs)</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Due In (Days)</label>
                    <input type="number" id="assign-days" value="7" min="1" max="30" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs focus:outline-none focus:border-blue-600">
                </div>
                <div class="flex justify-end space-x-3 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg text-xs font-semibold">Cancel</button>
                    <button type="submit" class="bg-teal-600 text-white px-5 py-2 rounded-lg text-xs font-semibold shadow">Generate Assignment</button>
                </div>
            </form>
        </div>
    `);
}

async function submitAssignExam(e, applicantId) {
    e.preventDefault();
    const examId = parseInt(document.getElementById('assign-exam-id').value);
    const dueDays = parseInt(document.getElementById('assign-days').value);

    try {
        const res = await fetch('/api/assignments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ exam_id: examId, applicant_id: applicantId, due_days: dueDays })
        });
        const data = await res.json();

        showToast(`Assignment created! Access Code: ${data.access_code}`, 'success');
        closeModal();
        routeView();

    } catch (err) {
        showToast('Error assigning exam', 'error');
    }
}


// --- QUESTION BANK VIEW ---
async function renderAdminQuestionsView(container) {
    container.innerHTML = `
        <div class="space-y-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900"><i class="fa-solid fa-database text-teal-600 mr-2"></i>Question Bank</h1>
                    <p class="text-xs text-slate-500 mt-0.5">Manage standardized aptitude questions across Abstract, Verbal, and Numerical categories.</p>
                </div>
                <button onclick="openQuestionModal()" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-4 py-2.5 rounded-xl shadow transition flex items-center">
                    <i class="fa-solid fa-plus mr-2"></i>Add Question
                </button>
            </div>

            <!-- Filters -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-wrap gap-3 items-center">
                <select id="filter-q-cat" onchange="loadQuestionsTable()" class="bg-slate-50 border border-slate-300 text-xs rounded-lg px-3 py-2">
                    <option value="">All Categories</option>
                    <option value="Abstract Reasoning">Abstract Reasoning</option>
                    <option value="Verbal Reasoning">Verbal Reasoning</option>
                    <option value="Numerical Reasoning">Numerical Reasoning</option>
                </select>

                <select id="filter-q-diff" onchange="loadQuestionsTable()" class="bg-slate-50 border border-slate-300 text-xs rounded-lg px-3 py-2">
                    <option value="">All Difficulties</option>
                    <option value="Easy">Easy</option>
                    <option value="Medium">Medium</option>
                    <option value="Hard">Hard</option>
                </select>

                <input type="text" id="filter-q-search" oninput="loadQuestionsTable()" placeholder="Search question text..." class="bg-slate-50 border border-slate-300 text-xs rounded-lg px-3 py-2 w-64">
            </div>

            <div id="questions-grid-container" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="col-span-full py-8 text-center text-slate-400">Loading questions...</div>
            </div>
        </div>
    `;

    loadQuestionsTable();
}

async function loadQuestionsTable() {
    const cat = document.getElementById('filter-q-cat')?.value || '';
    const diff = document.getElementById('filter-q-diff')?.value || '';
    const search = document.getElementById('filter-q-search')?.value || '';

    const url = `/api/questions?category=${encodeURIComponent(cat)}&difficulty=${encodeURIComponent(diff)}&search=${encodeURIComponent(search)}`;
    try {
        const res = await fetch(url);
        const questions = await res.json();
        const container = document.getElementById('questions-grid-container');

        if (!questions || questions.length === 0) {
            container.innerHTML = `<div class="col-span-full bg-white p-8 rounded-xl text-center text-slate-500 border border-slate-200">No questions found matching filter criteria.</div>`;
            return;
        }

        container.innerHTML = questions.map(q => `
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm flex flex-col justify-between space-y-3 hover:shadow-md transition">
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-blue-50 text-blue-700">${q.category}</span>
                        <div class="flex space-x-2 text-[11px]">
                            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded">${q.difficulty}</span>
                            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-mono">${q.points} pt</span>
                        </div>
                    </div>
                    <p class="text-xs font-semibold text-slate-900 leading-snug line-clamp-3">${q.question_text}</p>
                    ${q.diagram_svg ? '<div class="text-[10px] text-teal-600 font-semibold"><i class="fa-solid fa-image mr-1"></i>Visual Diagram Included</div>' : ''}
                    ${q.passage_text ? '<div class="text-[10px] text-purple-600 font-semibold"><i class="fa-solid fa-align-left mr-1"></i>Reading Passage Included</div>' : ''}
                </div>
                <div class="border-t border-slate-100 pt-3 flex justify-between items-center text-xs">
                    <span class="text-slate-400 text-[11px]">ID: #${q.id}</span>
                    <button onclick="deleteQuestion(${q.id})" class="text-red-600 hover:text-red-700 font-semibold">
                        <i class="fa-solid fa-trash mr-1"></i>Delete
                    </button>
                </div>
            </div>
        `).join('');

    } catch (err) {
        showToast('Error loading question bank', 'error');
    }
}

function openQuestionModal() {
    showModal(`
        <div class="space-y-4">
            <h3 class="text-lg font-bold text-slate-900">Create New Question</h3>
            <form onsubmit="submitQuestion(event)" class="space-y-3">
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Category</label>
                        <select id="q-category" required class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs">
                            <option value="Abstract Reasoning">Abstract Reasoning</option>
                            <option value="Verbal Reasoning">Verbal Reasoning</option>
                            <option value="Numerical Reasoning">Numerical Reasoning</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Difficulty</label>
                        <select id="q-difficulty" required class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs">
                            <option value="Easy">Easy</option>
                            <option value="Medium">Medium</option>
                            <option value="Hard">Hard</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Type</label>
                        <select id="q-type" required class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="visual_reasoning">Visual Reasoning</option>
                            <option value="numeric">Numeric Input</option>
                            <option value="true_false">True / False</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Question Text</label>
                    <textarea id="q-text" required rows="2" placeholder="Enter question prompt..." class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Passage Text (Optional for Verbal)</label>
                    <textarea id="q-passage" rows="2" placeholder="Enter reading passage if applicable..." class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs font-mono"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">SVG Diagram Code (Optional for Visual)</label>
                    <textarea id="q-svg" rows="2" placeholder="<svg>...</svg>" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs font-mono"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Correct Answer (e.g. 'a' or numeric '127')</label>
                        <input type="text" id="q-correct" required placeholder="a" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Points</label>
                        <input type="number" id="q-points" step="0.5" value="1.0" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Explanation</label>
                    <textarea id="q-explanation" rows="2" placeholder="Explanation for candidate review..." class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs"></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg text-xs font-semibold">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-xs font-semibold shadow">Save Question</button>
                </div>
            </form>
        </div>
    `);
}

async function submitQuestion(e) {
    e.preventDefault();
    const type = document.getElementById('q-type').value;

    let choices = [
        { id: "a", text: "Option A" },
        { id: "b", text: "Option B" },
        { id: "c", text: "Option C" },
        { id: "d", text: "Option D" }
    ];
    if (type === 'true_false') {
        choices = [
            { id: "a", text: "True" },
            { id: "b", text: "False" },
            { id: "c", text: "Cannot Determine" }
        ];
    } else if (type === 'numeric') {
        choices = [];
    }

    const payload = {
        category: document.getElementById('q-category').value,
        difficulty: document.getElementById('q-difficulty').value,
        question_type: type,
        question_text: document.getElementById('q-text').value,
        passage_text: document.getElementById('q-passage').value || null,
        diagram_svg: document.getElementById('q-svg').value || null,
        correct_answer: document.getElementById('q-correct').value,
        points: parseFloat(document.getElementById('q-points').value),
        explanation: document.getElementById('q-explanation').value || null,
        choices: choices
    };

    try {
        const res = await fetch('/api/questions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (res.ok) {
            showToast('Question created', 'success');
            closeModal();
            loadQuestionsTable();
        }
    } catch (err) {
        showToast('Error creating question', 'error');
    }
}

async function deleteQuestion(qid) {
    if (!confirm('Are you sure you want to delete this question?')) return;
    try {
        await fetch(`/api/questions/${qid}`, { method: 'DELETE' });
        showToast('Question deleted', 'success');
        loadQuestionsTable();
    } catch (err) {
        showToast('Error deleting question', 'error');
    }
}


// --- EXAM BUILDER VIEW ---
async function renderAdminExamsView(container) {
    container.innerHTML = `
        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900"><i class="fa-solid fa-layer-group text-blue-600 mr-2"></i>Exam Builder</h1>
                    <p class="text-xs text-slate-500 mt-0.5">Configure standardized tests, question compositions, duration, and passing rules.</p>
                </div>
                <button onclick="openExamModal()" class="bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs px-4 py-2.5 rounded-xl shadow transition flex items-center">
                    <i class="fa-solid fa-plus mr-2"></i>Create New Exam
                </button>
            </div>

            <div id="exams-list-grid" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="col-span-full py-8 text-center text-slate-400">Loading exams...</div>
            </div>
        </div>
    `;

    loadExamsGrid();
}

async function loadExamsGrid() {
    try {
        const res = await fetch('/api/exams');
        const exams = await res.json();
        const grid = document.getElementById('exams-list-grid');

        if (!exams || exams.length === 0) {
            grid.innerHTML = `<div class="col-span-full bg-white p-8 rounded-xl text-center text-slate-500 border border-slate-200">No exams configured yet.</div>`;
            return;
        }

        grid.innerHTML = exams.map(e => `
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4 hover:shadow-md transition">
                <div class="space-y-1">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded ${e.is_published ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600'}">
                            ${e.is_published ? 'PUBLISHED' : 'DRAFT'}
                        </span>
                        <span class="text-xs text-slate-400">Assigned: ${e.assigned_count} candidates</span>
                    </div>
                    <h3 class="font-bold text-slate-900 text-base">${e.title}</h3>
                    <p class="text-xs text-slate-500 line-clamp-2">${e.description || 'No description provided.'}</p>
                </div>

                <div class="grid grid-cols-3 gap-2 bg-slate-50 p-2.5 rounded-lg border border-slate-100 text-center text-xs text-slate-600">
                    <div><strong>${e.duration_minutes}</strong> min</div>
                    <div><strong>${e.question_count}</strong> Qs</div>
                    <div>Pass <strong>${e.passing_score}%</strong></div>
                </div>

                <div class="border-t border-slate-100 pt-3 flex justify-between items-center text-xs">
                    <button onclick="duplicateExam(${e.id})" class="text-blue-600 hover:text-blue-700 font-semibold"><i class="fa-solid fa-copy mr-1"></i>Duplicate</button>
                    <button onclick="togglePublishExam(${e.id})" class="text-teal-600 hover:text-teal-700 font-semibold"><i class="fa-solid fa-globe mr-1"></i>${e.is_published ? 'Unpublish' : 'Publish'}</button>
                    <button onclick="deleteExam(${e.id})" class="text-red-600 hover:text-red-700 font-semibold"><i class="fa-solid fa-trash mr-1"></i>Delete</button>
                </div>
            </div>
        `).join('');

    } catch (err) {
        showToast('Error loading exams', 'error');
    }
}

async function openExamModal() {
    const qRes = await fetch('/api/questions');
    const questions = await qRes.json();

    showModal(`
        <div class="space-y-4">
            <h3 class="text-lg font-bold text-slate-900">Configure New Exam</h3>
            <form onsubmit="submitNewExam(event)" class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Exam Title</label>
                    <input type="text" id="exam-title" required placeholder="e.g. Senior Software Engineer Aptitude Test" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Description</label>
                    <textarea id="exam-desc" rows="2" placeholder="Brief description for administrators and candidates..." class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Duration (Minutes)</label>
                        <input type="number" id="exam-duration" value="30" min="5" max="180" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Passing Threshold (%)</label>
                        <input type="number" id="exam-pass" value="70" min="0" max="100" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-xs">
                    </div>
                </div>

                <div class="space-y-2 pt-1">
                    <label class="block text-xs font-semibold text-slate-700">Exam Settings</label>
                    <div class="flex space-x-4 text-xs">
                        <label class="flex items-center"><input type="checkbox" id="exam-rand-q" checked class="mr-1.5"> Randomize Questions</label>
                        <label class="flex items-center"><input type="checkbox" id="exam-rand-c" checked class="mr-1.5"> Randomize Choices</label>
                        <label class="flex items-center"><input type="checkbox" id="exam-show-res" checked class="mr-1.5"> Show Candidate Results</label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Select Questions from Bank (${questions.length} available)</label>
                    <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg p-2 space-y-1.5 bg-slate-50">
                        ${questions.map(q => `
                            <label class="flex items-start p-2 bg-white rounded border border-slate-200 cursor-pointer text-xs">
                                <input type="checkbox" name="exam_q_ids" value="${q.id}" checked class="mt-0.5 mr-2">
                                <div>
                                    <span class="font-bold text-slate-900">[${q.category}]</span> ${q.question_text.substring(0, 70)}...
                                </div>
                            </label>
                        `).join('')}
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg text-xs font-semibold">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-xs font-semibold shadow">Create Exam</button>
                </div>
            </form>
        </div>
    `);
}

async function submitNewExam(e) {
    e.preventDefault();
    const checkedBoxes = document.querySelectorAll('input[name="exam_q_ids"]:checked');
    const qIds = Array.from(checkedBoxes).map(cb => parseInt(cb.value));

    const payload = {
        title: document.getElementById('exam-title').value,
        description: document.getElementById('exam-desc').value,
        duration_minutes: parseInt(document.getElementById('exam-duration').value),
        passing_score: parseFloat(document.getElementById('exam-pass').value),
        randomize_questions: document.getElementById('exam-rand-q').checked,
        randomize_choices: document.getElementById('exam-rand-c').checked,
        show_results_to_applicant: document.getElementById('exam-show-res').checked,
        is_published: true,
        question_ids: qIds
    };

    try {
        const res = await fetch('/api/exams', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (res.ok) {
            showToast('Exam created successfully', 'success');
            closeModal();
            loadExamsGrid();
        }
    } catch (err) {
        showToast('Error creating exam', 'error');
    }
}

async function duplicateExam(examId) {
    try {
        await fetch(`/api/exams/${examId}/duplicate`, { method: 'POST' });
        showToast('Exam duplicated', 'success');
        loadExamsGrid();
    } catch (err) {
        showToast('Error duplicating exam', 'error');
    }
}

async function togglePublishExam(examId) {
    try {
        await fetch(`/api/exams/${examId}/publish`, { method: 'POST' });
        showToast('Publish status updated', 'success');
        loadExamsGrid();
    } catch (err) {
        showToast('Error updating publish status', 'error');
    }
}

async function deleteExam(examId) {
    if (!confirm('Are you sure you want to delete this exam?')) return;
    try {
        await fetch(`/api/exams/${examId}`, { method: 'DELETE' });
        showToast('Exam deleted', 'success');
        loadExamsGrid();
    } catch (err) {
        showToast('Error deleting exam', 'error');
    }
}


// --- RESULTS & AUDIT LOG VIEW ---
async function renderAdminResultsView(container) {
    container.innerHTML = `
        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900"><i class="fa-solid fa-square-poll-vertical text-teal-600 mr-2"></i>Candidate Assessment Results</h1>
                    <p class="text-xs text-slate-500 mt-0.5">Review completed applicant assessment scores, response breakdowns, and audit event logs.</p>
                </div>
                <button onclick="downloadCSVExport()" class="bg-teal-600 hover:bg-teal-500 text-white font-semibold text-xs px-4 py-2 rounded-xl shadow flex items-center">
                    <i class="fa-solid fa-file-csv mr-2"></i>Export CSV
                </button>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 font-bold uppercase text-slate-500 text-[10px] tracking-wider">
                        <tr>
                            <th class="p-4">Candidate</th>
                            <th class="p-4">Exam Title</th>
                            <th class="p-4">Score</th>
                            <th class="p-4">Pass / Fail</th>
                            <th class="p-4">Submitted At</th>
                            <th class="p-4">Action</th>
                        </tr>
                    </thead>
                    <tbody id="admin-results-table-body">
                        <tr><td colspan="6" class="p-8 text-center text-slate-400">Loading results...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;

    try {
        const res = await fetch('/api/results');
        const results = await res.json();
        const tbody = document.getElementById('admin-results-table-body');

        if (!results || results.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-slate-400">No completed assessment results found.</td></tr>`;
            return;
        }

        tbody.innerHTML = results.map(r => `
            <tr class="border-b border-slate-100 hover:bg-slate-50/80 transition">
                <td class="p-4">
                    <div class="font-bold text-slate-900">${r.applicant_name}</div>
                    <div class="text-[11px] text-slate-400 font-mono">${r.applicant_email}</div>
                </td>
                <td class="p-4 font-semibold text-slate-800">${r.exam_title}</td>
                <td class="p-4 font-bold text-blue-600 text-sm">${r.percentage_score}% <span class="text-xs text-slate-400 font-normal">(${r.total_score}/${r.max_score})</span></td>
                <td class="p-4">
                    ${r.passed ? '<span class="bg-green-100 text-green-800 text-[10px] font-bold px-2.5 py-1 rounded-full">PASSED</span>' : '<span class="bg-red-100 text-red-800 text-[10px] font-bold px-2.5 py-1 rounded-full">FAILED</span>'}
                </td>
                <td class="p-4 text-slate-500 font-mono text-[11px]">${r.submitted_at}</td>
                <td class="p-4">
                    <button onclick="viewResult(${r.attempt_id})" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-3 py-1.5 rounded-lg text-[11px]">
                        Review Attempt
                    </button>
                </td>
            </tr>
        `).join('');

    } catch (err) {
        showToast('Error loading results table', 'error');
    }
}


// UI MODAL & TOAST HELPERS
function showModal(contentHtml) {
    const backdrop = document.getElementById('modal-backdrop');
    const container = document.getElementById('modal-content');
    container.innerHTML = contentHtml;
    backdrop.classList.remove('hidden');
}

function closeModal() {
    const backdrop = document.getElementById('modal-backdrop');
    backdrop.classList.add('hidden');
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const icon = document.getElementById('toast-icon');
    const msg = document.getElementById('toast-msg');

    msg.innerText = message;
    if (type === 'success') {
        icon.className = "fa-solid fa-circle-check text-green-400 text-lg";
    } else if (type === 'error') {
        icon.className = "fa-solid fa-circle-xmark text-red-400 text-lg";
    } else {
        icon.className = "fa-solid fa-circle-info text-blue-400 text-lg";
    }

    toast.classList.remove('translate-y-20', 'opacity-0');
    toast.classList.add('translate-y-0', 'opacity-100');

    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-20', 'opacity-0');
    }, 3500);
}
