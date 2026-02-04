# Quick Guide - New Folder & Upload Features

## How Users Can Use These Features

### 1. Creating a New Folder

```
Step 1: Click the "+ New" button in the sidebar
Step 2: From the dropdown menu, click "📁 New Folder"
Step 3: A dialog box will appear asking for the folder name
Step 4: Enter the desired folder name (e.g., "Project Files")
Step 5: Click OK
Step 6: The page will refresh and show your new folder in My Drive
```

**Folder names are sanitized to prevent security issues:**
- Special characters are removed
- Spaces, hyphens, and underscores are allowed
- Maximum flexibility with safe naming

### 2. Uploading Files

```
Step 1: Click the "+ New" button in the sidebar
Step 2: From the dropdown menu, click "📤 File Upload"
Step 3: Select one or multiple files from your computer
Step 4: Files are automatically uploaded and added to My Drive
Step 5: The page will refresh showing your uploaded files
```

**Supported actions after upload:**
- Download files
- Star files for quick access
- Delete unwanted files
- View file details

### 3. Uploading a Folder with All Contents

```
Step 1: Click the "+ New" button in the sidebar
Step 2: From the dropdown menu, click "📦 Folder Upload"
Step 3: Select a folder from your computer
Step 4: All files and subdirectories are uploaded
Step 5: Directory structure is preserved
Step 6: The page will refresh showing the uploaded folder
```

**Perfect for:**
- Uploading entire project directories
- Preserving folder structures
- Batch uploading organized content

## My Drive Features

### Viewing Your Files and Folders
- Files and folders appear in a grid layout
- Files show: 📄 icon or ⭐ if starred
- Folders show: 📁 icon
- Click the three-dot menu (⋮) to access options

### File Options
```
Click ⋮ on any file to:
- ⭐ Star/Unstar (for quick access in Starred section)
- 🗑 Delete (permanently remove the file)
- 📥 Download (download to your computer)
```

### Folder Options
```
Click ⋮ on any folder to:
- 🗑 Delete (remove folder and all contents inside)
```

## For Administrators

Admins have additional capabilities:

### Admin Dashboard Features
- Access own files/folders in "My Drive"
- Browse user files in "Users Drive"
- View user-uploaded folders in "Users Drive"
- Manage access to user content

### Admin-Specific Access
- **Users Drive** → View and manage files/folders for each user
- **My Drive** → Personal storage with folder support
- Both sections support the new folder and upload features

## Important Notes

### Folder Deletion
⚠️ **Warning:** Deleting a folder will permanently remove all files and subfolders inside it. There is no undo option after deletion.

### File/Folder Naming
- Folder names cannot contain: `< > : " / \ | ? *`
- These characters are automatically removed
- Empty names are rejected
- Names are trimmed of leading/trailing spaces

### Storage Location
- User files: `/uploads/user_[USER_ID]/`
- Admin files: `/uploads/user_[ADMIN_ID]/`
- Folder structures are maintained

### Upload Limits
- Browser-dependent (usually 100MB+ per file)
- Folder upload preserves directory structure
- Multiple files can be uploaded simultaneously

## Dropdown Menu UI/UX

The "+ New" button dropdown provides:

```
┌─────────────────┐
│ + New           │ ← Click to toggle menu
└────┬────────────┘
     │
     ├─→ 📁 New Folder
     │
     ├─→ 📤 File Upload
     │
     └─→ 📦 Folder Upload
```

**Auto-closes when:**
- You click an option
- You click outside the menu
- You select files to upload

## Browser Compatibility

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| New Folder | ✅ | ✅ | ✅ | ✅ |
| File Upload | ✅ | ✅ | ✅ | ✅ |
| Folder Upload | ✅ | ⚠️ | ⚠️ | ✅ |

✅ = Fully supported
⚠️ = Limited support (may require alternative method)

## Troubleshooting

### Folder not appearing after creation?
- Refresh the page manually (F5 or Ctrl+R)
- Check that you entered a valid folder name
- Ensure you have proper permissions

### Upload not working?
- Check internet connection
- File size shouldn't exceed browser limit
- Try uploading fewer files at once
- Use Chrome or Edge for folder uploads

### Can't delete a folder?
- Confirm you have write permissions
- Make sure the folder isn't in use
- Admin credentials may be required for user folders

### Need help?
- Contact the administrator
- Check server logs for error details
- Verify file permissions in file system
