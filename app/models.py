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

# Engine and session creation
APP_DB_URL = "sqlite:///./app_meta.db"
TARGET_DB_URL = "sqlite:///./target_managed.db"

app_engine = create_engine(APP_DB_URL, connect_args={"check_same_thread": False})
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=app_engine)

target_engine = create_engine(TARGET_DB_URL, connect_args={"check_same_thread": False})

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
