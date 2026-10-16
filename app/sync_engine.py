import datetime
import requests
import json
from typing import Dict, Any, List, Optional
from sqlalchemy.orm import Session
from app.models import Employee, SyncLog

class DarwinboxSyncEngine:
    DEFAULT_API_URL = "https://mega.darwinbox.com/masterapi/employee"

    @staticmethod
    def fetch_employees_from_api(
        api_key: str,
        dataset_key: str,
        authorization: str,
        api_url: Optional[str] = None
    ) -> List[Dict[str, Any]]:
        """
        Calls Darwinbox employee API and returns list of employee dicts.
        """
        url = api_url or DarwinboxSyncEngine.DEFAULT_API_URL
        headers = {
            "Accept": "*/*",
            "Content-Type": "application/json",
            "Authorization": authorization if authorization.startswith("Basic ") or authorization.startswith("Bearer ") else f"Basic {authorization}"
        }
        payload = {
            "api_key": api_key,
            "datasetKey": dataset_key
        }

        response = requests.post(url, headers=headers, json=payload, timeout=30)
        response.raise_for_status()

        data = response.json()

        # Darwinbox standard response payload handling
        # Could return list directly or dict with 'data' or 'employees' key or 'result'
        if isinstance(data, list):
            return data
        elif isinstance(data, dict):
            if "data" in data and isinstance(data["data"], list):
                return data["data"]
            elif "employees" in data and isinstance(data["employees"], list):
                return data["employees"]
            elif "result" in data and isinstance(data["result"], list):
                return data["result"]
            elif "employee_data" in data and isinstance(data["employee_data"], list):
                return data["employee_data"]
            # Fallback if dictionary contains employee list in nested 'data'->'employee_data'
            elif "data" in data and isinstance(data["data"], dict) and "employee_data" in data["data"]:
                return data["data"]["employee_data"]
            else:
                # If it's a single dict representing an employee or status
                return [data]
        return []

    @staticmethod
    def process_and_sync(
        db: Session,
        api_key: str,
        dataset_key: str,
        authorization: str,
        api_url: Optional[str] = None
    ) -> SyncLog:
        """
        Fetches employees from API and upserts them into the database, recording a SyncLog.
        """
        sync_log = SyncLog(
            status="IN_PROGRESS",
            message="Started Darwinbox employee synchronization...",
            started_at=datetime.datetime.utcnow()
        )
        db.add(sync_log)
        db.commit()
        db.refresh(sync_log)

        try:
            raw_records = DarwinboxSyncEngine.fetch_employees_from_api(
                api_key=api_key,
                dataset_key=dataset_key,
                authorization=authorization,
                api_url=api_url
            )

            record_count = 0
            for item in raw_records:
                if not isinstance(item, dict):
                    continue

                # Extract employee_id / id field
                emp_id = (
                    item.get("employee_id") or
                    item.get("employee_no") or
                    item.get("emp_id") or
                    item.get("id") or
                    item.get("user_id")
                )
                if not emp_id:
                    continue

                emp_id = str(emp_id).strip()

                # Extract names, email, department, designation
                first_name = item.get("first_name") or item.get("firstname") or item.get("name") or ""
                last_name = item.get("last_name") or item.get("lastname") or ""
                email = item.get("email") or item.get("official_email") or item.get("email_id") or ""
                department = item.get("department") or item.get("dept") or item.get("department_name") or ""
                designation = item.get("designation") or item.get("job_title") or item.get("title") or ""
                status = item.get("status") or item.get("employee_status") or "Active"

                # Check if employee already exists
                existing_emp = db.query(Employee).filter(Employee.employee_id == emp_id).first()
                if existing_emp:
                    existing_emp.first_name = first_name or existing_emp.first_name
                    existing_emp.last_name = last_name or existing_emp.last_name
                    existing_emp.email = email or existing_emp.email
                    existing_emp.department = department or existing_emp.department
                    existing_emp.designation = designation or existing_emp.designation
                    existing_emp.status = status or existing_emp.status
                    existing_emp.raw_data = item
                    existing_emp.updated_at = datetime.datetime.utcnow()
                else:
                    new_emp = Employee(
                        employee_id=emp_id,
                        first_name=first_name,
                        last_name=last_name,
                        email=email,
                        department=department,
                        designation=designation,
                        status=status,
                        raw_data=item
                    )
                    db.add(new_emp)

                record_count += 1

            db.commit()

            sync_log.status = "SUCCESS"
            sync_log.total_records = record_count
            sync_log.message = f"Successfully synced {record_count} employee records from Darwinbox."
            sync_log.completed_at = datetime.datetime.utcnow()
            db.commit()

        except Exception as e:
            db.rollback()
            sync_log.status = "FAILED"
            sync_log.error_details = str(e)
            sync_log.message = f"Sync failed: {str(e)}"
            sync_log.completed_at = datetime.datetime.utcnow()
            db.commit()

        db.refresh(sync_log)
        return sync_log
