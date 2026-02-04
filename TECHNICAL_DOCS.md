# Technical Documentation - New Folder & Upload Features

## Architecture Overview

### Component Diagram
```
Frontend (Menu.php)
    ↓
Dropdown UI (CSS Styling)
    ↓
JavaScript Event Handlers
    ├→ Create Folder AJAX
    ├→ File Upload Form
    └→ Folder Upload Form
         ↓
Backend PHP Handlers
    ├→ create_folder.php
    ├→ folder_upload.php
    ├→ delete_folder.php
    ├→ upload.php (existing)
    └→ delete_file.php (existing)
         ↓
File System
    └→ /uploads/user_[ID]/
```

## File Structure

```
innoventory/
├── common/
│   └── menu.php (MODIFIED)
├── css/
│   └── main.css (MODIFIED)
├── pkg/file-management/
│   ├── admin_drive.php (MODIFIED)
│   ├── create_folder.php (NEW)
│   ├── delete_folder.php (NEW)
│   ├── folder_upload.php (NEW)
│   ├── my_drive.php (MODIFIED)
│   ├── delete_file.php (existing)
│   └── upload.php (existing)
```

## API Endpoints

### 1. Create Folder
**Endpoint:** `pkg/file-management/create_folder.php`
**Method:** POST
**Parameters:**
```php
- folder_name (string, required) - Name of folder to create
- view_user_id (int, optional) - For admin to create in user's directory
```

**Response:**
```json
{
  "success": true/false,
  "message": "Folder created successfully",
  "folder_name": "Project Files"
}
```

**Security:** Session validation, folder name sanitization

### 2. Delete Folder
**Endpoint:** `pkg/file-management/delete_folder.php`
**Method:** POST
**Parameters:**
```php
- folder_name (string, required) - Name of folder to delete
- user_id (int, required) - User ID owning the folder
```

**Response:**
```json
{
  "success": true/false,
  "message": "Folder deleted successfully"
}
```

**Security:** User/Admin authorization check, recursive directory removal

### 3. Upload Folder
**Endpoint:** `pkg/file-management/folder_upload.php`
**Method:** POST (multipart/form-data)
**Parameters:**
```php
- folder[] (file array, required) - Folder contents with directory structure
```

**Response:** Redirect to dashboard with upload status

**Security:** Session validation, directory structure preservation

## Database

No new database tables created. All operations are file-system based.

**Existing Tables Used:**
- `users` - For user verification
- `starred_files` - For starred file management

## JavaScript Functions

### menu.php Functions

#### 1. toggleNewMenu(event)
```javascript
// Toggles the dropdown menu visibility
// Called when clicking the "+ New" button
// Parameters: event - DOM event object
// Prevents event propagation to avoid closing immediately
```

#### 2. showCreateFolderDialog()
```javascript
// Prompts user for folder name
// Validates input and calls createFolder()
// Closes dropdown after operation
```

#### 3. createFolder(folderName)
```javascript
// AJAX POST request to create_folder.php
// Shows success/error alerts
// Auto-refreshes page on success
// Parameters: folderName - string with folder name
```

### Event Listeners
```javascript
// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    // Checks if click is outside newBtn and dropdown
    // Removes 'show' class from dropdown
});
```

## PHP Functions

### create_folder.php Functions

#### sanitizeFolder Name(string)
```php
// Uses preg_replace to remove special characters
// Pattern: /[^a-zA-Z0-9\s\-_]/
// Allows: alphanumeric, spaces, hyphens, underscores
```

#### Folder Path Generation
```php
// Base directory: ../../uploads/user_[ID]/
// Final path: ../../uploads/user_[ID]/[folder_name]/
```

### delete_folder.php Functions

#### deleteDirectory(string $dir)
```php
// Recursive function to delete directories
// Steps:
// 1. Check if directory exists
// 2. Scan directory contents
// 3. Recursively delete subdirectories
// 4. Delete files in directory
// 5. Remove empty directory
// Returns: boolean success status
```

## CSS Classes

### New Dropdown Menu Classes

```css
.sb-new {
  position: relative;
  display: flex;
  justify-content: center;
  margin-bottom: 18px;
}

.sb-new-dropdown {
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: var(--panel);
  border-radius: 12px;
  box-shadow: var(--shadow);
  border: 1px solid var(--border);
  min-width: 200px;
  margin-top: 8px;
  display: none;
  flex-direction: column;
  z-index: 1000;
}

.sb-new-dropdown.show {
  display: flex;
}

.sb-new-option {
  padding: 12px 16px;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--text);
  font-size: 14px;
  font-weight: 500;
  transition: background 0.2s ease;
  font-family: "Poppins", system-ui, sans-serif;
}

.sb-new-option:hover {
  background: var(--accent-soft);
  color: var(--accent);
}
```

## Data Flow

### Create Folder Flow
```
User clicks "+ New" → Click "New Folder"
              ↓
showCreateFolderDialog() triggered
              ↓
Prompt for folder name
              ↓
User enters name
              ↓
createFolder(name) called
              ↓
AJAX POST to create_folder.php
              ↓
Server validates & sanitizes
              ↓
Directory created
              ↓
JSON response returned
              ↓
Page reloads to display new folder
```

### Delete Folder Flow
```
User clicks ⋮ on folder → Click "Delete"
              ↓
deleteFolder() triggered
              ↓
Confirm dialog shown
              ↓
User confirms deletion
              ↓
AJAX POST to delete_folder.php
              ↓
Server validates permissions
              ↓
Recursive directory deletion
              ↓
JSON response returned
              ↓
Page reloads
```

### Upload Folder Flow
```
User clicks "+ New" → Click "Folder Upload"
              ↓
File picker opens (folder mode)
              ↓
User selects folder
              ↓
HTML form submits with webkitdirectory
              ↓
folder_upload.php processes
              ↓
Directory structure reconstructed
              ↓
Files uploaded to user directory
              ↓
Redirect to dashboard with status
```

## Security Considerations

### 1. Session Validation
```php
if (!isset($_SESSION['loggedin'])) {
    exit; // Unauthorized
}
```

### 2. Input Sanitization
```php
// Folder names sanitized
$sanitizedName = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $folderName);

// Filenames use basename()
$filename = basename($_FILES['upload']['name']);
```

### 3. Authorization Checks
```php
// Users can only access their own files/folders
// Admins can access user files/folders with proper verification
if ($role !== 'admin' && $viewUserId !== $userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
```

### 4. Path Traversal Prevention
```php
// Using basename() to prevent directory traversal
// Folder path restricted to /uploads/user_[ID]/
```

### 5. JSON Response Only
```php
header('Content-Type: application/json');
// Prevents execution of malicious content
```

## Performance Considerations

### 1. Folder Upload
- Uses `webkitdirectory` for folder selection
- Browser handles file selection before upload
- Directory structure preserved efficiently
- Supports unlimited subdirectory depth

### 2. Large Folder Uploads
- Consider implementing progress tracking
- Server-side file size limits may apply
- Browser upload size limits may vary

### 3. Database Queries
- No new database queries added
- Existing session checks utilized
- Starred files queries unchanged

## Browser API Usage

### 1. File Upload Input
```html
<input type="file" id="fileUploadInput" name="upload" hidden>
```

### 2. Folder Upload Input
```html
<input type="file" id="folderUploadInput" name="folder" hidden 
       webkitdirectory mozdirectory>
```

**Attributes:**
- `webkitdirectory` - Chrome/Edge folder selection
- `mozdirectory` - Firefox folder selection
- Fallback: single file selection in unsupported browsers

## Error Handling

### Frontend Errors
```javascript
try {
    const res = await fetch(url);
    const data = await res.json();
    if (!data.success) {
        alert(data.message);
    }
} catch (err) {
    console.error(err);
    alert('An error occurred');
}
```

### Backend Errors
```php
if (empty($folderName)) {
    echo json_encode(['success' => false, 'message' => 'Folder name is required']);
    exit;
}
```

## Testing Checklist

### Unit Tests
- [ ] Folder name sanitization
- [ ] Directory creation
- [ ] Directory deletion
- [ ] File permission handling

### Integration Tests
- [ ] Create folder → Upload file to folder
- [ ] Create folder → Delete folder with contents
- [ ] Admin create user folder
- [ ] User folder upload with subdirectories

### UI Tests
- [ ] Dropdown menu opens/closes
- [ ] All three options functional
- [ ] Menu closes on outside click
- [ ] Proper visual feedback

### Security Tests
- [ ] Session validation enforced
- [ ] Unauthorized access prevented
- [ ] Path traversal attempts blocked
- [ ] Special characters properly sanitized

## Future Enhancements

1. **Rename Folder** - Add rename functionality
2. **Move Folder** - Support moving files/folders between directories
3. **Share Folder** - Share folders with other users
4. **Folder Preview** - Show folder contents count
5. **Storage Quota** - Implement user storage limits
6. **Bulk Operations** - Select multiple items for batch operations
7. **Progress Bar** - Show upload progress for large files
8. **Versioning** - Keep file version history
9. **Search** - Search within folders
10. **Compress** - ZIP folder downloads
