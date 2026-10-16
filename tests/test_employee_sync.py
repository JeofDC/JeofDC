import pytest
from unittest.mock import patch, MagicMock
from fastapi.testclient import TestClient
from app.main import app
from app.models import init_db, SessionLocal, Employee, SyncLog, app_engine

client = TestClient(app)

@pytest.fixture(autouse=True)
def setup_db():
    init_db()
    db = SessionLocal()
    db.query(Employee).delete()
    db.query(SyncLog).delete()
    db.commit()
    db.close()

MOCK_DARWINBOX_RESPONSE = {
    "status": 1,
    "message": "Employees fetched successfully",
    "data": [
        {
            "employee_id": "EMP1001",
            "first_name": "Jane",
            "last_name": "Doe",
            "email": "jane.doe@example.com",
            "department": "Engineering",
            "designation": "Senior Developer",
            "status": "Active"
        },
        {
            "employee_id": "EMP1002",
            "first_name": "John",
            "last_name": "Smith",
            "email": "john.smith@example.com",
            "department": "HR",
            "designation": "HR Manager",
            "status": "Active"
        }
    ]
}

@patch("requests.post")
def test_employee_sync_trigger_api(mock_post):
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.json.return_value = MOCK_DARWINBOX_RESPONSE
    mock_post.return_value = mock_response

    payload = {
        "api_key": "test_api_key",
        "datasetKey": "test_dataset_key",
        "authorization": "Basic test_auth_token"
    }

    resp = client.post("/api/employee-sync/trigger", json=payload)
    assert resp.status_code == 200
    data = resp.json()
    assert data["status"] == "SUCCESS"
    assert data["total_records"] == 2

    # Verify employees in database
    emp_resp = client.get("/api/employees")
    assert emp_resp.status_code == 200
    employees = emp_resp.json()
    assert len(employees) == 2
    emp_ids = [e["employee_id"] for e in employees]
    assert "EMP1001" in emp_ids
    assert "EMP1002" in emp_ids

    # Verify sync logs
    logs_resp = client.get("/api/employee-sync/logs")
    assert logs_resp.status_code == 200
    logs = logs_resp.json()
    assert len(logs) == 1
    assert logs[0]["status"] == "SUCCESS"
    assert logs[0]["total_records"] == 2

@patch("requests.post")
def test_employee_sync_search_and_filter(mock_post):
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.json.return_value = MOCK_DARWINBOX_RESPONSE
    mock_post.return_value = mock_response

    client.post("/api/employee-sync/trigger", json={
        "api_key": "k", "datasetKey": "d", "authorization": "a"
    })

    # Search query
    search_resp = client.get("/api/employees?query=Jane")
    assert search_resp.status_code == 200
    results = search_resp.json()
    assert len(results) == 1
    assert results[0]["first_name"] == "Jane"

    # Filter by department
    dept_resp = client.get("/api/employees?department=HR")
    assert dept_resp.status_code == 200
    dept_results = dept_resp.json()
    assert len(dept_results) == 1
    assert dept_results[0]["department"] == "HR"
