# 📋 New Import Format Guide for Orders

## 🆕 **What Changed?**

### **Before (Old System):**
- Required **2 separate sheets** (Carpet & Rubber)
- Fixed column positions
- Manual material type assignment

### **Now (New System):**
- **Single sheet** with all orders
- **Automatic material type detection** from product name
- **Smart column mapping** - automatically recognizes your columns
- **Flexible structure** - additional columns are stored but not displayed

## 📊 **Expected Excel Format**

### **Required Columns (Will be displayed in table):**
| Column Header | Description | Example |
|---------------|-------------|---------|
| `order id` | Unique order identifier | ORD001 |
| `order item` | Specific item identifier | ITM001 |
| `product-name` | Product name (must end with "Carpet" or "Rubber") | GCM - Car Floor Mats for Toyota Corolla Hybrid 2019 To Present (Estate, Hatchback & Saloon) - Anti Slip & Fit Car Mat with Clips Easy to Clean Car Carpet for All-Weather- Grey Edging, Carpet |
| `quantity` | Number of items | 5 |
| `recipient name` | Customer name | John Doe |
| `address` | Street address | 123 Main St |
| `city` | City name | New York |
| `postal code` | ZIP/Postal code | 10001 |

### **Optional Columns (Will be stored but not displayed):**
| Column Header | Description | Example |
|---------------|-------------|---------|
| `custom_field_1` | Any additional data | Value 1 |
| `custom_field_2` | Any additional data | Value 2 |
| `notes` | Order notes | Special instructions |
| `priority` | Order priority | High/Medium/Low |

## 🔍 **Material Type Detection**

The system automatically detects material type from the **`product-name`** column:

### **Carpet Orders:**
- Product names ending with **`Carpet`**
- Examples:
  - `GCM - Car Floor Mats for Toyota Corolla Hybrid 2019 To Present (Estate, Hatchback & Saloon) - Anti Slip & Fit Car Mat with Clips Easy to Clean Car Carpet for All-Weather- Grey Edging, Carpet`
  - `GCM - Van Floor Mats for Vauxhall Vivaro Van 2019 To Present Full Coverage Floor Protection - Anti Slip & Fit Van Mat with Clips Easy to Clean Van Carpet for All-Weather- Black Edging, Carpet`
  - `GCM - Floor Mats for Renault Trafic 2014 to Present - Full Floor Protection, Anti-Slip & Fit Mat With Clips - Easy to Clean Carpet for All-Weather - Black Edging`

### **Rubber Orders:**
- Product names ending with **`Rubber`**
- Examples:
  - `GCM - Vauxhall Vivaro Van Floor Mats 2019+ - Full Coverage, Anti-Slip, Heavy Duty, Easy Clean - Black Edging, Rubber`

## 🚗 **Vehicle Information Extraction**

The system automatically extracts clean vehicle information from the product name:

### **Examples:**
- **Input**: `GCM - Car Floor Mats for Toyota Corolla Hybrid 2019 To Present (Estate, Hatchback & Saloon) - Anti Slip & Fit Car Mat with Clips Easy to Clean Car Carpet for All-Weather- Grey Edging, Carpet`
- **Saved as**: `Toyota Corolla Hybrid 2019 To Present (Estate, Hatchback & Saloon)`

- **Input**: `GCM - Van Floor Mats for Vauxhall Vivaro Van 2019 To Present Full Coverage Floor Protection - Anti Slip & Fit Van Mat with Clips Easy to Clean Van Carpet for All-Weather- Black Edging, Carpet`
- **Saved as**: `Vauxhall Vivaro Van 2019 To Present`

- **Input**: `GCM - Floor Mats for Renault Trafic 2014 to Present - Full Floor Protection, Anti-Slip & Fit Mat With Clips - Easy to Clean Carpet for All-Weather - Black Edging`
- **Saved as**: `Renault Trafic 2014 to Present`

- **Input**: `GCM Tailored Black Carpet-Rubber Car Mats for Dacia Sandero 2013-2020 (Red Edging, Carpet)`
- **Saved as**: `Dacia Sandero 2013-2020`

- **Input**: `GCM - Vauxhall Vivaro Van Floor Mats 2019+ - Full Coverage, Anti-Slip, Heavy Duty, Easy Clean - Black Edging, Rubber`
- **Saved as**: `Vauxhall Vivaro Van Floor Mats 2019+`

## 🎨 **Edging Color Extraction**

The system automatically extracts edging color from the product name:

### **Examples:**
- **Input**: `Grey Edging, Carpet` → **Saved as**: `Grey`
- **Input**: `Black Edging, Carpet` → **Saved as**: `Black`
- **Input**: `Red Edging, Carpet` → **Saved as**: `Red`
- **Input**: `Black Edging, Rubber` → **Saved as**: `Black`

## 📝 **Import Process**

1. **Upload Excel file** with single sheet
2. **System automatically:**
   - Maps your column headers to standard fields
   - Detects material type from product names
   - Stores additional columns in `additional_data` field
   - Categorizes orders into Carpet/Rubber tabs
3. **Orders appear** in respective material type tabs
4. **Frontend remains unchanged** - same Carpet/Rubber tabs

## ✅ **Benefits of New System**

- ✅ **Single file upload** instead of two sheets
- ✅ **Automatic column recognition** - no need to match exact positions
- ✅ **Smart material detection** - no manual categorization
- ✅ **Flexible structure** - add new columns without code changes
- ✅ **Backward compatible** - existing functionality preserved
- ✅ **Better error handling** - clear feedback on import issues

## 🚀 **How to Use**

1. **Prepare your Excel file** with the required columns
2. **Ensure product names end** with `Carpet)` or `Rubber)`
3. **Upload via Orders tab** → Import Orders button
4. **System processes automatically** and categorizes orders
5. **View results** in respective Carpet/Rubber tabs

## ⚠️ **Important Notes**

- **Product names must end** with `Carpet` or `Rubber` for proper categorization
- **Vehicle information is automatically extracted** from the product name
- **Edging color is automatically extracted** from the product name
- **Column headers are case-insensitive** and flexible
- **Additional columns are preserved** for future use
- **Duplicate prevention** based on `order_id` + `order_item_id`
- **Empty rows are automatically skipped**

## 🔧 **Technical Details**

- **Database field**: `additional_data` (JSON) stores extra columns
- **Column mapping**: Intelligent fuzzy matching for headers
- **Material detection**: Regex pattern matching on product names
- **Error handling**: Comprehensive validation and feedback
- **Performance**: Optimized for large file imports
