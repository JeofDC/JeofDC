/* public/assets/js/quiz.js - Interactive Assessment Logic */

let quizData = null;
let currentQuestionIndex = 0;
let userAnswers = {}; // Map of question_id => selected_option_id
let userInfo = { name: '', email: '' };

document.addEventListener('DOMContentLoaded', () => {
    const userInfoForm = document.getElementById('user-info-form');
    if (userInfoForm) {
        userInfoForm.addEventListener('submit', handleUserInfoSubmit);
    }

    const btnNext = document.getElementById('btn-next');
    const btnPrev = document.getElementById('btn-prev');
    const btnSubmit = document.getElementById('btn-submit');

    if (btnNext) btnNext.addEventListener('click', handleNextQuestion);
    if (btnPrev) btnPrev.addEventListener('click', handlePrevQuestion);
    if (btnSubmit) btnSubmit.addEventListener('click', handleSubmitQuiz);
});

async function handleUserInfoSubmit(e) {
    e.preventDefault();
    const nameInput = document.getElementById('user_name').value.trim();
    const emailInput = document.getElementById('user_email').value.trim();

    if (!nameInput || !emailInput) {
        alert("Please enter both your name and email.");
        return;
    }

    userInfo.name = nameInput;
    userInfo.email = emailInput;

    document.getElementById('user-info-card').style.display = 'none';
    document.getElementById('quiz-card').style.display = 'block';

    await fetchQuizQuestions();
}

async function fetchQuizQuestions() {
    try {
        const response = await fetch('/api/quiz/get.php');
        const res = await response.json();

        if (!res.success || !res.data) {
            alert("Failed to load quiz assessment.");
            return;
        }

        quizData = res.data;
        currentQuestionIndex = 0;
        renderQuestion();

    } catch (err) {
        console.error(err);
        alert("Server error while loading questions.");
    }
}

function renderQuestion() {
    if (!quizData || !quizData.questions[currentQuestionIndex]) return;

    const totalQuestions = quizData.total_questions;
    const q = quizData.questions[currentQuestionIndex];

    // Counter & Progress
    document.getElementById('question-counter').textContent = `Question ${currentQuestionIndex + 1} of ${totalQuestions}`;
    const progressPct = Math.round(((currentQuestionIndex + 1) / totalQuestions) * 100);
    document.getElementById('progress-percentage').textContent = `${progressPct}% Completed`;
    document.getElementById('progress-bar').style.width = `${progressPct}%`;

    // Question Text
    document.getElementById('question-text').textContent = `${q.question_number}. ${q.question_text}`;

    // Options grid
    const optionsGrid = document.getElementById('options-grid');
    optionsGrid.innerHTML = '';

    q.options.forEach(opt => {
        const tile = document.createElement('div');
        tile.className = 'option-tile' + (userAnswers[q.id] === opt.id ? ' selected' : '');

        const isChecked = userAnswers[q.id] === opt.id ? 'checked' : '';

        tile.innerHTML = `
            <input type="radio" name="question_${q.id}" value="${opt.id}" class="option-radio" ${isChecked}>
            <span class="option-text">${escapeHtml(opt.option_text)}</span>
        `;

        tile.addEventListener('click', () => {
            userAnswers[q.id] = opt.id;

            // Highlight tile
            document.querySelectorAll('.option-tile').forEach(t => t.classList.remove('selected'));
            tile.classList.add('selected');

            const radio = tile.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        });

        optionsGrid.appendChild(tile);
    });

    // Navigation buttons
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnSubmit = document.getElementById('btn-submit');

    btnPrev.disabled = currentQuestionIndex === 0;

    if (currentQuestionIndex === totalQuestions - 1) {
        btnNext.style.display = 'none';
        btnSubmit.style.display = 'inline-flex';
    } else {
        btnNext.style.display = 'inline-flex';
        btnSubmit.style.display = 'none';
    }
}

function handleNextQuestion() {
    const q = quizData.questions[currentQuestionIndex];
    if (!userAnswers[q.id]) {
        alert("Please select an answer before proceeding.");
        return;
    }

    if (currentQuestionIndex < quizData.total_questions - 1) {
        currentQuestionIndex++;
        renderQuestion();
    }
}

function handlePrevQuestion() {
    if (currentQuestionIndex > 0) {
        currentQuestionIndex--;
        renderQuestion();
    }
}

async function handleSubmitQuiz() {
    const q = quizData.questions[currentQuestionIndex];
    if (!userAnswers[q.id]) {
        alert("Please select an answer for the final question.");
        return;
    }

    if (Object.keys(userAnswers).length < quizData.total_questions) {
        alert("Please complete all questions before submitting.");
        return;
    }

    const payload = {
        quiz_id: quizData.quiz.id,
        user_name: userInfo.name,
        user_email: userInfo.email,
        answers: userAnswers
    };

    try {
        const response = await fetch('/api/quiz/submit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const res = await response.json();

        if (!res.success || !res.data) {
            alert(res.message || "Failed to submit assessment.");
            return;
        }

        // Redirect to results page with attempt ID
        window.location.href = `/result.php?id=${res.data.attempt_id}`;

    } catch (err) {
        console.error(err);
        alert("Error submitting assessment.");
    }
}

function escapeHtml(str) {
    return str ? str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
}
