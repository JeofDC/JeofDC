import pytest
from fastapi.testclient import TestClient
from app.main import app
from app.models import init_db, target_engine, RequestType
from app.engine import SQLGenerator, SchemaInspector

client = TestClient(app)

@pytest.fixture(autouse=True)
def clean_target_db():
    init_db()
    with target_engine.connect() as conn:
        conn.exec_driver_sql("DELETE FROM _dba_roles;")
        conn.exec_driver_sql("DELETE FROM _dba_role_memberships;")
        conn.exec_driver_sql("DELETE FROM _dba_user_permissions;")
        conn.commit()

def test_sql_generator_rbac_statements():
    # 1. CREATE_ROLE
    sql_role = SQLGenerator.generate_sql(RequestType.CREATE_ROLE, {
        "role_name": "dw_analyst",
        "can_login": False
    })
    assert "CREATE ROLE dw_analyst WITH NOLOGIN;" in sql_role
    assert "INSERT INTO _dba_roles" in sql_role

    sql_user = SQLGenerator.generate_sql(RequestType.CREATE_ROLE, {
        "role_name": "alice_user",
        "can_login": True,
        "password": "SecretPassword123"
    })
    assert "CREATE ROLE alice_user WITH LOGIN WITH PASSWORD 'SecretPassword123';" in sql_user

    # 2. ASSIGN_ROLE
    sql_assign = SQLGenerator.generate_sql(RequestType.ASSIGN_ROLE, {
        "member_name": "alice_user",
        "parent_role": "dw_analyst"
    })
    assert "GRANT dw_analyst TO alice_user;" in sql_assign
    assert "INSERT INTO _dba_role_memberships" in sql_assign

    # 3. GRANT_PRIVILEGE
    sql_grant = SQLGenerator.generate_sql(RequestType.GRANT_PRIVILEGE, {
        "grantee": "dw_analyst",
        "schema_name": "public",
        "target_type": "ALL TABLES",
        "privileges": "SELECT"
    })
    assert "GRANT SELECT ON ALL TABLES IN SCHEMA public TO dw_analyst;" in sql_grant

    # 4. REVOKE_PRIVILEGE
    sql_revoke = SQLGenerator.generate_sql(RequestType.REVOKE_PRIVILEGE, {
        "grantee": "dw_analyst",
        "schema_name": "public",
        "target_type": "ALL TABLES",
        "privileges": "SELECT"
    })
    assert "REVOKE SELECT ON ALL TABLES IN SCHEMA public FROM dw_analyst;" in sql_revoke

    # 5. SET_DEFAULT_PRIVILEGES
    sql_def = SQLGenerator.generate_sql(RequestType.SET_DEFAULT_PRIVILEGES, {
        "grantee": "dw_analyst",
        "schema_name": "public",
        "privileges": "SELECT"
    })
    assert "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO dw_analyst;" in sql_def

def test_rbac_full_flow():
    # 1. Create Role Request & Approve
    create_req = client.post("/api/requests", json={
        "developer_name": "DataArchitect",
        "request_type": "CREATE_ROLE",
        "title": "Create DW Analyst role",
        "details": {"role_name": "dw_analyst", "can_login": False}
    })
    assert create_req.status_code == 201
    req_id1 = create_req.json()["id"]

    approve1 = client.post(f"/api/requests/{req_id1}/approve")
    assert approve1.status_code == 200
    assert approve1.json()["status"] == "COMPLETED"

    # 2. Assign Role Request & Approve
    assign_req = client.post("/api/requests", json={
        "developer_name": "DataArchitect",
        "request_type": "ASSIGN_ROLE",
        "title": "Assign dw_analyst to bob",
        "details": {"member_name": "bob_user", "parent_role": "dw_analyst"}
    })
    req_id2 = assign_req.json()["id"]
    client.post(f"/api/requests/{req_id2}/approve")

    # 3. Grant Privilege Request & Approve
    grant_req = client.post("/api/requests", json={
        "developer_name": "DataArchitect",
        "request_type": "GRANT_PRIVILEGE",
        "title": "Grant SELECT on public tables to dw_analyst",
        "details": {
            "grantee": "dw_analyst",
            "schema_name": "public",
            "target_type": "ALL TABLES",
            "privileges": "SELECT"
        }
    })
    req_id3 = grant_req.json()["id"]
    approve3 = client.post(f"/api/requests/{req_id3}/approve")
    assert approve3.status_code == 200
    assert approve3.json()["status"] == "COMPLETED"

    # 4. Verify matrix endpoint
    matrix_resp = client.get("/api/rbac/matrix")
    assert matrix_resp.status_code == 200
    m_data = matrix_resp.json()

    role_names = [r["role_name"] for r in m_data["roles"]]
    assert "dw_analyst" in role_names

    memberships = m_data["memberships"]
    assert any(m["member_name"] == "bob_user" and m["parent_role"] == "dw_analyst" for m in memberships)

    permissions = m_data["permissions"]
    assert any(p["grantee"] == "dw_analyst" and p["permission_level"] == "SELECT" for p in permissions)

    assert "bob_user" in m_data["inheritance"]
    assert "dw_analyst" in m_data["inheritance"]["bob_user"]
