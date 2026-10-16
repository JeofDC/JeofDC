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

class Employee(Base):
    __tablename__ = "employees"

    id = Column(Integer, primary_key=True, index=True)
    employee_id = Column(String(100), unique=True, index=True, nullable=False)
    first_name = Column(String(100), nullable=True)
    last_name = Column(String(100), nullable=True)
    email = Column(String(200), nullable=True)
    department = Column(String(100), nullable=True)
    designation = Column(String(100), nullable=True)
    status = Column(String(50), nullable=True, default="Active")
    raw_data = Column(JSON, nullable=True, default={})

    created_at = Column(DateTime, default=datetime.datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.datetime.utcnow, onupdate=datetime.datetime.utcnow)

class SyncLog(Base):
    __tablename__ = "sync_logs"

    id = Column(Integer, primary_key=True, index=True)
    status = Column(String(50), nullable=False) # SUCCESS, FAILED, IN_PROGRESS
    total_records = Column(Integer, default=0)
    message = Column(Text, nullable=True)
    error_details = Column(Text, nullable=True)
    started_at = Column(DateTime, default=datetime.datetime.utcnow)
    completed_at = Column(DateTime, nullable=True)

# Engine and session creation
import os

APP_DB_URL = os.getenv("POSTGRES_URL") or os.getenv("DATABASE_URL") or "sqlite:///./app_meta.db"
if APP_DB_URL.startswith("postgres://"):
    APP_DB_URL = APP_DB_URL.replace("postgres://", "postgresql://", 1)

TARGET_DB_URL = os.getenv("TARGET_DB_URL") or "sqlite:///./target_managed.db"
if TARGET_DB_URL.startswith("postgres://"):
    TARGET_DB_URL = TARGET_DB_URL.replace("postgres://", "postgresql://", 1)

app_connect_args = {"check_same_thread": False} if "sqlite" in APP_DB_URL else {}
target_connect_args = {"check_same_thread": False} if "sqlite" in TARGET_DB_URL else {}

app_engine = create_engine(APP_DB_URL, connect_args=app_connect_args)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=app_engine)

target_engine = create_engine(TARGET_DB_URL, connect_args=target_connect_args)

def init_db():
    Base.metadata.create_all(bind=app_engine)
    # Ensure target DB has user_permissions table for RBAC/access grants simulation
    with target_engine.connect() as conn:
        conn.exec_driver_sql("""
            CREATE TABLE IF NOT EXISTS _dba_user_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                target_object TEXT NOT NULL,
                permission_level TEXT NOT NULL,
                granted_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)
        conn.commit()

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
