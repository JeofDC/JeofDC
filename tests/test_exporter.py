import io
import pandas as pd
import pytest
from app.models import init_db, target_engine
from app.exporter import XLSXHandler
from app.engine import SchemaInspector

@pytest.fixture(autouse=True)
def clean_target_db():
    init_db()
    with target_engine.connect() as conn:
        schema = SchemaInspector.get_full_schema()
        for tbl in schema["tables"]:
            conn.exec_driver_sql(f"DROP TABLE IF EXISTS {tbl['table_name']};")
        conn.commit()

def create_sample_xlsx_bytes() -> bytes:
    df = pd.DataFrame({
        "product_id": [101, 102, 103],
        "name": ["Laptop", "Mouse", "Keyboard"],
        "price": [1200.50, 25.00, 75.00]
    })
    buf = io.BytesIO()
    with pd.ExcelWriter(buf, engine="openpyxl") as writer:
        df.to_excel(writer, sheet_name="Products", index=False)
    buf.seek(0)
    return buf.getvalue()

def test_preview_xlsx():
    xlsx_bytes = create_sample_xlsx_bytes()
    preview = XLSXHandler.preview_xlsx(xlsx_bytes)
    assert preview["sheet_names"] == ["Products"]
    assert preview["columns"] == ["product_id", "name", "price"]
    assert preview["total_rows"] == 3
    assert len(preview["preview_rows"]) == 3
    assert preview["preview_rows"][0]["name"] == "Laptop"

def test_export_xlsx_to_db():
    xlsx_bytes = create_sample_xlsx_bytes()
    res = XLSXHandler.export_xlsx_to_db(xlsx_bytes, table_name="inventory_test", if_exists="replace")
    assert res["success"] is True
    assert res["rows_imported"] == 3
    assert res["table_name"] == "inventory_test"

    schema = SchemaInspector.get_full_schema()
    tbl_names = [t["table_name"] for t in schema["tables"]]
    assert "inventory_test" in tbl_names

def test_export_db_to_xlsx():
    # First write data to DB table
    xlsx_bytes = create_sample_xlsx_bytes()
    XLSXHandler.export_xlsx_to_db(xlsx_bytes, table_name="export_test", if_exists="replace")

    # Export DB table back to XLSX bytes
    exported_bytes = XLSXHandler.export_db_to_xlsx(table_name="export_test")
    assert exported_bytes is not None

    # Read exported XLSX back into DataFrame
    df_read = pd.read_excel(io.BytesIO(exported_bytes))
    assert list(df_read.columns) == ["product_id", "name", "price"]
    assert len(df_read) == 3
    assert df_read.iloc[0]["name"] == "Laptop"

def test_csv_preview_and_import():
    csv_content = b"col1,col2,col3\nval1,val2,100\nval3,val4,200"
    preview = XLSXHandler.preview_xlsx(csv_content, filename="sample.csv")
    assert preview["columns"] == ["col1", "col2", "col3"]
    assert preview["total_rows"] == 2

    res = XLSXHandler.export_xlsx_to_db(csv_content, table_name="csv_import_test", filename="sample.csv", if_exists="replace")
    assert res["success"] is True
    assert res["rows_imported"] == 2

    schema = SchemaInspector.get_full_schema()
    tbl_names = [t["table_name"] for t in schema["tables"]]
    assert "csv_import_test" in tbl_names
