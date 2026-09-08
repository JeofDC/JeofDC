import datetime
from sqlalchemy import inspect, text
from app.models import (
    target_engine, RequestType, RequestStatus, DBARequest, ExecutionLog
)

class SQLGenerator:
    """Generates valid SQL DDL/DML statements based on request types and structured parameters."""

    @staticmethod
    def generate_sql(request_type: str, details: dict) -> str:
        if request_type == RequestType.CREATE_TABLE:
            table_name = details.get("table_name", "").strip()
            columns = details.get("columns", [])
            if not table_name:
                raise ValueError("Table name is required for CREATE_TABLE.")
            if not columns:
                raise ValueError("At least one column is required for CREATE_TABLE.")

            is_sqlite = target_engine.dialect.name == "sqlite"

            col_defs = []
            for col in columns:
                c_name = col.get("name", "").strip()
                c_type = col.get("type", "TEXT").upper().strip()
                c_pk = col.get("primary_key", False)
                c_auto = col.get("autoincrement", False)
                c_nn = col.get("not_null", False)
                c_unique = col.get("unique", False)
                c_default = col.get("default")

                if is_sqlite:
                    # SQLite autoincrement syntax
                    c_pk_str = " PRIMARY KEY" if c_pk else ""
                    c_auto_str = " AUTOINCREMENT" if c_auto and c_pk and c_type in ["INTEGER", "INT"] else ""
                    c_nn_str = " NOT NULL" if c_nn and not c_pk else ""
                    c_unique_str = " UNIQUE" if c_unique else ""
                    c_default_str = f" DEFAULT '{c_default}'" if c_default is not None and c_default != "" else ""
                    col_defs.append(f"  {c_name} {c_type}{c_pk_str}{c_auto_str}{c_nn_str}{c_unique_str}{c_default_str}")
                else:
                    # PostgreSQL syntax: SERIAL for auto-increment PKs
                    if c_pk and (c_auto or c_type in ["SERIAL", "BIGSERIAL"]):
                        if c_type in ["BIGINT", "BIGSERIAL"]:
                            col_defs.append(f"  {c_name} BIGSERIAL PRIMARY KEY")
                        else:
                            col_defs.append(f"  {c_name} SERIAL PRIMARY KEY")
                    else:
                        c_pk_str = " PRIMARY KEY" if c_pk else ""
                        c_nn_str = " NOT NULL" if c_nn and not c_pk else ""
                        c_unique_str = " UNIQUE" if c_unique else ""
                        c_default_str = f" DEFAULT '{c_default}'" if c_default is not None and c_default != "" else ""
                        col_defs.append(f"  {c_name} {c_type}{c_pk_str}{c_nn_str}{c_unique_str}{c_default_str}")

            sql = f"CREATE TABLE {table_name} (\n" + ",\n".join(col_defs) + "\n);"
            return sql

        elif request_type == RequestType.ALTER_TABLE:
            table_name = details.get("table_name", "").strip()
            action = details.get("action", "ADD_COLUMN") # ADD_COLUMN, RENAME_TABLE, DROP_COLUMN, RENAME_COLUMN, ALTER_TYPE
            if not table_name:
                raise ValueError("Table name is required for ALTER_TABLE.")

            if action == "ADD_COLUMN":
                col = details.get("column", {})
                c_name = col.get("name", "").strip()
                c_type = col.get("type", "TEXT").upper().strip()
                c_nn = " NOT NULL" if col.get("not_null") else ""
                c_default = f" DEFAULT '{col.get('default')}'" if col.get("default") is not None and col.get("default") != "" else ""
                return f"ALTER TABLE {table_name} ADD COLUMN {c_name} {c_type}{c_nn}{c_default};"

            elif action == "RENAME_TABLE":
                new_name = details.get("new_table_name", "").strip()
                if not new_name:
                    raise ValueError("New table name is required for RENAME_TABLE.")
                return f"ALTER TABLE {table_name} RENAME TO {new_name};"

            elif action == "DROP_COLUMN":
                c_name = details.get("column_name", "").strip()
                if not c_name:
                    raise ValueError("Column name is required for DROP_COLUMN.")
                return f"ALTER TABLE {table_name} DROP COLUMN {c_name};"

            elif action == "RENAME_COLUMN":
                old_name = details.get("old_column_name", "").strip()
                new_name = details.get("new_column_name", "").strip()
                if not old_name or not new_name:
                    raise ValueError("Old and new column names are required for RENAME_COLUMN.")
                return f"ALTER TABLE {table_name} RENAME COLUMN {old_name} TO {new_name};"

            elif action == "ALTER_TYPE":
                c_name = details.get("column_name", "").strip()
                new_type = details.get("new_type", "TEXT").upper().strip()
                if not c_name:
                    raise ValueError("Column name is required for ALTER_TYPE.")
                return f"ALTER TABLE {table_name} ALTER COLUMN {c_name} TYPE {new_type};"

            else:
                raise ValueError(f"Unsupported alter table action: {action}")

        elif request_type == RequestType.DROP_TABLE:
            table_name = details.get("table_name", "").strip()
            if not table_name:
                raise ValueError("Table name is required for DROP_TABLE.")
            if target_engine.dialect.name == "sqlite":
                return f"DROP TABLE IF EXISTS {table_name};"
            else:
                return f"DROP TABLE IF EXISTS {table_name} CASCADE;"

        elif request_type == RequestType.GRANT_ACCESS:
            username = details.get("username", "").strip()
            target_object = details.get("target_object", "").strip() # e.g. table name or *
            permission = details.get("permission", "READ").upper().strip() # READ, WRITE, ADMIN
            if not username or not target_object:
                raise ValueError("Username and Target Object are required for GRANT_ACCESS.")

            if permission == "READ":
                privs = "SELECT"
            elif permission == "WRITE":
                privs = "SELECT, INSERT, UPDATE, DELETE"
            elif permission == "ADMIN":
                privs = "ALL PRIVILEGES"
            else:
                privs = permission

            if target_object == "*" or target_object.upper() == "ALL TABLES":
                grant_stmt = f"GRANT {privs} ON ALL TABLES IN SCHEMA public TO {username};"
            else:
                grant_stmt = f"GRANT {privs} ON TABLE {target_object} TO {username};"

            audit_stmt = f"INSERT INTO _dba_user_permissions (username, target_object, permission_level) VALUES ('{username}', '{target_object}', '{permission}');"

            return f"{grant_stmt}\n{audit_stmt}"

        elif request_type == RequestType.CUSTOM_SQL:
            sql_query = details.get("sql_query", "").strip()
            if not sql_query:
                raise ValueError("SQL query cannot be empty for CUSTOM_SQL.")
            return sql_query

        else:
            raise ValueError(f"Unknown request type: {request_type}")

class AutomationEngine:
    """Validates, processes, and automatically executes approved DBA requests on target databases."""

    @staticmethod
    def execute_request(db_session, request_obj: DBARequest) -> bool:
        """
        Executes the SQL associated with the DBA request in a safe database transaction.
        Updates request status and appends execution logs.
        """
        sql_to_run = request_obj.generated_sql
        if not sql_to_run:
            # Generate if missing
            sql_to_run = SQLGenerator.generate_sql(request_obj.request_type, request_obj.details)
            request_obj.generated_sql = sql_to_run

        request_obj.status = RequestStatus.EXECUTING
        db_session.commit()

        # Execute statement on target DB
        try:
            # We support multiple statements separated by ';'
            statements = [s.strip() for s in sql_to_run.split(";") if s.strip()]

            with target_engine.connect() as conn:
                for stmt in statements:
                    try:
                        conn.exec_driver_sql(stmt)
                    except Exception as e:
                        # SQLite driver fallback for GRANT DCL statement during local mock testing
                        if stmt.upper().startswith("GRANT") and target_engine.dialect.name == "sqlite":
                            pass
                        else:
                            raise e
                conn.commit()

            # Record successful execution
            request_obj.status = RequestStatus.COMPLETED
            request_obj.executed_at = datetime.datetime.utcnow()
            request_obj.error_log = None

            log = ExecutionLog(
                request_id=request_obj.id,
                sql_executed=sql_to_run,
                status="SUCCESS",
                output_message="Executed successfully on target database."
            )
            db_session.add(log)
            db_session.commit()
            return True

        except Exception as e:
            err_msg = str(e)
            request_obj.status = RequestStatus.FAILED
            request_obj.error_log = err_msg

            log = ExecutionLog(
                request_id=request_obj.id,
                sql_executed=sql_to_run,
                status="ERROR",
                output_message=err_msg
            )
            db_session.add(log)
            db_session.commit()
            return False

class SchemaInspector:
    """Inspects target database schema, tables, columns, indexes, and user permissions."""

    @staticmethod
    def get_full_schema():
        inspector = inspect(target_engine)
        tables_info = []

        table_names = inspector.get_table_names()
        for t_name in table_names:
            if t_name == "_dba_user_permissions":
                continue

            columns = []
            for col in inspector.get_columns(t_name):
                columns.append({
                    "name": col["name"],
                    "type": str(col["type"]),
                    "nullable": col.get("nullable", True),
                    "default": str(col.get("default")) if col.get("default") is not None else None,
                    "primary_key": col.get("primary_key", 0) > 0
                })

            foreign_keys = inspector.get_foreign_keys(t_name)
            fk_list = []
            for fk in foreign_keys:
                fk_list.append({
                    "constrained_columns": fk.get("constrained_columns"),
                    "referred_table": fk.get("referred_table"),
                    "referred_columns": fk.get("referred_columns")
                })

            tables_info.append({
                "table_name": t_name,
                "columns": columns,
                "foreign_keys": fk_list
            })

        # Fetch permissions
        permissions = []
        try:
            with target_engine.connect() as conn:
                res = conn.execute(text("SELECT id, username, target_object, permission_level, granted_at FROM _dba_user_permissions ORDER BY id DESC"))
                for row in res:
                    permissions.append({
                        "id": row.id,
                        "username": row.username,
                        "target_object": row.target_object,
                        "permission_level": row.permission_level,
                        "granted_at": str(row.granted_at)
                    })
        except Exception:
            pass

        return {
            "tables": tables_info,
            "permissions": permissions
        }
