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

from app.models import (
    init_db, get_db, DBARequest, ExecutionLog, RequestType, RequestStatus
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

# XLSX Import/Export API Endpoints
@app.post("/api/xlsx/preview")
async def preview_xlsx_endpoint(file: UploadFile = File(...), sheet_name: Optional[str] = Form(None)):
    if not file.filename.endswith(('.xlsx', '.xls')):
        raise HTTPException(status_code=400, detail="Uploaded file must be an Excel spreadsheet (.xlsx or .xls)")
    try:
        content = await file.read()
        result = XLSXHandler.preview_xlsx(content, sheet_name=sheet_name)
        return result
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Failed to preview Excel file: {str(e)}")

@app.post("/api/xlsx/import")
async def import_xlsx_endpoint(
    file: UploadFile = File(...),
    table_name: str = Form(...),
    if_exists: str = Form("append"),
    sheet_name: Optional[str] = Form(None)
):
    if not file.filename.endswith(('.xlsx', '.xls')):
        raise HTTPException(status_code=400, detail="Uploaded file must be an Excel spreadsheet (.xlsx or .xls)")
    if not table_name.strip():
        raise HTTPException(status_code=400, detail="Target table name is required.")
    if if_exists not in ["append", "replace", "fail"]:
        raise HTTPException(status_code=400, detail="if_exists must be one of: append, replace, fail")

    try:
        content = await file.read()
        res = XLSXHandler.export_xlsx_to_db(
            file_content=content,
            table_name=table_name.strip(),
            if_exists=if_exists,
            sheet_name=sheet_name
        )
        return res
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Failed to import XLSX into database table: {str(e)}")

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
