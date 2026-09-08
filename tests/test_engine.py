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
    if target_engine.dialect.name == "sqlite":
        assert "id INTEGER PRIMARY KEY AUTOINCREMENT" in sql
    else:
        assert "id SERIAL PRIMARY KEY" in sql
    assert "title VARCHAR(255) NOT NULL" in sql
    assert "price REAL DEFAULT '0.0'" in sql

def test_sql_generator_alter_table_actions():
    # ADD_COLUMN
    add_sql = SQLGenerator.generate_sql(RequestType.ALTER_TABLE, {
        "table_name": "products",
        "action": "ADD_COLUMN",
        "column": {"name": "stock", "type": "INTEGER", "default": "0"}
    })
    assert add_sql == "ALTER TABLE products ADD COLUMN stock INTEGER DEFAULT '0';"

    # DROP_COLUMN
    drop_col_sql = SQLGenerator.generate_sql(RequestType.ALTER_TABLE, {
        "table_name": "products",
        "action": "DROP_COLUMN",
        "column_name": "stock"
    })
    assert drop_col_sql == "ALTER TABLE products DROP COLUMN stock;"

    # RENAME_COLUMN
    rename_col_sql = SQLGenerator.generate_sql(RequestType.ALTER_TABLE, {
        "table_name": "products",
        "action": "RENAME_COLUMN",
        "old_column_name": "title",
        "new_column_name": "product_name"
    })
    assert rename_col_sql == "ALTER TABLE products RENAME COLUMN title TO product_name;"

    # ALTER_TYPE
    alter_type_sql = SQLGenerator.generate_sql(RequestType.ALTER_TABLE, {
        "table_name": "products",
        "action": "ALTER_TYPE",
        "column_name": "price",
        "new_type": "NUMERIC"
    })
    assert alter_type_sql == "ALTER TABLE products ALTER COLUMN price TYPE NUMERIC;"

def test_sql_generator_grant_access():
    details = {
        "username": "data_dev",
        "target_object": "products",
        "permission": "READ"
    }
    sql = SQLGenerator.generate_sql(RequestType.GRANT_ACCESS, details)
    assert "GRANT SELECT ON TABLE products TO data_dev;" in sql
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
