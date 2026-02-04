<?php
require_once "../../session.php";
require_once "../../config.php";

/* ================= SECURITY ================= */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../index.php");
    exit;
}

$adminId = (int) ($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
if ($adminId <= 0) {
    header("Location: ../../index.php");
    exit;
}

/* ================= STAR TABLE ================= */
$db->query("
    CREATE TABLE IF NOT EXISTS starred_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        starred_by INT NOT NULL,
        owner_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_star (starred_by, owner_id, filename)
    )
");

/* ================= FILE PATH ================= */
$uploadDir = "../../uploads/user_" . $adminId;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}
$allItems = array_diff(scandir($uploadDir), ['.', '..']);

// Separate files and folders
$files = [];
$folders = [];
foreach ($allItems as $item) {
    $fullPath = $uploadDir . "/" . $item;
    if (is_dir($fullPath)) {
        $folders[] = $item;
    } else {
        $files[] = $item;
    }
}

/* ================= GET STARRED FOR ADMIN (OWN FILES) ================= */
$starredFiles = [];
$stmt = $db->prepare("SELECT filename FROM starred_files WHERE starred_by=? AND owner_id=?");
$stmt->bind_param("ii", $adminId, $adminId);
$stmt->execute();
$rs = $stmt->get_result();
while ($row = $rs->fetch_assoc()) {
    $starredFiles[] = $row['filename'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Drive (Admin) - Innoventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/main.css">
</head>
<body>

<div class="app-grid">

    <?php include "../../common/menu.php"; ?>
    <?php include "../../common/header.php"; ?>


    <main>
        <div class="dashboard-card" id="dropZone">

            <div class="dashboard-header">
                <h1>My Drive</h1>
                <span>Your uploaded files (Admin)</span>
            </div>

            <!-- Drag & Drop Note -->
            <div class="drag-drop-note">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9,16V10H5L12,3L19,10H15V16H9M5,20V18H19V20H5Z"/>
                </svg>
                <span><strong>Tip:</strong> Drag and drop files here to upload</span>
            </div>

            <?php if (empty($files) && empty($folders)): ?>
                <div class="empty-state">No files or folders uploaded yet.</div>
            <?php else: ?>
                <div class="file-grid">
                    <!-- Display Folders -->
                    <?php foreach ($folders as $folder): ?>
                        <?php
                            $safeFolder = htmlspecialchars($folder, ENT_QUOTES);
                            $menuId = "kebab_folder_" . md5($folder);
                        ?>

                        <div class="file-card">

                            <!-- KEBAB -->
                            <button class="kebab-btn" type="button"
                                    onclick="toggleKebab(event, '<?= $menuId ?>')">⋮</button>

                            <div class="kebab-dropdown" id="<?= $menuId ?>">
                                <button class="kebab-item"
                                        onclick="window.location.href='folder_view.php?folder=<?= urlencode($folder) ?>&user_id=<?= $adminId ?>'">
                                    📂 Open
                                </button>
                                
                                <button class="kebab-item"
                                        onclick="window.location.href='download_folder.php?folder=<?= urlencode($folder) ?>&user_id=<?= $adminId ?>'">
                                    📥 Download
                                </button>
                                
                                <button class="kebab-item"
                                        onclick="renameFolder('<?= $safeFolder ?>', <?= $adminId ?>)">
                                    ✏️ Rename
                                </button>
                                
                                <button class="kebab-item delete"
                                        onclick="deleteFolder('<?= $safeFolder ?>')">
                                    🗑 Delete
                                </button>
                            </div>

                            <div class="file-icon" style="cursor: pointer;" 
                                 onclick="window.location.href='folder_view.php?folder=<?= urlencode($folder) ?>&user_id=<?= $adminId ?>'">
                                📁
                            </div>

                            <div class="file-name" style="cursor: pointer;"
                                 onclick="window.location.href='folder_view.php?folder=<?= urlencode($folder) ?>&user_id=<?= $adminId ?>'">
                                <?= htmlspecialchars($folder) ?>
                            </div>

                            <a class="file-download" href="download_folder.php?folder=<?= urlencode($folder) ?>&user_id=<?= $adminId ?>">
                                Download
                            </a>
                        </div>

                    <?php endforeach; ?>

                    <!-- Display Files -->
                    <?php foreach ($files as $file): ?>
                        <?php
                            $isStarred = in_array($file, $starredFiles);
                            $safeFile = htmlspecialchars($file, ENT_QUOTES);
                            $menuId = "kebab_" . md5($file);
                        ?>

                        <div class="file-card">

                            <!-- KEBAB -->
                            <button class="kebab-btn" type="button"
                                    onclick="toggleKebab(event, '<?= $menuId ?>')">⋮</button>

                            <div class="kebab-dropdown" id="<?= $menuId ?>">
                                <button class="kebab-item"
                                        onclick="toggleStar('<?= $safeFile ?>', <?= $isStarred ? 'true' : 'false' ?>)">
                                    <?= $isStarred ? '⭐ Remove Star' : '⭐ Star File' ?>
                                </button>

                                <button class="kebab-item"
                                        onclick="previewFile('<?= $safeFile ?>', <?= (int)$adminId ?>)">
                                    👁️ Preview
                                </button>

                                <button class="kebab-item"
                                        onclick="renameFile('<?= $safeFile ?>', <?= (int)$adminId ?>)">
                                    ✏️ Rename
                                </button>

                                <button class="kebab-item delete"
                                        onclick="deleteFile('<?= $safeFile ?>')">
                                    🗑 Delete
                                </button>
                            </div>

                            <div class="file-icon" onclick="previewFile('<?= $safeFile ?>', <?= (int)$adminId ?>)" style="cursor: pointer;"><?= $isStarred ? "⭐" : "📄" ?></div>

                            <div class="file-name" onclick="previewFile('<?= $safeFile ?>', <?= (int)$adminId ?>)" style="cursor: pointer;"><?= htmlspecialchars($file) ?></div>

                            <a class="file-download"
                               href="../../pkg/file-management/download.php?user_id=<?= $adminId ?>&file=<?= urlencode($file) ?>">
                                Download
                            </a>
                        </div>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>

</div>

<!-- Upload Progress Container -->
<div id="uploadProgressContainer" class="upload-progress-container"></div>

<script>
// ==================== DRAG AND DROP UPLOAD ====================
const dropZone = document.getElementById('dropZone');
const uploadProgressContainer = document.getElementById('uploadProgressContainer');
let uploadQueue = [];

// Prevent default drag behaviors
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
    document.body.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

// Highlight drop zone when dragging over
['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.classList.add('drag-over');
}

function unhighlight(e) {
    dropZone.classList.remove('drag-over');
}

// Handle dropped files
dropZone.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const items = dt.items;
    
    // Check if any items are folders
    let hasFolder = false;
    const files = [];
    
    if (items) {
        // Check for folders using webkitGetAsEntry
        for (let i = 0; i < items.length; i++) {
            const entry = items[i].webkitGetAsEntry();
            if (entry && entry.isDirectory) {
                hasFolder = true;
                break;
            }
        }
    }
    
    if (hasFolder) {
        alert('To upload folders, please use the "+ New" button and select "Folder Upload" option.');
        return;
    }
    
    // Get actual files
    const fileList = dt.files;
    
    if (fileList.length > 0) {
        // Filter out any invalid files (0 bytes usually means folder)
        for (let i = 0; i < fileList.length; i++) {
            if (fileList[i].size > 0 || fileList[i].type !== '') {
                files.push(fileList[i]);
            }
        }
        
        if (files.length === 0) {
            alert('To upload folders, please use the "+ New" button and select "Folder Upload" option.');
            return;
        }
        
        handleFiles(files);
    }
}

async function handleFiles(files) {
    uploadProgressContainer.classList.add('show');
    
    for (let i = 0; i < files.length; i++) {
        await uploadFile(files[i]);
    }
    
    // Reload page after all uploads complete
    setTimeout(() => {
        location.reload();
    }, 1500);
}

async function uploadFile(file) {
    const uploadId = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    // Create progress item
    const progressItem = createProgressItem(uploadId, file.name, file.size);
    uploadProgressContainer.appendChild(progressItem);
    
    const formData = new FormData();
    formData.append('upload', file);
    
    try {
        const xhr = new XMLHttpRequest();
        
        // Update progress bar
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                updateProgress(uploadId, percentComplete);
            }
        });
        
        // Handle completion
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                markComplete(uploadId, true);
            } else {
                markComplete(uploadId, false, 'Upload failed');
            }
        });
        
        // Handle errors
        xhr.addEventListener('error', () => {
            markComplete(uploadId, false, 'Network error');
        });
        
        xhr.open('POST', 'upload.php', true);
        xhr.send(formData);
        
        // Wait for completion
        await new Promise((resolve) => {
            xhr.addEventListener('loadend', resolve);
        });
        
    } catch (err) {
        console.error(err);
        markComplete(uploadId, false, err.message);
    }
}

function createProgressItem(id, filename, size) {
    const div = document.createElement('div');
    div.className = 'upload-progress-item';
    div.id = id;
    
    const sizeStr = formatBytes(size);
    
    div.innerHTML = `
        <div class="upload-file-info">
            <div class="upload-file-icon">📄</div>
            <div class="upload-file-details">
                <div class="upload-file-name" title="${filename}">${filename}</div>
                <div class="upload-file-size">${sizeStr}</div>
            </div>
        </div>
        <div class="upload-progress-bar">
            <div class="upload-progress-fill" style="width: 0%"></div>
        </div>
        <div class="upload-progress-text">0%</div>
    `;
    
    return div;
}

function updateProgress(id, percent) {
    const item = document.getElementById(id);
    if (!item) return;
    
    const fill = item.querySelector('.upload-progress-fill');
    const text = item.querySelector('.upload-progress-text');
    
    fill.style.width = percent + '%';
    text.textContent = Math.round(percent) + '%';
}

function markComplete(id, success, errorMsg = '') {
    const item = document.getElementById(id);
    if (!item) return;
    
    const text = item.querySelector('.upload-progress-text');
    
    if (success) {
        text.textContent = '✓ Complete';
        text.classList.add('upload-success');
    } else {
        text.textContent = '✗ ' + (errorMsg || 'Failed');
        text.classList.add('upload-error');
    }
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// ==================== EXISTING FUNCTIONS ====================
function toggleKebab(e, id) {
    e.stopPropagation();
    document.querySelectorAll('.kebab-dropdown').forEach(d => {
        if (d.id !== id) d.classList.remove('show');
    });
    const el = document.getElementById(id);
    if (el) el.classList.toggle('show');
}

document.addEventListener('click', function() {
    document.querySelectorAll('.kebab-dropdown').forEach(d => d.classList.remove('show'));
});

async function toggleStar(file, isStarred) {
    const action = isStarred ? 'unstar' : 'star';

    const formData = new FormData();
    formData.append('action', action);
    formData.append('filename', file);
    formData.append('owner_id', <?= $adminId ?>); // owner is admin itself

    try {
        const res = await fetch('star_action.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message || 'Star action failed');
    } catch (err) {
        console.error(err);
        alert('Request failed');
    }
}

async function deleteFile(file) {
    if (!confirm("Delete file: " + file + " ?")) return;

    const formData = new FormData();
    formData.append('filename', file);
    formData.append('user_id', <?= $adminId ?>);

    try {
        const res = await fetch('delete_file.php', { method: 'POST', body: formData });
        const text = await res.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            alert('Server error: ' + text.substring(0, 200));
            return;
        }

        if (data.success) location.reload();
        else alert(data.message || 'Delete failed');
    } catch (err) {
        console.error(err);
        alert('Request failed: ' + err.message);
    }
}

async function deleteFolder(folder) {
    if (!confirm("Delete folder: " + folder + " and all its contents?")) return;

    const formData = new FormData();
    formData.append('folder_name', folder);
    formData.append('user_id', <?= $adminId ?>);

    try {
        const res = await fetch('delete_folder.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) location.reload();
        else alert(data.message || 'Delete failed');
    } catch (err) {
        console.error(err);
        alert('Request failed: ' + err.message);
    }
}

async function renameFolder(oldName, userId) {
    const newName = prompt("Rename folder to:", oldName);
    if (!newName || newName.trim() === '' || newName === oldName) return;

    const formData = new FormData();
    formData.append('old_name', oldName);
    formData.append('new_name', newName.trim());
    formData.append('user_id', userId);

    try {
        const res = await fetch('rename_folder.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) location.reload();
        else alert(data.message || 'Rename failed');
    } catch (err) {
        console.error(err);
        alert('Request failed: ' + err.message);
    }
}

async function renameFile(oldName, userId) {
    const newName = prompt("Rename file to:", oldName);
    if (!newName || newName.trim() === '' || newName === oldName) return;

    const formData = new FormData();
    formData.append('old_name', oldName);
    formData.append('new_name', newName.trim());
    formData.append('user_id', userId);

    try {
        const res = await fetch('rename_file.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) location.reload();
        else alert(data.message || 'Rename failed');
    } catch (err) {
        console.error(err);
        alert('Request failed: ' + err.message);
    }
}

async function previewFile(filename, userId) {
    try {
        const res = await fetch(`preview.php?file=${encodeURIComponent(filename)}&user_id=${userId}`);
        const data = await res.json();

        if (!data.success) {
            alert(data.message || 'Failed to load preview');
            return;
        }

        showPreviewModal(data);
    } catch (err) {
        console.error(err);
        alert('Failed to load preview: ' + err.message);
    }
}

function showPreviewModal(fileData) {
    const modal = document.getElementById('previewModal');
    const title = document.getElementById('previewTitle');
    const body = document.getElementById('previewBody');
    const info = document.getElementById('previewInfo');
    const downloadLink = document.getElementById('previewDownload');

    title.textContent = fileData.filename;
    info.textContent = `${fileData.sizeFormatted} • ${fileData.extension.toUpperCase()}`;
    downloadLink.href = fileData.previewUrl;
    downloadLink.download = fileData.filename;

    body.innerHTML = '';

    if (!fileData.canPreview) {
        body.innerHTML = `
            <div class="preview-not-supported">
                <svg fill="currentColor" viewBox="0 0 24 24"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" /></svg>
                <div style="margin-top: 12px; font-size: 15px; font-weight: 500;">Preview not available</div>
                <div style="margin-top: 4px;">Download the file to view it</div>
            </div>
        `;
    } else if (fileData.type === 'image') {
        const img = document.createElement('img');
        img.src = fileData.previewUrl;
        img.alt = fileData.filename;
        body.appendChild(img);
    } else if (fileData.type === 'video') {
        const video = document.createElement('video');
        video.controls = true;
        video.src = fileData.previewUrl;
        video.style.maxWidth = '100%';
        video.style.maxHeight = '70vh';
        body.appendChild(video);
    } else if (fileData.type === 'audio') {
        const audio = document.createElement('audio');
        audio.controls = true;
        audio.src = fileData.previewUrl;
        audio.style.width = '100%';
        body.appendChild(audio);
    } else if (fileData.type === 'pdf') {
        const iframe = document.createElement('iframe');
        iframe.src = fileData.previewUrl;
        body.appendChild(iframe);
    } else if (fileData.type === 'text') {
        const pre = document.createElement('div');
        pre.className = 'preview-text-content';
        pre.textContent = fileData.content;
        body.appendChild(pre);
    }

    modal.classList.add('show');
}

function closePreviewModal() {
    const modal = document.getElementById('previewModal');
    modal.classList.remove('show');
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePreviewModal();
    }
});

// Close modal on background click
document.getElementById('previewModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closePreviewModal();
    }
});

</script>

<!-- Preview Modal -->
<div id="previewModal" class="preview-modal">
    <div class="preview-content">
        <div class="preview-header">
            <h3 class="preview-title" id="previewTitle">File Preview</h3>
            <button class="preview-close" onclick="closePreviewModal()">×</button>
        </div>
        <div class="preview-body" id="previewBody">
            <!-- Dynamic content will be loaded here -->
        </div>
        <div class="preview-footer">
            <span class="preview-info" id="previewInfo"></span>
            <div class="preview-actions">
                <a id="previewDownload" class="preview-btn-download" download>Download</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
