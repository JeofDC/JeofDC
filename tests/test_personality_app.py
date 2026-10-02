import os
import subprocess
import time
import requests
import pytest

SERVER_URL = "http://127.0.0.1:8089"

@pytest.fixture(scope="module", autouse=True)
def run_php_server():
    # Ensure database is freshly seeded
    subprocess.run(["php", "database/seed.php"], check=True)

    # Start PHP built-in web server
    server_process = subprocess.Popen(
        ["php", "-S", "127.0.0.1:8089", "-t", "public"],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )
    time.sleep(1) # Give server time to bind

    yield server_process

    server_process.terminate()
    server_process.wait()

def test_homepage_availability():
    res = requests.get(f"{SERVER_URL}/index.php")
    assert res.status_code == 200
    assert "DISC Personality Center" in res.text

def test_quiz_get_api():
    res = requests.get(f"{SERVER_URL}/api/quiz/get.php")
    assert res.status_code == 200
    data = res.json()
    assert data["success"] is True
    assert data["data"]["total_questions"] == 12
    assert len(data["data"]["questions"]) == 12

def test_quiz_submit_and_result_flow():
    # First get questions and quiz ID
    q_res = requests.get(f"{SERVER_URL}/api/quiz/get.php")
    quiz_info = q_res.json()["data"]["quiz"]
    questions = q_res.json()["data"]["questions"]

    # Select first option for each question
    answers = {}
    for q in questions:
        answers[str(q["id"])] = q["options"][0]["id"]

    payload = {
        "quiz_id": quiz_info["id"],
        "user_name": "Test Candidate",
        "user_email": "candidate@example.com",
        "answers": answers
    }

    # Submit
    sub_res = requests.post(f"{SERVER_URL}/api/quiz/submit.php", json=payload)
    assert sub_res.status_code == 200
    sub_data = sub_res.json()
    assert sub_data["success"] is True
    attempt_id = sub_data["data"]["attempt_id"]

    # Fetch Result
    res_res = requests.get(f"{SERVER_URL}/api/quiz/result.php?id={attempt_id}")
    assert res_res.status_code == 200
    res_data = res_res.json()
    assert res_data["success"] is True
    assert res_data["data"]["attempt"]["user_name"] == "Test Candidate"
    assert "primary_dimension" in res_data["data"]

def test_history_api():
    res = requests.get(f"{SERVER_URL}/api/quiz/history.php?query=candidate@example.com")
    assert res.status_code == 200
    data = res.json()
    assert data["success"] is True
    assert len(data["data"]) >= 1
    assert data["data"][0]["user_email"] == "candidate@example.com"
