import pytest
from fastapi.testclient import TestClient
from app.main import app
from app.models import init_db, target_engine
from app.engine import SchemaInspector

client = TestClient(app)

@pytest.fixture(autouse=True)
def clean_target_db():
    init_db()
    with target_engine.connect() as conn:
        inspector = SchemaInspector.get_full_schema()
        for tbl in inspector["tables"]:
            conn.exec_driver_sql(f"DROP TABLE IF EXISTS {tbl['table_name']};")
        conn.commit()

def test_api_create_and_preview_sql():
    # Test preview SQL endpoint
    preview_resp = client.post("/api/preview-sql", json={
        "request_type": "CREATE_TABLE",
        "details": {
            "table_name": "categories",
            "columns": [{"name": "id", "type": "INTEGER", "primary_key": True}]
        }
    })
    assert preview_resp.status_code == 200
    assert "CREATE TABLE categories" in preview_resp.json()["sql"]

    # Test create request endpoint
    create_resp = client.post("/api/requests", json={
        "developer_name": "Bob",
        "request_type": "CREATE_TABLE",
        "title": "Create categories table",
        "description": "Catalog classification",
        "details": {
            "table_name": "categories",
            "columns": [{"name": "id", "type": "INTEGER", "primary_key": True}]
        }
    })
    assert create_resp.status_code == 201
    data = create_resp.json()
    assert data["status"] == "PENDING"
    assert data["developer_name"] == "Bob"

def test_api_approval_flow():
    # 1. Create request
    create_resp = client.post("/api/requests", json={
        "developer_name": "Charlie",
        "request_type": "CREATE_TABLE",
        "title": "Create customers table",
        "details": {
            "table_name": "customers",
            "columns": [{"name": "id", "type": "INTEGER", "primary_key": True}]
        }
    })
    req_id = create_resp.json()["id"]

    # 2. Approve request as DBA
    approve_resp = client.post(f"/api/requests/{req_id}/approve", json={"dba_notes": "Approved by Senior DBA"})
    assert approve_resp.status_code == 200
    app_data = approve_resp.json()
    assert app_data["status"] == "COMPLETED"
    assert app_data["dba_notes"] == "Approved by Senior DBA"

    # 3. Verify in schema
    schema_resp = client.get("/api/schema")
    assert schema_resp.status_code == 200
    tbl_names = [t["table_name"] for t in schema_resp.json()["tables"]]
    assert "customers" in tbl_names

def test_api_rejection_flow():
    # Create request
    create_resp = client.post("/api/requests", json={
        "developer_name": "Dave",
        "request_type": "DROP_TABLE",
        "title": "Drop customers table",
        "details": {"table_name": "customers"}
    })
    req_id = create_resp.json()["id"]

    # Reject
    reject_resp = client.post(f"/api/requests/{req_id}/reject", json={"dba_notes": "Destructive change not allowed."})
    assert reject_resp.status_code == 200
    assert reject_resp.json()["status"] == "REJECTED"

def test_api_cancellation_flow():
    create_resp = client.post("/api/requests", json={
        "developer_name": "Eve",
        "request_type": "GRANT_ACCESS",
        "title": "Grant read on customers",
        "details": {"username": "eve", "target_object": "customers", "permission": "READ"}
    })
    req_id = create_resp.json()["id"]

    cancel_resp = client.post(f"/api/requests/{req_id}/cancel")
    assert cancel_resp.status_code == 200
    assert cancel_resp.json()["status"] == "CANCELLED"

def test_api_postgres_sql_previews():
    # Grant Access preview
    grant_preview = client.post("/api/preview-sql", json={
        "request_type": "GRANT_ACCESS",
        "details": {"username": "pg_dev", "target_object": "orders", "permission": "READ"}
    })
    assert grant_preview.status_code == 200
    assert "GRANT SELECT ON TABLE orders TO pg_dev;" in grant_preview.json()["sql"]

    # Alter Table drop column preview
    alter_preview = client.post("/api/preview-sql", json={
        "request_type": "ALTER_TABLE",
        "details": {"table_name": "orders", "action": "DROP_COLUMN", "column_name": "temp_col"}
    })
    assert alter_preview.status_code == 200
    assert alter_preview.json()["sql"] == "ALTER TABLE orders DROP COLUMN temp_col;"
