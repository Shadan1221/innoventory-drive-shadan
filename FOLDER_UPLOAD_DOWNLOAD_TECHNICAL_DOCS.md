# Folder Upload and Download - Technical Documentation

## Overview

This document explains the technical implementation of the folder upload and download functionality in Innoventory Drive.

---

## Table of Contents

1. [Folder Upload](#folder-upload)
2. [Folder Download](#folder-download)
3. [Folder Viewing](#folder-viewing)
4. [Folder Management](#folder-management)
5. [Security Considerations](#security-considerations)

---

## Folder Upload

### How It Works

#### 1. **HTML5 Directory Input Attribute**

The folder upload feature uses the HTML5 `webkitdirectory` attribute which allows users to select entire folders instead of individual files.

**File:** `common/menu.php`
```html
<input type="file" 
       id="folderUploadInput" 
       name="folder[]" 
       multiple 
       webkitdirectory 
       directory 
       onchange="document.getElementById('folderUploadForm').submit()">
```

**Key Attributes:**
- `webkitdirectory`: Enables folder selection in Chrome/Edge
- `directory`: Standard attribute for folder selection
- `multiple`: Allows multiple files (all files in folder)
- `name="folder[]"`: PHP array notation to handle multiple files

#### 2. **Browser Behavior**

When a user selects a folder:
- Browser reads ALL files in the folder (including subfolders)
- Each file gets a `webkitRelativePath` property containing the full path
- All files are sent in a single form submission as an array

**Example:**
```
Selected Folder: MyProject/
├── file1.txt
├── subfolder/
│   ├── file2.txt
│   └── file3.txt
```

Browser sends:
```
$_FILES['folder']['webkitRelativePath'][0] = "MyProject/file1.txt"
$_FILES['folder']['webkitRelativePath'][1] = "MyProject/subfolder/file2.txt"
$_FILES['folder']['webkitRelativePath'][2] = "MyProject/subfolder/file3.txt"
```

#### 3. **Server-Side Processing**

**File:** `pkg/file-management/folder_upload.php`

```php
// Extract folder structure from webkitRelativePath
$fullPath = $_FILES['folder']['full_path'][$i] 
         ?? $_FILES['folder']['webkitRelativePath'][$i] 
         ?? $_FILES['folder']['name'][$i];

// Normalize path separators (Windows vs Unix)
$fullPath = str_replace('\\', '/', $fullPath);

// Build target path maintaining structure
$relativePath = $fullPath;
$targetPath = $userDir . "/" . $relativePath;

// Create necessary subdirectories
$targetDir = dirname($targetPath);
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true); // Recursive directory creation
}

// Move uploaded file
move_uploaded_file($tmpName, $targetPath);
```

**Process Flow:**
1. Loop through all uploaded files in `$_FILES['folder']` array
2. Extract full path including folder structure
3. Create matching directory structure in `uploads/user_X/`
4. Move each file to its correct location
5. Preserve original folder hierarchy

**Storage Structure:**
```
uploads/
└── user_123/
    └── MyProject/              ← Root folder
        ├── file1.txt
        └── subfolder/          ← Subfolder preserved
            ├── file2.txt
            └── file3.txt
```

---

## Folder Download

### How It Works

#### 1. **ZIP Archive Creation**

Folder downloads use PHP's `ZipArchive` class to create compressed archives.

**File:** `pkg/file-management/download_folder.php`

```php
// Create temporary ZIP file
$zipFilePath = sys_get_temp_dir() . '/' . uniqid() . '.zip';
$zip = new ZipArchive();
$zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

// Recursively add all files
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    $filePath = $file->getRealPath();
    $relativePath = substr($filePath, strlen($folderPath) + 1);
    $zip->addFile($filePath, $relativePath);
}

$zip->close();
```

#### 2. **Recursive Directory Iteration**

**RecursiveDirectoryIterator:**
- Traverses all subdirectories automatically
- `SKIP_DOTS` ignores `.` and `..` entries
- `LEAVES_ONLY` only returns files (not directories)

**Path Handling:**
```php
// Example:
// $folderPath = "D:/xampp/htdocs/uploads/user_123/MyProject"
// $filePath   = "D:/xampp/htdocs/uploads/user_123/MyProject/subfolder/file2.txt"

// Calculate relative path:
$relativePath = substr($filePath, strlen($folderPath) + 1);
// Result: "subfolder/file2.txt"

// Add to ZIP with relative path (preserves structure)
$zip->addFile($filePath, $relativePath);
```

#### 3. **File Download Headers**

```php
// Set appropriate headers for browser download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="MyProject.zip"');
header('Content-Length: ' . filesize($zipFilePath));

// Stream file to browser
readfile($zipFilePath);

// Cleanup temporary file
unlink($zipFilePath);
```

**Header Explanation:**
- `Content-Type: application/zip` - Tells browser it's a ZIP file
- `Content-Disposition: attachment` - Forces download instead of displaying
- `Content-Length` - Enables download progress bar
- `readfile()` - Streams file efficiently to browser
- `unlink()` - Deletes temporary file after sending

---

## Folder Viewing

### How It Works

**File:** `pkg/file-management/folder_view.php`

#### 1. **Folder Navigation**

```php
// Get folder name from URL parameter
$folderName = $_GET['folder'] ?? '';

// Build folder path
$baseDir = "../../uploads/user_" . $viewUserId;
$folderPath = $baseDir . "/" . basename($folderName);

// Scan directory contents
$allItems = array_diff(scandir($folderPath), ['.', '..']);

// Separate files and subfolders
foreach ($allItems as $item) {
    $fullPath = $folderPath . "/" . $item;
    if (is_dir($fullPath)) {
        $subfolders[] = $item;
    } else {
        $files[] = $item;
    }
}
```

#### 2. **File Download from Folder**

Files inside folders are downloaded using their relative path:

```php
// In folder_view.php:
$downloadUrl = "download.php?user_id={$viewUserId}&file=" 
             . urlencode($folderName . '/' . $file);

// In download.php:
$file = $_GET['file']; // e.g., "MyProject/subfolder/file2.txt"
$path = "../../uploads/user_$userId/" . $file;
```

**Path Construction:**
```
Folder: MyProject
File: file2.txt in subfolder

Download URL parameter: "MyProject/subfolder/file2.txt"
Final path: "uploads/user_123/MyProject/subfolder/file2.txt"
```

---

## Folder Management

### Operations Available

#### 1. **Rename Folder**

**File:** `pkg/file-management/rename_folder.php`

```php
$oldPath = $baseDir . "/" . basename($oldName);
$newPath = $baseDir . "/" . basename($newName);

// PHP rename() moves entire directory with contents
rename($oldPath, $newPath);
```

**How it works:**
- PHP's `rename()` function works on directories
- Automatically moves all contents (files and subfolders)
- Atomic operation (all-or-nothing)

#### 2. **Delete Folder**

**File:** `pkg/file-management/delete_folder.php`

```php
function deleteDirectory($dir) {
    // Get all files and subdirectories
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $filePath = $dir . '/' . $file;
        
        // Recursive: if directory, delete it first
        if (is_dir($filePath)) {
            deleteDirectory($filePath);
        } else {
            unlink($filePath); // Delete file
        }
    }
    
    // Remove empty directory
    return rmdir($dir);
}
```

**Recursive Deletion:**
1. Scan directory for all contents
2. For each subdirectory, recursively call `deleteDirectory()`
3. Delete all files with `unlink()`
4. Remove empty directory with `rmdir()`
5. Works bottom-up (deepest folders deleted first)

---

## Security Considerations

### 1. **Path Traversal Prevention**

```php
// Remove dangerous path traversal attempts
$file = str_replace(['../', '..\\'], '', $file);

// Use basename() for folder names (removes parent paths)
$folderPath = $baseDir . "/" . basename($folderName);
```

**Prevents:**
- `../../etc/passwd` → `etcpasswd`
- `..\..\..\windows\system32` → `windowssystem32`

### 2. **User Authorization**

```php
// Security check for non-admin users
if ($role !== 'admin' && $viewUserId !== $userId) {
    die("Unauthorized");
}
```

**Access Control:**
- Regular users: Can only access their own folders
- Admin users: Can access any user's folders
- Enforced on every file operation

### 3. **Session Validation**

```php
// Every page starts with:
require_once "../../session.php";

if (!isset($_SESSION['loggedin'])) {
    header("Location: ../../index.php");
    exit;
}
```

**Ensures:**
- Only logged-in users can upload/download
- Session timeout protection
- Prevents direct URL access

### 4. **File Type Validation**

While folders accept all file types, security measures include:
- Files stored outside web root or in protected directories
- No execution permissions on upload directory
- Server configured to not execute PHP files in upload directory

---

## Technical Requirements

### PHP Extensions Required

1. **zip** - For creating ZIP archives
   - Enable in `php.ini`: `extension=zip`
   - Required for folder downloads

2. **fileinfo** - For MIME type detection
   - Usually enabled by default
   - Used for file type validation

### PHP Configuration

Recommended `php.ini` settings for folder uploads:

```ini
upload_max_filesize = 128M      ; Maximum file size
post_max_size = 128M            ; Maximum POST size
max_file_uploads = 200          ; Maximum files per request
max_execution_time = 300        ; 5 minutes for large folders
memory_limit = 256M             ; Sufficient memory for ZIP creation
```

### Server Requirements

- **PHP Version:** 7.4 or higher
- **Disk Space:** Sufficient for user uploads
- **Temp Directory:** Writable for ZIP creation
- **Permissions:** 0755 for directories, 0644 for files

---

## File Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    FOLDER UPLOAD FLOW                        │
└─────────────────────────────────────────────────────────────┘

User Selects Folder → Browser Reads All Files → Form Submit
                                 ↓
                    folder_upload.php receives:
                    $_FILES['folder']['name'][]
                    $_FILES['folder']['tmp_name'][]
                    $_FILES['folder']['webkitRelativePath'][]
                                 ↓
                Loop through each file:
                1. Extract full path with folder structure
                2. Create matching directories
                3. Move file to target location
                                 ↓
                Redirect to my_drive.php
                                 ↓
                    Display folder in file grid


┌─────────────────────────────────────────────────────────────┐
│                   FOLDER DOWNLOAD FLOW                       │
└─────────────────────────────────────────────────────────────┘

User Clicks Download → download_folder.php
                                 ↓
                Create temporary ZIP file
                                 ↓
                Recursively iterate folder:
                - Add each file to ZIP
                - Preserve directory structure
                                 ↓
                Close ZIP archive
                                 ↓
                Send to browser with headers:
                - Content-Type: application/zip
                - Content-Disposition: attachment
                                 ↓
                Delete temporary ZIP file
                                 ↓
                User receives MyFolder.zip
```

---

## Browser Compatibility

### Folder Upload Support

| Browser | Version | Support |
|---------|---------|---------|
| Chrome | 21+ | ✅ Full |
| Edge | 79+ | ✅ Full |
| Firefox | 50+ | ✅ Full |
| Safari | 11.1+ | ✅ Full |
| Opera | 15+ | ✅ Full |
| IE | Any | ❌ Not Supported |

**Note:** The `webkitdirectory` attribute is now supported by all modern browsers despite the "webkit" prefix.

---

## Performance Considerations

### Large Folder Handling

**Upload:**
- Browser sends all files in single request
- PHP processes sequentially
- Consider chunked uploads for very large folders (1000+ files)

**Download:**
- ZIP creation happens in memory/temp directory
- Large folders may require increased PHP memory limit
- Streaming ZIP creation can improve memory usage

### Optimization Tips

1. **Increase PHP limits** for large folders
2. **Use streaming** for ZIP creation if available
3. **Implement progress bars** for user feedback
4. **Consider background processing** for very large folders
5. **Add file count/size validation** before upload

---

## Troubleshooting

### Common Issues

**1. "ZIP extension not enabled"**
- Solution: Enable `extension=zip` in php.ini and restart Apache

**2. "File not found" when downloading files in folders**
- Cause: Path not properly constructed
- Solution: Use relative paths with folder name included

**3. "Maximum execution time exceeded"**
- Cause: Large folder upload/download timeout
- Solution: Increase `max_execution_time` in php.ini

**4. "Failed to create directories"**
- Cause: Permission issues
- Solution: Ensure uploads directory has write permissions (0755)

**5. Folder upload only uploads some files**
- Cause: `max_file_uploads` limit reached
- Solution: Increase `max_file_uploads` in php.ini

---

## Future Enhancements

Potential improvements to consider:

1. **Progress Bar** - Real-time upload progress indicator
2. **Drag & Drop** - Drag folders directly to browser
3. **Chunked Upload** - Split large folders into chunks
4. **Background Processing** - Queue large uploads/downloads
5. **ZIP Streaming** - Stream ZIP creation without temp files
6. **Folder Sharing** - Share folders with other users
7. **Folder Synchronization** - Auto-sync local folders
8. **Version Control** - Track folder changes over time

---

## Conclusion

The folder upload and download system leverages HTML5 directory APIs, PHP file handling, and ZIP compression to provide a seamless user experience. The implementation preserves folder structures, ensures security through proper validation, and handles large directories efficiently.

**Key Takeaways:**
- HTML5 `webkitdirectory` enables folder selection
- PHP preserves complete folder hierarchy
- ZipArchive provides efficient compression
- Security is enforced at every layer
- Performance tuning may be needed for large folders

---

**Document Version:** 1.0  
**Last Updated:** January 28, 2026  
**Author:** Innoventory Development Team
