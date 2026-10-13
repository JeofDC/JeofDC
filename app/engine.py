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

            col_defs = []
            for col in columns:
                c_name = col.get("name", "").strip()
                c_type = col.get("type", "TEXT").upper().strip()
                c_pk = " PRIMARY KEY" if col.get("primary_key") else ""
                c_auto = " AUTOINCREMENT" if col.get("autoincrement") and col.get("primary_key") else ""
                c_nn = " NOT NULL" if col.get("not_null") and not col.get("primary_key") else ""
                c_unique = " UNIQUE" if col.get("unique") else ""
                c_default = f" DEFAULT '{col.get('default')}'" if col.get("default") is not None and col.get("default") != "" else ""
                col_defs.append(f"  {c_name} {c_type}{c_pk}{c_auto}{c_nn}{c_unique}{c_default}")

            sql = f"CREATE TABLE {table_name} (\n" + ",\n".join(col_defs) + "\n);"
            return sql

        elif request_type == RequestType.ALTER_TABLE:
            table_name = details.get("table_name", "").strip()
            action = details.get("action", "ADD_COLUMN") # ADD_COLUMN, RENAME_TABLE
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

            else:
                raise ValueError(f"Unsupported alter table action: {action}")

        elif request_type == RequestType.DROP_TABLE:
            table_name = details.get("table_name", "").strip()
            if not table_name:
                raise ValueError("Table name is required for DROP_TABLE.")
            return f"DROP TABLE IF EXISTS {table_name};"

        elif request_type == RequestType.GRANT_ACCESS:
            username = details.get("username", "").strip()
            target_object = details.get("target_object", "").strip() # e.g. table name or *
            permission = details.get("permission", "READ").upper().strip() # READ, WRITE, ADMIN
            if not username or not target_object:
                raise ValueError("Username and Target Object are required for GRANT_ACCESS.")

            # Map legacy permission to PG privilege for backward compatibility
            priv = "SELECT" if permission == "READ" else "SELECT, INSERT, UPDATE, DELETE" if permission == "WRITE" else "ALL PRIVILEGES"
            return f"GRANT {priv} ON {target_object} TO {username};\nINSERT INTO _dba_user_permissions (grantee, schema_name, target_object, permission_level) VALUES ('{username}', 'public', '{target_object}', '{permission}');"

        elif request_type == RequestType.CREATE_ROLE:
            role_name = details.get("role_name", "").strip()
            can_login = details.get("can_login", False)
            password = details.get("password", "").strip()
            if not role_name:
                raise ValueError("Role name is required for CREATE_ROLE.")
            login_clause = "LOGIN" if can_login else "NOLOGIN"
            password_clause = f" WITH PASSWORD '{password}'" if password and can_login else ""
            role_type = "USER" if can_login else "ROLE"
            login_num = 1 if can_login else 0

            sql = f"CREATE ROLE {role_name} WITH {login_clause}{password_clause};\n"
            sql += f"INSERT INTO _dba_roles (role_name, role_type, can_login) VALUES ('{role_name}', '{role_type}', {login_num});"
            return sql

        elif request_type == RequestType.ASSIGN_ROLE:
            member_name = details.get("member_name", "").strip()
            parent_role = details.get("parent_role", "").strip()
            if not member_name or not parent_role:
                raise ValueError("Member name and Parent role are required for ASSIGN_ROLE.")

            sql = f"GRANT {parent_role} TO {member_name};\n"
            sql += f"INSERT INTO _dba_role_memberships (member_name, parent_role) VALUES ('{member_name}', '{parent_role}');"
            return sql

        elif request_type == RequestType.GRANT_PRIVILEGE:
            grantee = details.get("grantee", "").strip() # User or Role name
            schema_name = details.get("schema_name", "public").strip() or "public"
            target_type = details.get("target_type", "ALL TABLES").upper().strip() # ALL TABLES, SCHEMA, TABLE
            target_object = details.get("target_object", "").strip() or "ALL TABLES"
            privileges = details.get("privileges", "SELECT").upper().strip() # SELECT, INSERT, UPDATE, DELETE, ALL PRIVILEGES, USAGE

            if not grantee:
                raise ValueError("Grantee (role/user) is required for GRANT_PRIVILEGE.")

            if target_type == "SCHEMA":
                pg_sql = f"GRANT {privileges} ON SCHEMA {schema_name} TO {grantee};"
            elif target_type == "ALL TABLES" or target_type == "ALL TABLES IN SCHEMA":
                pg_sql = f"GRANT {privileges} ON ALL TABLES IN SCHEMA {schema_name} TO {grantee};"
            else: # Specific Table
                pg_sql = f"GRANT {privileges} ON TABLE {schema_name}.{target_object} TO {grantee};"

            sql = f"{pg_sql}\nINSERT INTO _dba_user_permissions (grantee, schema_name, target_object, permission_level) VALUES ('{grantee}', '{schema_name}', '{target_object}', '{privileges}');"
            return sql

        elif request_type == RequestType.REVOKE_PRIVILEGE:
            grantee = details.get("grantee", "").strip()
            schema_name = details.get("schema_name", "public").strip() or "public"
            target_type = details.get("target_type", "ALL TABLES").upper().strip()
            target_object = details.get("target_object", "").strip() or "ALL TABLES"
            privileges = details.get("privileges", "SELECT").upper().strip()

            if not grantee:
                raise ValueError("Grantee is required for REVOKE_PRIVILEGE.")

            if target_type == "SCHEMA":
                pg_sql = f"REVOKE {privileges} ON SCHEMA {schema_name} FROM {grantee};"
            elif target_type == "ALL TABLES":
                pg_sql = f"REVOKE {privileges} ON ALL TABLES IN SCHEMA {schema_name} FROM {grantee};"
            else:
                pg_sql = f"REVOKE {privileges} ON TABLE {schema_name}.{target_object} FROM {grantee};"

            sql = f"{pg_sql}\nDELETE FROM _dba_user_permissions WHERE grantee='{grantee}' AND schema_name='{schema_name}' AND target_object='{target_object}';"
            return sql

        elif request_type == RequestType.SET_DEFAULT_PRIVILEGES:
            target_role = details.get("target_role", "").strip() # Owner role, or empty
            grantee = details.get("grantee", "").strip()
            schema_name = details.get("schema_name", "public").strip() or "public"
            privileges = details.get("privileges", "SELECT").upper().strip()

            if not grantee:
                raise ValueError("Grantee is required for SET_DEFAULT_PRIVILEGES.")

            for_role_clause = f" FOR ROLE {target_role}" if target_role else ""
            pg_sql = f"ALTER DEFAULT PRIVILEGES{for_role_clause} IN SCHEMA {schema_name} GRANT {privileges} ON TABLES TO {grantee};"
            sql = f"{pg_sql}\nINSERT INTO _dba_user_permissions (grantee, schema_name, target_object, permission_level) VALUES ('{grantee}', '{schema_name}', 'DEFAULT TABLES ({privileges})', '{privileges}');"
            return sql

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
                    # In SQLite simulation mode, skip execution of PG-specific DDL syntax that SQLite driver cannot parse
                    if target_engine.dialect.name == "sqlite" and any(stmt.upper().startswith(kw) for kw in ["CREATE ROLE", "GRANT ", "REVOKE ", "ALTER DEFAULT PRIVILEGES"]):
                        continue
                    conn.exec_driver_sql(stmt)
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
            if t_name in ["_dba_user_permissions", "_dba_roles", "_dba_role_memberships"]:
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

        # Fetch roles, memberships, and permissions
        roles = []
        memberships = []
        permissions = []

        try:
            with target_engine.connect() as conn:
                # Roles
                res_roles = conn.execute(text("SELECT id, role_name, role_type, can_login, created_at FROM _dba_roles ORDER BY id DESC"))
                for row in res_roles:
                    roles.append({
                        "id": row.id,
                        "role_name": row.role_name,
                        "role_type": row.role_type,
                        "can_login": bool(row.can_login),
                        "created_at": str(row.created_at)
                    })

                # Memberships
                res_m = conn.execute(text("SELECT id, member_name, parent_role, granted_at FROM _dba_role_memberships ORDER BY id DESC"))
                for row in res_m:
                    memberships.append({
                        "id": row.id,
                        "member_name": row.member_name,
                        "parent_role": row.parent_role,
                        "granted_at": str(row.granted_at)
                    })

                # Permissions
                res_p = conn.execute(text("SELECT id, grantee, schema_name, target_object, permission_level, granted_at FROM _dba_user_permissions ORDER BY id DESC"))
                for row in res_p:
                    permissions.append({
                        "id": row.id,
                        "grantee": row.grantee,
                        "schema_name": row.schema_name,
                        "target_object": row.target_object,
                        "permission_level": row.permission_level,
                        "granted_at": str(row.granted_at)
                    })
        except Exception:
            pass

        return {
            "tables": tables_info,
            "roles": roles,
            "memberships": memberships,
            "permissions": permissions
        }
