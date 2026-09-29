# tests/test_aptitude_app.py - Integration and API test suite for Aptitude Exam Application

import os
import time
import subprocess
import requests
import pytest

BASE_URL = "http://127.0.0.1:8080"

@pytest.fixture(scope="module", autouse=True)
def setup_server():
    # Run DB seed
    subprocess.run(["php", "database/seed.php"], check=True)

    # Start PHP built-in web server on port 8080
    env = os.environ.copy()
    server_proc = subprocess.Popen(
        ["php", "-S", "127.0.0.1:8080", "-t", "public"],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        env=env
    )
    time.sleep(1.5)  # Allow server time to start

    yield server_proc

    server_proc.terminate()
    server_proc.wait()

def test_login_and_csrf():
    session = requests.Session()

    # Get CSRF Token
    resp = session.get(f"{BASE_URL}/api/auth/csrf-token.php")
    assert resp.status_code == 200
    csrf_token = resp.json().get("csrf_token")
    assert csrf_token is not None

    # Login as Admin
    login_resp = session.post(f"{BASE_URL}/api/auth/login.php", json={
        "username": "admin",
        "password": "Admin123!"
    })
    assert login_resp.status_code == 200
    json_data = login_resp.json()
    assert json_data["success"] is True
    assert json_data["data"]["user"]["username"] == "admin"
    assert "Administrator" in json_data["data"]["user"]["roles"]

def test_question_bank_api():
    session = requests.Session()

    # Login as Examiner
    session.post(f"{BASE_URL}/api/auth/login.php", json={"username": "examiner", "password": "Examiner123!"})

    # Fetch Question List
    resp = session.get(f"{BASE_URL}/api/questions/list.php")
    assert resp.status_code == 200
    json_data = resp.json()
    assert json_data["success"] is True
    assert len(json_data["data"]) >= 30

def test_candidate_exam_flow():
    session = requests.Session()

    # Get CSRF Token
    resp_csrf = session.get(f"{BASE_URL}/api/auth/csrf-token.php")
    csrf_token = resp_csrf.json()["csrf_token"]

    # Login as Candidate
    session.post(f"{BASE_URL}/api/auth/login.php", json={"username": "candidate", "password": "Candidate123!"})

    # Get Assigned Exam ID (Sample Exam ID = 1)
    exam_id = 1

    # Start Exam Attempt
    start_resp = session.post(f"{BASE_URL}/api/attempts/start.php", json={
        "exam_id": exam_id,
        "csrf_token": csrf_token
    })
    assert start_resp.status_code == 200
    start_json = start_resp.json()
    assert start_json["success"] is True
    attempt_id = start_json["attempt_id"]
    assert attempt_id is not None

    # Fetch Current Attempt Questions
    curr_resp = session.get(f"{BASE_URL}/api/attempts/current.php?exam_id={exam_id}")
    assert curr_resp.status_code == 200
    curr_json = curr_resp.json()
    assert curr_json["success"] is True
    questions = curr_json["questions"]
    assert len(questions) == 30

    # Auto-save choices for all questions
    for q in questions:
        choice_id = q["choices"][0]["id"]
        save_resp = session.post(f"{BASE_URL}/api/attempts/save-answer.php", json={
            "attempt_id": attempt_id,
            "attempt_question_id": q["attempt_question_id"],
            "selected_choice_id": choice_id,
            "csrf_token": csrf_token
        })
        assert save_resp.status_code == 200
        assert save_resp.json()["success"] is True

    # Submit Examination
    sub_resp = session.post(f"{BASE_URL}/api/attempts/submit.php", json={
        "attempt_id": attempt_id,
        "csrf_token": csrf_token
    })
    assert sub_resp.status_code == 200
    sub_json = sub_resp.json()
    assert sub_json["success"] is True
    assert sub_json["data"]["total_questions"] == 30
    assert sub_json["data"]["percentage"] >= 0.0

    # Verify Result View
    res_resp = session.get(f"{BASE_URL}/api/results/list.php?attempt_id={attempt_id}")
    assert res_resp.status_code == 200
    res_json = res_resp.json()
    assert res_json["success"] is True
    assert len(res_json["category_scores"]) == 3
