import os
import pytest
from app.models import (
    init_db, SessionLocal, target_engine, DBARequest, RequestType, RequestStatus
)
from app.engine import SQLGenerator, AutomationEngine, SchemaInspector

@pytest.fixture(autouse=True)
def clean_target_db():
    init_db()
    # Drop all user tables created during tests
    with target_engine.connect() as conn:
        inspector = SchemaInspector.get_full_schema()
        for tbl in inspector["tables"]:
            conn.exec_driver_sql(f"DROP TABLE IF EXISTS {tbl['table_name']};")
        conn.commit()

def test_sql_generator_create_table():
    details = {
        "table_name": "products",
        "columns": [
            {"name": "id", "type": "INTEGER", "primary_key": True, "autoincrement": True},
            {"name": "title", "type": "VARCHAR(255)", "not_null": True},
            {"name": "price", "type": "REAL", "default": "0.0"}
        ]
    }
    sql = SQLGenerator.generate_sql(RequestType.CREATE_TABLE, details)
    assert "CREATE TABLE products" in sql
    assert "id INTEGER PRIMARY KEY AUTOINCREMENT" in sql
    assert "title VARCHAR(255) NOT NULL" in sql
    assert "price REAL DEFAULT '0.0'" in sql

def test_sql_generator_alter_table():
    details = {
        "table_name": "products",
        "action": "ADD_COLUMN",
        "column": {"name": "stock", "type": "INTEGER", "default": "0"}
    }
    sql = SQLGenerator.generate_sql(RequestType.ALTER_TABLE, details)
    assert sql == "ALTER TABLE products ADD COLUMN stock INTEGER DEFAULT '0';"

def test_sql_generator_grant_access():
    details = {
        "username": "data_dev",
        "target_object": "products",
        "permission": "READ"
    }
    sql = SQLGenerator.generate_sql(RequestType.GRANT_ACCESS, details)
    assert "INSERT INTO _dba_user_permissions" in sql
    assert "data_dev" in sql
    assert "products" in sql

def test_automation_engine_execute():
    db = SessionLocal()
    req = DBARequest(
        developer_name="TestDev",
        request_type=RequestType.CREATE_TABLE,
        title="Create test_logs table",
        details={
            "table_name": "test_logs",
            "columns": [{"name": "id", "type": "INTEGER", "primary_key": True}]
        }
    )
    db.add(req)
    db.commit()

    success = AutomationEngine.execute_request(db, req)
    assert success is True
    assert req.status == RequestStatus.COMPLETED
    assert req.executed_at is not None

    schema = SchemaInspector.get_full_schema()
    table_names = [t["table_name"] for t in schema["tables"]]
    assert "test_logs" in table_names
