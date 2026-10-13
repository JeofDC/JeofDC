import enum
import datetime
import json
from sqlalchemy import create_engine, Column, Integer, String, Text, DateTime, Enum, JSON, ForeignKey
from sqlalchemy.orm import declarative_base, sessionmaker, relationship

Base = declarative_base()

class RequestType(str, enum.Enum):
    CREATE_TABLE = "CREATE_TABLE"
    ALTER_TABLE = "ALTER_TABLE"
    DROP_TABLE = "DROP_TABLE"
    GRANT_ACCESS = "GRANT_ACCESS"
    CREATE_ROLE = "CREATE_ROLE"
    ASSIGN_ROLE = "ASSIGN_ROLE"
    GRANT_PRIVILEGE = "GRANT_PRIVILEGE"
    REVOKE_PRIVILEGE = "REVOKE_PRIVILEGE"
    SET_DEFAULT_PRIVILEGES = "SET_DEFAULT_PRIVILEGES"
    CUSTOM_SQL = "CUSTOM_SQL"

class RequestStatus(str, enum.Enum):
    PENDING = "PENDING"
    APPROVED = "APPROVED"
    EXECUTING = "EXECUTING"
    COMPLETED = "COMPLETED"
    REJECTED = "REJECTED"
    FAILED = "FAILED"
    CANCELLED = "CANCELLED"

class DBARequest(Base):
    __tablename__ = "dba_requests"

    id = Column(Integer, primary_key=True, index=True)
    developer_name = Column(String(100), nullable=False)
    request_type = Column(String(50), nullable=False)
    title = Column(String(200), nullable=False)
    description = Column(Text, nullable=True)

    # Details JSON contains structure like columns, table_name, access_level, target_user, sql_query etc.
    details = Column(JSON, nullable=False, default={})

    # Generated target SQL that will be executed upon DBA approval
    generated_sql = Column(Text, nullable=True)

    status = Column(String(50), default=RequestStatus.PENDING, nullable=False)

    dba_notes = Column(Text, nullable=True)
    error_log = Column(Text, nullable=True)

    created_at = Column(DateTime, default=datetime.datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.datetime.utcnow, onupdate=datetime.datetime.utcnow)
    executed_at = Column(DateTime, nullable=True)

    execution_logs = relationship("ExecutionLog", back_populates="request", cascade="all, delete-orphan")

class ExecutionLog(Base):
    __tablename__ = "execution_logs"

    id = Column(Integer, primary_key=True, index=True)
    request_id = Column(Integer, ForeignKey("dba_requests.id"), nullable=False)
    sql_executed = Column(Text, nullable=False)
    status = Column(String(50), nullable=False) # SUCCESS or ERROR
    output_message = Column(Text, nullable=True)
    executed_at = Column(DateTime, default=datetime.datetime.utcnow)

    request = relationship("DBARequest", back_populates="execution_logs")

# Engine and session creation
APP_DB_URL = "sqlite:///./app_meta.db"
TARGET_DB_URL = "sqlite:///./target_managed.db"

app_engine = create_engine(APP_DB_URL, connect_args={"check_same_thread": False})
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=app_engine)

target_engine = create_engine(TARGET_DB_URL, connect_args={"check_same_thread": False})

def init_db():
    Base.metadata.create_all(bind=app_engine)
    # Ensure target DB has tables for PostgreSQL Data Warehouse RBAC simulation
    with target_engine.connect() as conn:
        conn.exec_driver_sql("""
            CREATE TABLE IF NOT EXISTS _dba_roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_name TEXT UNIQUE NOT NULL,
                role_type TEXT NOT NULL DEFAULT 'ROLE', -- 'ROLE' or 'USER'
                can_login INTEGER NOT NULL DEFAULT 0,   -- 1 for LOGIN, 0 for NOLOGIN
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        """)
        conn.exec_driver_sql("""
            CREATE TABLE IF NOT EXISTS _dba_role_memberships (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                member_name TEXT NOT NULL,              -- User or Role name
                parent_role TEXT NOT NULL,              -- Parent Role granted to member
                granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(member_name, parent_role)
            );
        """)
        try:
            conn.exec_driver_sql("ALTER TABLE _dba_user_permissions RENAME COLUMN username TO grantee;")
        except Exception:
            pass
        try:
            conn.exec_driver_sql("ALTER TABLE _dba_user_permissions ADD COLUMN schema_name TEXT NOT NULL DEFAULT 'public';")
        except Exception:
            pass

        conn.exec_driver_sql("""
            CREATE TABLE IF NOT EXISTS _dba_user_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                grantee TEXT NOT NULL,                 -- Role or User
                schema_name TEXT NOT NULL DEFAULT 'public',
                target_object TEXT NOT NULL,           -- e.g. 'ALL TABLES', 'table_name', or 'SCHEMA'
                permission_level TEXT NOT NULL,        -- e.g. 'SELECT', 'INSERT', 'USAGE', 'ALL PRIVILEGES'
                granted_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        """)
        conn.commit()

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
