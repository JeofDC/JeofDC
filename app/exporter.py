import io
from typing import Dict, Any, Optional, List
import pandas as pd
from sqlalchemy.engine import Engine
from app.models import target_engine

class XLSXHandler:
    """Handles parsing, previewing, importing XLSX data into database tables, and exporting database tables to XLSX format."""

    @staticmethod
    def preview_xlsx(file_content: bytes, sheet_name: Optional[str] = None) -> Dict[str, Any]:
        """Reads an XLSX file content and returns metadata including sheet names, columns, row count, and preview rows."""
        buffer = io.BytesIO(file_content)
        excel_file = pd.ExcelFile(buffer, engine="openpyxl")
        sheet_names = excel_file.sheet_names

        selected_sheet = sheet_name if (sheet_name and sheet_name in sheet_names) else sheet_names[0]
        df = pd.read_excel(excel_file, sheet_name=selected_sheet)

        # Convert preview data to standard json-serializable python types
        preview_data = df.head(5).fillna("").to_dict(orient="records")

        return {
            "sheet_names": sheet_names,
            "selected_sheet": selected_sheet,
            "columns": list(df.columns),
            "total_rows": len(df),
            "preview_rows": preview_data
        }

    @staticmethod
    def export_xlsx_to_db(
        file_content: bytes,
        table_name: str,
        if_exists: str = "append",
        sheet_name: Optional[str] = None,
        engine: Optional[Engine] = None
    ) -> Dict[str, Any]:
        """Reads an XLSX file and writes its data to a database table."""
        if engine is None:
            engine = target_engine

        buffer = io.BytesIO(file_content)
        excel_file = pd.ExcelFile(buffer, engine="openpyxl")
        sheet_names = excel_file.sheet_names

        selected_sheet = sheet_name if (sheet_name and sheet_name in sheet_names) else sheet_names[0]
        df = pd.read_excel(excel_file, sheet_name=selected_sheet)

        # Clean column names (strip whitespace)
        df.columns = [str(c).strip() for c in df.columns]

        rows_written = len(df)
        with engine.begin() as connection:
            df.to_sql(name=table_name, con=connection, if_exists=if_exists, index=False)

        return {
            "success": True,
            "table_name": table_name,
            "rows_imported": rows_written,
            "sheet_used": selected_sheet,
            "columns": list(df.columns)
        }

    @staticmethod
    def export_db_to_xlsx(
        table_name: str,
        engine: Optional[Engine] = None
    ) -> bytes:
        """Queries a database table and exports its contents to XLSX format as bytes."""
        if engine is None:
            engine = target_engine

        with engine.connect() as connection:
            df = pd.read_sql_table(table_name=table_name, con=connection)

        output = io.BytesIO()
        with pd.ExcelWriter(output, engine="openpyxl") as writer:
            df.to_excel(writer, sheet_name=table_name[:31], index=False)

        output.seek(0)
        return output.getvalue()
