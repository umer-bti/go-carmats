from fastapi import FastAPI, UploadFile, File, Request
from fastapi.responses import JSONResponse, RedirectResponse, HTMLResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from typing import List

import sys
import uuid
import logging

from app.converter import convert_dxf_to_jpg, merge_and_convert_dxf_to_jpg
from app.core.config import settings

# Logging setup
logging.basicConfig(
    stream=sys.stderr,
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s'
)

app = FastAPI()

# Mount static files for converted images and uploads
app.mount("/converted", StaticFiles(directory=settings.CONVERTED_DIR), name="converted")
app.mount("/uploads", StaticFiles(directory=settings.UPLOAD_DIR), name="uploads")

# Setup templates
templates = Jinja2Templates(directory=settings.TEMPLATES_DIR)


@app.get("/", response_class=HTMLResponse)
async def root():
    return RedirectResponse(f"{settings.BASE_URL}/upload")


@app.get("/upload", response_class=HTMLResponse)
async def upload_form(request: Request):
    return templates.TemplateResponse(
        "upload.html",
        {
            "request": request,
            "base_url": settings.BASE_URL
        }
    )


@app.post("/convert")
async def upload_dxf(file: UploadFile = File(...)):
    logging.info(f"Received file: {file.filename}")

    if not file.filename.lower().endswith(".dxf"):
        logging.error("Invalid file type")
        return JSONResponse(
            success=False,
            status_code=400,
            content={"error": "Only .dxf files are allowed"}
        )

    file_id = str(uuid.uuid4())

    dxf_path = settings.UPLOAD_DIR / f"{file_id}.dxf"
    jpg_filename = f"{file_id}.jpg"
    jpg_path = settings.CONVERTED_DIR / jpg_filename


    with open(dxf_path, "wb") as f:
        f.write(await file.read())

    logging.info(f"Saved DXF to: {dxf_path}")

    try:
        convert_dxf_to_jpg(dxf_path, jpg_path)
        image_url = f"{settings.BASE_URL}/converted/{jpg_filename}"
        logging.info(f"Converted JPG: {jpg_path}")
        return {
            "success": True,
            "message": "File converted successfully",
            "image_url": image_url
        }

    except Exception as e:
        logging.exception("Conversion failed")
        return JSONResponse(
            success=False,
            status_code=500,
            content={"error": str(e)}
        )


@app.get("/batch-upload", response_class=HTMLResponse)
async def batch_upload_form(request: Request):
    return templates.TemplateResponse(
        "batch_upload.html",
        {
            "request": request,
            "base_url": settings.BASE_URL
        }
    )


@app.post("/convert-batch")
async def upload_multiple_dxf(files: List[UploadFile] = File(...)):
    logging.info(f"Received {len(files)} files for batch conversion")

    # Validate all files are DXF
    for file in files:
        if not file.filename.lower().endswith(".dxf"):
            logging.error(f"Invalid file type: {file.filename}")
            return JSONResponse(
                status_code=400,
                content={"success": False, "error": f"File '{file.filename}' is not a .dxf file. All files must be DXF format."}
            )

    if len(files) == 0:
        return JSONResponse(
            status_code=400,
            content={"success": False, "error": "No files provided"}
        )

    batch_id = str(uuid.uuid4())
    saved_dxf_paths = []

    # Save all uploaded DXF files
    for idx, file in enumerate(files):
        dxf_filename = f"{batch_id}_{idx}.dxf"
        dxf_path = settings.UPLOAD_DIR / dxf_filename
        
        with open(dxf_path, "wb") as f:
            f.write(await file.read())
        
        saved_dxf_paths.append(str(dxf_path))
        logging.info(f"Saved DXF {idx + 1}/{len(files)}: {dxf_path}")

    # Define output paths
    merged_dxf_filename = f"{batch_id}_merged.dxf"
    merged_dxf_path = str(settings.UPLOAD_DIR / merged_dxf_filename)
    
    jpg_filename = f"{batch_id}_merged.jpg"
    jpg_path = str(settings.CONVERTED_DIR / jpg_filename)

    try:
        # Merge and convert
        logging.info(f"Merging {len(saved_dxf_paths)} DXF files...")
        result = merge_and_convert_dxf_to_jpg(saved_dxf_paths, merged_dxf_path, jpg_path)
        
        image_url = f"{settings.BASE_URL}/converted/{jpg_filename}"
        merged_dxf_url = f"{settings.BASE_URL}/uploads/{merged_dxf_filename}"
        
        logging.info(f"Successfully merged and converted: {jpg_path}")
        
        return {
            "success": True,
            "message": f"Successfully merged {len(files)} DXF files and converted to image",
            "image_url": image_url,
            "merged_dxf_url": merged_dxf_url,
            "files_count": len(files)
        }

    except Exception as e:
        logging.exception("Batch conversion failed")
        return JSONResponse(
            status_code=500,
            content={"success": False, "error": str(e)}
        )
