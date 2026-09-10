import os
import datetime
from typing import List, Optional, Any, Dict
from fastapi import FastAPI, Depends, HTTPException, Query, status, UploadFile, File, Form
from fastapi.responses import Response
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from fastapi.requests import Request
from pydantic import BaseModel, Field
from sqlalchemy.orm import Session

from sqlalchemy import create_engine, text
from app.models import (
    init_db, get_db, DBARequest, ExecutionLog, RequestType, RequestStatus,
    db_config, set_target_engine, build_connection_url, target_engine
)
from app.engine import SQLGenerator, AutomationEngine, SchemaInspector
from app.exporter import XLSXHandler

# Initialize databases
init_db()

app = FastAPI(title="DBA Request & Automation Engine Portal", version="1.0.0")

# Mount static directory and setup templates
app.mount("/static", StaticFiles(directory="app/static"), name="static")
templates = Jinja2Templates(directory="app/templates")

# Pydantic Schemas
class ColumnSchema(BaseModel):
    name: str
    type: str = "TEXT"
    primary_key: bool = False
    autoincrement: bool = False
    not_null: bool = False
    unique: bool = False
    default: Optional[str] = None

class CreateRequestInput(BaseModel):
    developer_name: str
    request_type: str
    title: str
    description: Optional[str] = None
    details: Dict[str, Any]

class RequestActionInput(BaseModel):
    dba_notes: Optional[str] = None

from pydantic import ConfigDict

class ExecutionLogResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    sql_executed: str
    status: str
    output_message: Optional[str]
    executed_at: datetime.datetime

class DBARequestResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    developer_name: str
    request_type: str
    title: str
    description: Optional[str]
    details: Dict[str, Any]
    generated_sql: Optional[str]
    status: str
    dba_notes: Optional[str]
    error_log: Optional[str]
    created_at: datetime.datetime
    updated_at: datetime.datetime
    executed_at: Optional[datetime.datetime]
    execution_logs: List[ExecutionLogResponse] = []

# Web Page Routes
@app.get("/")
def home(request: Request):
    return templates.TemplateResponse(request=request, name="index.html")

# API Endpoints
@app.post("/api/preview-sql")
def preview_sql(payload: Dict[str, Any]):
    request_type = payload.get("request_type")
    details = payload.get("details", {})
    try:
        sql = SQLGenerator.generate_sql(request_type, details)
        return {"sql": sql}
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

@app.post("/api/requests", response_model=DBARequestResponse, status_code=status.HTTP_201_CREATED)
def create_request(data: CreateRequestInput, db: Session = Depends(get_db)):
    # Validate request_type
    try:
        req_type = RequestType(data.request_type)
    except ValueError:
        raise HTTPException(status_code=400, detail=f"Invalid request type: {data.request_type}")

    # Generate SQL
    try:
        generated_sql = SQLGenerator.generate_sql(req_type, data.details)
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Failed to generate SQL: {str(e)}")

    req_obj = DBARequest(
        developer_name=data.developer_name,
        request_type=req_type,
        title=data.title,
        description=data.description,
        details=data.details,
        generated_sql=generated_sql,
        status=RequestStatus.PENDING
    )
    db.add(req_obj)
    db.commit()
    db.refresh(req_obj)
    return req_obj

@app.get("/api/requests", response_model=List[DBARequestResponse])
def list_requests(
    status_filter: Optional[str] = Query(None, alias="status"),
    developer_filter: Optional[str] = Query(None, alias="developer"),
    type_filter: Optional[str] = Query(None, alias="type"),
    db: Session = Depends(get_db)
):
    query = db.query(DBARequest)
    if status_filter:
        query = query.filter(DBARequest.status == status_filter)
    if developer_filter:
        query = query.filter(DBARequest.developer_name.ilike(f"%{developer_filter}%"))
    if type_filter:
        query = query.filter(DBARequest.request_type == type_filter)

    requests = query.order_by(DBARequest.id.desc()).all()
    return requests

@app.get("/api/requests/{request_id}", response_model=DBARequestResponse)
def get_request_details(request_id: int, db: Session = Depends(get_db)):
    req_obj = db.query(DBARequest).filter(DBARequest.id == request_id).first()
    if not req_obj:
        raise HTTPException(status_code=404, detail="Request not found")
    return req_obj

@app.post("/api/requests/{request_id}/approve", response_model=DBARequestResponse)
def approve_request(request_id: int, action: Optional[RequestActionInput] = None, db: Session = Depends(get_db)):
    req_obj = db.query(DBARequest).filter(DBARequest.id == request_id).first()
    if not req_obj:
        raise HTTPException(status_code=404, detail="Request not found")

    if req_obj.status in [RequestStatus.COMPLETED, RequestStatus.REJECTED, RequestStatus.CANCELLED]:
        raise HTTPException(status_code=400, detail=f"Cannot approve request in status: {req_obj.status}")

    if action and action.dba_notes:
        req_obj.dba_notes = action.dba_notes

    # Approve & trigger automatic execution
    success = AutomationEngine.execute_request(db, req_obj)
    db.refresh(req_obj)
    return req_obj

@app.post("/api/requests/{request_id}/reject", response_model=DBARequestResponse)
def reject_request(request_id: int, action: RequestActionInput, db: Session = Depends(get_db)):
    req_obj = db.query(DBARequest).filter(DBARequest.id == request_id).first()
    if not req_obj:
        raise HTTPException(status_code=404, detail="Request not found")

    if req_obj.status in [RequestStatus.COMPLETED, RequestStatus.REJECTED, RequestStatus.CANCELLED]:
        raise HTTPException(status_code=400, detail=f"Cannot reject request in status: {req_obj.status}")

    req_obj.status = RequestStatus.REJECTED
    req_obj.dba_notes = action.dba_notes or "Rejected by DBA."
    db.commit()
    db.refresh(req_obj)
    return req_obj

@app.post("/api/requests/{request_id}/cancel", response_model=DBARequestResponse)
def cancel_request(request_id: int, db: Session = Depends(get_db)):
    req_obj = db.query(DBARequest).filter(DBARequest.id == request_id).first()
    if not req_obj:
        raise HTTPException(status_code=404, detail="Request not found")

    if req_obj.status != RequestStatus.PENDING:
        raise HTTPException(status_code=400, detail="Only PENDING requests can be cancelled.")

    req_obj.status = RequestStatus.CANCELLED
    db.commit()
    db.refresh(req_obj)
    return req_obj

@app.get("/api/schema")
def get_schema():
    return SchemaInspector.get_full_schema()

# Database Connection Configuration & Folder Scanner Endpoints
class DBConfigInput(BaseModel):
    db_type: str = "postgresql" # postgresql or sqlite
    server: str = "localhost"
    port: str = "5432"
    dbname: str = "postgres"
    user: str = "postgres"
    password: Optional[str] = ""
    db_schema: Optional[str] = Field("public", alias="schema")

class FolderScanInput(BaseModel):
    folder_path: str

@app.get("/api/db-config")
def get_db_config():
    # Mask password for security
    conf = dict(db_config)
    conf["password"] = "******" if conf.get("password") else ""
    conf["active_target_url"] = str(target_engine.url)
    return conf

@app.post("/api/db-config")
def update_db_config(data: DBConfigInput):
    try:
        new_conf = data.model_dump(by_alias=True)
        url = build_connection_url(new_conf)

        # Test connection
        if "sqlite" in url:
            test_eng = create_engine(url, connect_args={"check_same_thread": False})
        else:
            test_eng = create_engine(url)

        with test_eng.connect() as conn:
            conn.execute(text("SELECT 1"))

        # Connection successful, set active target engine
        set_target_engine(new_conf)
        init_db()

        return {"success": True, "message": "Database connection updated successfully!", "target_url": str(target_engine.url)}
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Database connection failed: {str(e)}")

@app.post("/api/folder/scan")
def scan_folder(data: FolderScanInput):
    folder_path = data.folder_path.strip()
    if not os.path.exists(folder_path) or not os.path.isdir(folder_path):
        raise HTTPException(status_code=404, detail=f"Folder path '{folder_path}' does not exist or is not a directory.")

    files = []
    for f in os.listdir(folder_path):
        if f.lower().endswith(('.xlsx', '.xls', '.csv')):
            full_p = os.path.join(folder_path, f)
            size_bytes = os.path.getsize(full_p)
            files.append({
                "filename": f,
                "filepath": full_p,
                "size_kb": round(size_bytes / 1024, 2),
                "type": "CSV" if f.lower().endswith('.csv') else "Excel"
            })

    return {"folder_path": folder_path, "files": sorted(files, key=lambda x: x["filename"])}

# XLSX & CSV Import/Export API Endpoints
@app.post("/api/xlsx/preview")
async def preview_xlsx_endpoint(
    file: Optional[UploadFile] = File(None),
    filepath: Optional[str] = Form(None),
    sheet_name: Optional[str] = Form(None)
):
    try:
        if file and file.filename:
            filename = file.filename
            content = await file.read()
        elif filepath:
            if not os.path.exists(filepath):
                raise HTTPException(status_code=404, detail="File path not found.")
            filename = os.path.basename(filepath)
            with open(filepath, "rb") as f:
                content = f.read()
        else:
            raise HTTPException(status_code=400, detail="Either a file upload or file path must be provided.")

        if not filename.lower().endswith(('.xlsx', '.xls', '.csv')):
            raise HTTPException(status_code=400, detail="File must be an Excel spreadsheet (.xlsx, .xls) or CSV file (.csv)")

        result = XLSXHandler.preview_xlsx(content, filename=filename, sheet_name=sheet_name)
        return result
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Failed to preview file: {str(e)}")

@app.post("/api/xlsx/import")
async def import_xlsx_endpoint(
    file: Optional[UploadFile] = File(None),
    filepath: Optional[str] = Form(None),
    table_name: str = Form(...),
    if_exists: str = Form("append"),
    sheet_name: Optional[str] = Form(None)
):
    if not table_name.strip():
        raise HTTPException(status_code=400, detail="Target table name is required.")
    if if_exists not in ["append", "replace", "fail"]:
        raise HTTPException(status_code=400, detail="if_exists must be one of: append, replace, fail")

    try:
        if file and file.filename:
            filename = file.filename
            content = await file.read()
        elif filepath:
            if not os.path.exists(filepath):
                raise HTTPException(status_code=404, detail="File path not found.")
            filename = os.path.basename(filepath)
            with open(filepath, "rb") as f:
                content = f.read()
        else:
            raise HTTPException(status_code=400, detail="Either a file upload or file path must be provided.")

        if not filename.lower().endswith(('.xlsx', '.xls', '.csv')):
            raise HTTPException(status_code=400, detail="File must be an Excel spreadsheet (.xlsx, .xls) or CSV file (.csv)")

        res = XLSXHandler.export_xlsx_to_db(
            file_content=content,
            table_name=table_name.strip(),
            filename=filename,
            if_exists=if_exists,
            sheet_name=sheet_name,
            schema=db_config.get("schema")
        )
        return res
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Failed to import file into database table: {str(e)}")

@app.get("/api/xlsx/export/{table_name}")
def export_xlsx_endpoint(table_name: str):
    try:
        xlsx_data = XLSXHandler.export_db_to_xlsx(table_name=table_name)
        headers = {
            'Content-Disposition': f'attachment; filename="{table_name}_export.xlsx"'
        }
        return Response(
            content=xlsx_data,
            media_type='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            headers=headers
        )
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Failed to export table '{table_name}' to XLSX: {str(e)}")
