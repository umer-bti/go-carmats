# Custom Order Export Implementation Guide

## Overview
A custom export functionality has been implemented for the Orders page that exports orders in a specific format different from the standard DataTable export.

## Features Implemented

### 1. **Custom Export Button**
- Location: Orders page header (`http://127.0.0.1:8000/console/orders`)
- Button: Green "Export Orders" button with Excel icon
- Position: Between "Delete Selected" and "Import Orders" buttons

### 2. **Hidden Default Export Buttons**
- The default DataTable export buttons (CSV, Excel) are now hidden using CSS
- Only your custom export button is visible

### 3. **Custom Export Format**
The exported Excel file contains the following columns in this specific sequence:

| Column | Description |
|--------|-------------|
| Status | Order status (e.g., awaiting_shipment) |
| Order Date | Date of the order |
| Make & Model | Format: `Edging - Product Name - 450/3MM` where:<br>- 450 is added for Carpet orders<br>- 3MM is added for Rubber orders |
| Quantity | Order quantity |
| Edging | Edging type |
| Recipient Name | Customer name |
| Address Line One | Street address |
| Address City | City |
| Address Postcode | Postal code |
| Order ID | Order identifier |
| Order Item ID | Order item identifier |

### 4. **Export Filtering**
The export respects all current filters on the page:
- **Material Type**: Exports only Carpet or Rubber orders based on active tab
- **Batch Status**: Pending or All orders
- **Date Range**: From and To dates
- **Shipment Status**: Awaiting Payment, Awaiting Shipment, Shipped
- **Design Status**: Designed or Not Designed
- **Return Status**: Returned or Not Returned

## How to Use

1. Navigate to `http://127.0.0.1:8000/console/orders`
2. Select the material type tab (Carpet or Rubber)
3. Apply any filters you want (optional)
4. Click the green "Export Orders" button
5. The Excel file will download automatically with filename format: `orders_custom_carpet_YYYY-MM-DD_HHMMSS.xlsx`

## Technical Details

### Files Created/Modified

1. **New File**: `app/Exports/CustomOrdersExport.php`
   - Custom export class with specific column sequence
   - Handles Make & Model formatting with 450/3MM suffix

2. **Modified**: `app/Http/Controllers/Console/Order/OrderController.php`
   - Added `customExport()` method
   - Imports `CustomOrdersExport` class

3. **Modified**: `routes/web.php`
   - Added route: `GET /console/orders/custom-export`

4. **Modified**: `resources/views/console/orders/index.blade.php`
   - Hidden default DataTable export buttons with CSS
   - Added custom "Export Orders" button
   - Added `exportCustomOrders()` JavaScript function

## Export Examples

### For Carpet Orders:
```
Make & Model: Black - BMW 3 Series 2015-2019 - 450
```

### For Rubber Orders:
```
Make & Model: Grey - Mercedes C-Class 2020-2023 - 3MM
```

## Notes
- The export does NOT interfere with other exports in the system (Tracking, etc.)
- All existing functionality remains unchanged
- The export button is always visible (not hidden like batch/delete buttons)
- Export filename includes material type and timestamp for easy identification

