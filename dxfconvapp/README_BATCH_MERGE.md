# DXF Batch Merge & Convert Feature

## Overview
New feature that allows uploading multiple DXF files, merging them into a single DXF file, and converting the merged result to a JPG image.

## New Files Added
- `app/templates/batch_upload.html` - Beautiful drag & drop interface for batch upload
- Updated `app/converter.py` - Added merge functions
- Updated `main.py` - Added new endpoints

## New API Endpoints

### GET `/batch-upload`
Returns the batch upload HTML form

### POST `/convert-batch`
Merges multiple DXF files and converts to image

**Request:**
- Content-Type: `multipart/form-data`
- Body: Multiple files with field name `files`

**Response:**
```json
{
  "success": true,
  "message": "Successfully merged 3 DXF files and converted to image",
  "image_url": "http://localhost:5000/converted/{uuid}_merged.jpg",
  "merged_dxf_url": "http://localhost:5000/uploads/{uuid}_merged.dxf",
  "files_count": 3
}
```

## How It Works

1. **Upload Multiple DXF Files** - User selects/drags multiple .dxf files
2. **Validation** - Server validates all files are DXF format
3. **Save Files** - Each file is saved temporarily with unique ID
4. **Merge DXFs** - Using `ezdxf.addons.importer.Importer`:
   - First file is used as base
   - Remaining files are imported side-by-side with spacing
   - Entities are offset horizontally to avoid overlap
5. **Convert to JPG** - Merged DXF is rendered to JPG (300 DPI, black background)
6. **Return Results** - Both merged DXF and JPG are accessible

## Merge Strategy

The merger places drawings **side-by-side** horizontally:
- Calculates bounding box of current drawing
- Places next drawing 10 units to the right
- Preserves all layers, blocks, and entity properties
- Handles import errors gracefully (skips problematic entities)

## UI Features

✅ Drag & drop support  
✅ Multiple file selection  
✅ File list with remove buttons  
✅ Real-time file count  
✅ Loading animation  
✅ Success/error messages  
✅ Download links for both image and merged DXF  
✅ Responsive design with gradient styling  

## Usage

### Via Web Interface
1. Start the service: `cd dxfconvapp && source venv/bin/activate && uvicorn main:app --reload`
2. Open browser: `http://localhost:5000/batch-upload`
3. Select multiple DXF files
4. Click "Merge & Convert"
5. Download results

### Via API (PHP/Laravel)
```php
$files = [/* array of UploadedFile instances */];

$response = Http::attach(
    'files[]', file_get_contents($files[0]->path()), $files[0]->getClientOriginalName()
)->attach(
    'files[]', file_get_contents($files[1]->path()), $files[1]->getClientOriginalName()
)->post('http://localhost:5000/convert-batch');

$result = $response->json();
// $result['image_url']
// $result['merged_dxf_url']
```

## Navigation
The web interface includes navigation links:
- Single File Upload (original feature)
- Batch Upload (new feature)

## File Structure
```
dxfconvapp/
├── app/
│   ├── converter.py          # ✨ Updated with merge functions
│   ├── core/
│   │   └── config.py
│   └── templates/
│       ├── upload.html       # Original single upload
│       └── batch_upload.html # ✨ New batch upload
├── data/
│   ├── uploads/              # Now serves merged DXF files
│   └── converted/            # Stores JPG outputs
├── main.py                   # ✨ Updated with batch endpoints
└── requirements.txt
```

## Testing

Test with multiple DXF files from your product database:
```bash
# Access the batch upload page
open http://localhost:5000/batch-upload

# Or test via curl
curl -X POST http://localhost:5000/convert-batch \
  -F "files=@file1.dxf" \
  -F "files=@file2.dxf" \
  -F "files=@file3.dxf"
```

## Notes

- Files are merged **horizontally** (side-by-side)
- 10 units spacing between drawings
- Supports any number of files (limited by server memory)
- Temporary files are saved with UUID naming
- Both merged DXF and JPG are returned for download

