import pytest
from fastapi.testclient import TestClient
from app.main import app
from app.models import init_db, Base, engine, SessionLocal

client = TestClient(app)

@pytest.fixture(autouse=True)
def clean_db():
    Base.metadata.drop_all(bind=engine)
    init_db()

def test_home_page_and_static_files():
    # Home HTML route
    resp = client.get("/")
    assert resp.status_code == 200
    assert "Aptitude Hub" in resp.text

    # Static CSS
    css_resp = client.get("/static/style.css")
    assert css_resp.status_code == 200

    # Static JS
    js_resp = client.get("/static/app.js")
    assert js_resp.status_code == 200

def test_user_and_auth_api():
    # Get current user
    resp = client.get("/api/users/me")
    assert resp.status_code == 200
    user_data = resp.json()
    assert "email" in user_data

    # List all users
    users_resp = client.get("/api/users")
    assert users_resp.status_code == 200
    assert len(users_resp.json()) >= 4

    # Switch user
    switch_resp = client.post("/api/auth/switch-user", json={"user_id": 2})
    assert switch_resp.status_code == 200
    assert switch_resp.json()["user"]["id"] == 2

def test_applicant_management_api():
    # List applicants
    resp = client.get("/api/applicants")
    assert resp.status_code == 200
    apps = resp.json()
    assert len(apps) >= 3

    # Register new applicant
    create_resp = client.post("/api/applicants", json={
        "full_name": "Alice Smith",
        "email": "alice.smith@example.com",
        "applied_position": "Product Manager",
        "phone": "555-9988"
    })
    assert create_resp.status_code == 201
    assert "id" in create_resp.json()

def test_questions_bank_api():
    # List questions with category filter
    resp = client.get("/api/questions?category=Abstract Reasoning")
    assert resp.status_code == 200
    questions = resp.json()
    assert len(questions) > 0
    assert all(q["category"] == "Abstract Reasoning" for q in questions)

    # Create question
    create_resp = client.post("/api/questions", json={
        "category": "Verbal Reasoning",
        "subcategory": "Vocabulary",
        "difficulty": "Easy",
        "question_type": "multiple_choice",
        "question_text": "What is a synonym for 'rapid'?",
        "points": 1.0,
        "choices": [
            {"id": "a", "text": "Fast"},
            {"id": "b", "text": "Slow"}
        ],
        "correct_answer": "a",
        "explanation": "'Rapid' means moving fast."
    })
    assert create_resp.status_code == 201
    q_id = create_resp.json()["id"]

    # Get single question
    get_q = client.get(f"/api/questions/{q_id}")
    assert get_q.status_code == 200
    assert get_q.json()["question_text"] == "What is a synonym for 'rapid'?"

    # Update question
    up_resp = client.put(f"/api/questions/{q_id}", json={
        "category": "Verbal Reasoning",
        "subcategory": "Vocabulary",
        "difficulty": "Easy",
        "question_type": "multiple_choice",
        "question_text": "What is a synonym for 'swift'?",
        "points": 1.0,
        "choices": [
            {"id": "a", "text": "Fast"},
            {"id": "b", "text": "Slow"}
        ],
        "correct_answer": "a",
        "explanation": "'Swift' means moving fast."
    })
    assert up_resp.status_code == 200

    # Delete question
    del_resp = client.delete(f"/api/questions/{q_id}")
    assert del_resp.status_code == 200

def test_exam_builder_and_assignment_api():
    # List exams
    exams_resp = client.get("/api/exams")
    assert exams_resp.status_code == 200
    assert len(exams_resp.json()) >= 2

    # Create exam
    create_exam = client.post("/api/exams", json={
        "title": "New Logic Assessment",
        "description": "Test exam builder",
        "duration_minutes": 15,
        "passing_score": 70.0,
        "question_ids": [1, 2, 3]
    })
    assert create_exam.status_code == 201
    exam_id = create_exam.json()["id"]

    # Duplicate exam
    dup_resp = client.post(f"/api/exams/{exam_id}/duplicate")
    assert dup_resp.status_code == 200
    assert "id" in dup_resp.json()

    # Toggle publish & archive
    pub_resp = client.post(f"/api/exams/{exam_id}/publish")
    assert pub_resp.status_code == 200

    arc_resp = client.post(f"/api/exams/{exam_id}/archive")
    assert arc_resp.status_code == 200

    # Assign exam to applicant (user_id=2)
    assign_resp = client.post("/api/assignments", json={
        "exam_id": exam_id,
        "applicant_id": 2,
        "due_days": 5
    })
    assert assign_resp.status_code == 201
    code = assign_resp.json()["access_code"]

    # Lookup assignment code
    lookup = client.get(f"/api/assignments/code/{code}")
    assert lookup.status_code == 200
    assert lookup.json()["exam_title"] == "New Logic Assessment"

def test_candidate_assessment_flow():
    # Use code EVAL-JANE-2024 from seeds (NOT_STARTED)
    start_resp = client.post("/api/assessments/start", json={"access_code": "EVAL-JANE-2024"})
    assert start_resp.status_code == 200
    data = start_resp.json()
    attempt_id = data["attempt_id"]
    assert data["total_questions"] > 0
    assert "questions" in data
    first_q = data["questions"][0]
    assert "correct_answer" not in first_q # Candidate view safety masking

    # Save choice answer
    save_resp = client.post("/api/assessments/save-answer", json={
        "attempt_id": attempt_id,
        "question_id": first_q["id"],
        "selected_choice": "a"
    })
    assert save_resp.status_code == 200

    # Toggle mark for review
    review_resp = client.post("/api/assessments/toggle-review", json={
        "attempt_id": attempt_id,
        "question_id": first_q["id"],
        "is_marked_for_review": True
    })
    assert review_resp.status_code == 200

    # Get attempt state
    attempt_state = client.get(f"/api/assessments/attempt/{attempt_id}")
    assert attempt_state.status_code == 200
    assert attempt_state.json()["marked_for_review"] == [first_q["id"]]

    # Submit assessment
    submit_resp = client.post("/api/assessments/submit", json={"attempt_id": attempt_id})
    assert submit_resp.status_code == 200
    assert "summary" in submit_resp.json()

def test_results_analytics_and_csv_export():
    res_list = client.get("/api/results")
    assert res_list.status_code == 200
    assert len(res_list.json()) > 0

    single_res = client.get("/api/results/1")
    assert single_res.status_code == 200
    assert "question_review" in single_res.json()
    assert "event_logs" in single_res.json()

    analytics = client.get("/api/analytics")
    assert analytics.status_code == 200
    assert "average_score" in analytics.json()

    csv_export = client.get("/api/results/export/csv")
    assert csv_export.status_code == 200
    assert csv_export.headers["content-type"] == "text/csv; charset=utf-8"
