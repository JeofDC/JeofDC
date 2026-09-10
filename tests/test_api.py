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

def test_api_xlsx_preview_and_import():
    import io
    import pandas as pd

    df = pd.DataFrame({"id": [1, 2], "label": ["A", "B"]})
    buf = io.BytesIO()
    with pd.ExcelWriter(buf, engine="openpyxl") as writer:
        df.to_excel(writer, sheet_name="Data", index=False)
    file_bytes = buf.getvalue()

    # Test preview API endpoint
    preview_resp = client.post(
        "/api/xlsx/preview",
        files={"file": ("test.xlsx", file_bytes, "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")}
    )
    assert preview_resp.status_code == 200
    p_data = preview_resp.json()
    assert p_data["columns"] == ["id", "label"]
    assert p_data["total_rows"] == 2

    # Test import API endpoint
    import_resp = client.post(
        "/api/xlsx/import",
        data={"table_name": "api_import_tbl", "if_exists": "replace"},
        files={"file": ("test.xlsx", file_bytes, "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")}
    )
    assert import_resp.status_code == 200
    i_data = import_resp.json()
    assert i_data["success"] is True
    assert i_data["rows_imported"] == 2

def test_api_xlsx_export():
    import io
    import pandas as pd

    df = pd.DataFrame({"code": ["X1", "X2"], "value": [10, 20]})
    buf = io.BytesIO()
    with pd.ExcelWriter(buf, engine="openpyxl") as writer:
        df.to_excel(writer, index=False)
    file_bytes = buf.getvalue()

    # Seed data via import
    client.post(
        "/api/xlsx/import",
        data={"table_name": "api_export_tbl", "if_exists": "replace"},
        files={"file": ("test.xlsx", file_bytes, "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")}
    )

    # Test export API endpoint
    export_resp = client.get("/api/xlsx/export/api_export_tbl")
    assert export_resp.status_code == 200
    assert export_resp.headers["content-type"] == "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    assert len(export_resp.content) > 0

def test_api_db_config():
    # Test GET db-config
    get_resp = client.get("/api/db-config")
    assert get_resp.status_code == 200
    assert get_resp.json()["db_type"] in ["postgresql", "sqlite"]

    # Test POST db-config (switch to SQLite for local test environment)
    post_resp = client.post("/api/db-config", json={
        "db_type": "sqlite",
        "server": "",
        "port": "",
        "dbname": "",
        "user": "",
        "password": "",
        "schema": "main"
    })
    assert post_resp.status_code == 200
    assert post_resp.json()["success"] is True

def test_api_folder_scan_and_filepath_import(tmp_path):
    # Create sample CSV file in temp directory
    test_csv = tmp_path / "sample_data.csv"
    test_csv.write_text("id,name\n1,Alpha\n2,Beta")

    # Test scan endpoint
    scan_resp = client.post("/api/folder/scan", json={"folder_path": str(tmp_path)})
    assert scan_resp.status_code == 200
    files = scan_resp.json()["files"]
    assert len(files) == 1
    assert files[0]["filename"] == "sample_data.csv"

    # Test import using filepath
    import_resp = client.post(
        "/api/xlsx/import",
        data={"filepath": str(test_csv), "table_name": "folder_csv_tbl", "if_exists": "replace"}
    )
    assert import_resp.status_code == 200
    assert import_resp.json()["rows_imported"] == 2
