<?php
require_once "../../session.php";
require_once "../../config.php";

/* ================= SECURITY ================= */
if (!isset($_SESSION['loggedin'])) {
    header("Location: ../../index.php");
    exit;
}

$loggedInUserId = (int) ($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$role = $_SESSION['role'] ?? 'user';

/* ================= GET FOLDER ================= */
$folderName = $_GET['folder'] ?? '';
$viewUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $loggedInUserId;

// Security check
if ($role !== 'admin' && $viewUserId !== $loggedInUserId) {
    die("Unauthorized");
}

if (empty($folderName)) {
    header("Location: my_drive.php");
    exit;
}

// Sanitize folder path to prevent traversal but allow nested folders
$folderName = str_replace(['..', '\\'], ['', '/'], $folderName);
$folderName = ltrim($folderName, '/');
$folderName = preg_replace('#/+#', '/', $folderName);

$baseDir = "../../uploads/user_" . $viewUserId;
$folderPath = $baseDir . "/" . $folderName;

// Verify folder exists
if (!is_dir($folderPath)) {
    header("Location: my_drive.php?error=folder_not_found");
    exit;
}

/* ================= GET FOLDER CONTENTS ================= */
$allItems = array_diff(scandir($folderPath), ['.', '..']);

// Separate files and subfolders
$files = [];
$subfolders = [];
foreach ($allItems as $item) {
    $fullPath = $folderPath . "/" . $item;
    if (is_dir($fullPath)) {
        $subfolders[] = $item;
    } else {
        $files[] = $item;
    }
}

/* ================= GET STARRED FILES ================= */
$starredFiles = [];
$stmt = $db->prepare("SELECT filename FROM starred_files WHERE starred_by = ?");
$stmt->bind_param("i", $viewUserId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $starredFiles[] = $row['filename'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($folderName) ?> - Innoventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/main.css">
</head>

<body>

<div class="app-grid">

    <!-- SIDEBAR -->
    <?php include "../../common/menu.php"; ?>
    <?php include "../../common/header.php"; ?>

    <!-- MAIN -->
    <main>
        <div class="dashboard-card">

            <div class="dashboard-header">
                <div>
                    <?php
                        $hasParent = strpos($folderName, '/') !== false;
                        $parentFolder = $hasParent ? dirname($folderName) : '';
                        $backHref = $hasParent
                            ? 'folder_view.php?folder=' . urlencode($parentFolder) . '&user_id=' . $viewUserId
                            : ($role === 'admin' ? 'admin_drive.php' : 'my_drive.php');
                    ?>
                    <a href="<?= $backHref ?>" style="text-decoration: none; color: var(--accent);">← Back</a>
                    <h1 style="margin-top: 10px;">
                        <svg class="ico-folder" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="vertical-align: -6px; margin-right: 6px; width: 28px; height: 28px;"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2Z"/></svg>
                        <?= htmlspecialchars($folderName) ?>
                    </h1>
                </div>
                <span><?= count($files) ?> file(s), <?= count($subfolders) ?> folder(s)</span>
            </div>

            <section class="drive-files" id="dropZone">

                <!-- Drag & Drop Note -->
                <div class="drag-drop-note">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9,16V10H5L12,3L19,10H15V16H9M5,20V18H19V20H5Z"/>
                    </svg>
                    <span><strong>Tip:</strong> Drag and drop files here to upload to this folder</span>
                </div>

                <?php if (empty($files) && empty($subfolders)): ?>
                    <div class="empty-state">This folder is empty.</div>
                <?php else: ?>

                    <div class="file-grid">
                        <!-- Display Subfolders -->
                        <?php foreach ($subfolders as $subfolder): ?>
                            <?php
                            $safeSubfolder = htmlspecialchars($subfolder, ENT_QUOTES);
                            $subfolderPath = $folderName . '/' . $subfolder;
                            ?>

                            <div class="file-card">
                                <div class="file-icon" style="cursor: pointer;" onclick="window.location.href='folder_view.php?folder=<?= urlencode($subfolderPath) ?>&user_id=<?= $viewUserId ?>'">
                                    <svg class="ico-folder" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2Z"/></svg>
                                </div>
                                <div class="file-name" style="cursor: pointer;" onclick="window.location.href='folder_view.php?folder=<?= urlencode($subfolderPath) ?>&user_id=<?= $viewUserId ?>'">
                                    <?= htmlspecialchars($subfolder) ?>
                                </div>
                                <a class="file-download" href="folder_view.php?folder=<?= urlencode($subfolderPath) ?>&user_id=<?= $viewUserId ?>">
                                    Open
                                </a>
                            </div>

                        <?php endforeach; ?>

                        <!-- Display Files -->
                        <?php foreach ($files as $file): ?>
                            <?php
                            $isStarred = in_array($folderName . '/' . $file, $starredFiles);
                                $safeFile = htmlspecialchars($file, ENT_QUOTES);
                                $menuId = "kebab_" . md5($file);
                            ?>

                            <div class="file-card">

                                <!-- kebab -->
                                <button class="kebab-btn" type="button"
                                        onclick="toggleKebab(event, '<?= $menuId ?>')">
                                    ⋮
                                </button>

                                <div class="kebab-dropdown" id="<?= $menuId ?>">
                                    <button class="kebab-item"
                                            onclick="toggleStarInFolder('<?= $safeFile ?>', '<?= htmlspecialchars($folderName, ENT_QUOTES) ?>', <?= $isStarred ? 'true' : 'false' ?>, <?= (int)$viewUserId ?>)">
                                        <?= $isStarred ? '⭐ Remove Star' : '⭐ Star File' ?>
                                    </button>
                                    
                                    <button class="kebab-item"
                                            onclick="previewFileInFolder('<?= $safeFile ?>', '<?= htmlspecialchars($folderName, ENT_QUOTES) ?>', <?= (int)$viewUserId ?>)">
                                        👁️ Preview
                                    </button>
                                    
                                    <button class="kebab-item"
                                            onclick="renameFileInFolder('<?= $safeFile ?>', '<?= htmlspecialchars($folderName, ENT_QUOTES) ?>', <?= (int)$viewUserId ?>)">
                                        ✏️ Rename
                                    </button>
                                    
                                    <button class="kebab-item delete"
                                            onclick="deleteFileInFolder('<?= $safeFile ?>', '<?= htmlspecialchars($folderName, ENT_QUOTES) ?>', <?= (int)$viewUserId ?>)">
                                        🗑 Delete
                                    </button>
                                </div>

                                <div class="file-icon">
                                    <?= $isStarred
                                        ? '<svg class="ico-star" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27Z"/></svg>'
                                        : '<svg class="ico-file" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 2h8l4 4v16H6V2Zm8 1.5V7h3.5L14 3.5Z"/></svg>'
                                    ?>
                                </div>
                                <div class="file-name"><?= htmlspecialchars($file) ?></div>

                                <a class="file-download"
                                   href="../../pkg/file-management/download.php?user_id=<?= $viewUserId ?>&file=<?= urlencode($folderName . '/' . $file) ?>">
                                    Download
                                </a>
                            </div>

                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

            </section>

        </div>
    </main>

</div>

<!-- Upload Progress Container -->
<div id="uploadProgressContainer" class="upload-progress-container"></div>

<script>
// ==================== DRAG AND DROP UPLOAD ====================
const dropZone = document.getElementById('dropZone');
const uploadProgressContainer = document.getElementById('uploadProgressContainer');
const targetFolder = <?= json_encode($folderName) ?>;

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
        alert('Cannot drag and drop folders into folders. Please upload files only.');
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
            alert('Cannot drag and drop folders into folders. Please upload files only.');
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
    formData.append('target_folder', targetFolder);
    
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
        
        xhr.open('POST', 'upload_to_folder.php', true);
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
            <div class="upload-file-icon"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 2h8l4 4v16H6V2Zm8 1.5V7h3.5L14 3.5Z"/></svg></div>
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

async function toggleStarInFolder(file, folder, isStarred, userId) {
    const action = isStarred ? 'unstar' : 'star';
    const fullPath = folder + '/' + file;

    const formData = new FormData();
    formData.append('action', action);
    formData.append('filename', fullPath);
    formData.append('user_id', userId);

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

async function previewFileInFolder(file, folder, userId) {
    const fullPath = folder + '/' + file;
    
    try {
        const res = await fetch(`preview.php?file=${encodeURIComponent(fullPath)}&user_id=${userId}`);
        const data = await res.json();

        if (!data.success) {
            alert(data.message || 'Preview failed');
            return;
        }

        showPreviewModal(data);
    } catch (err) {
        console.error(err);
        alert('Preview request failed');
    }
}

async function renameFileInFolder(oldName, folder, userId) {
    const newName = prompt('Rename file to:', oldName);
    if (!newName || newName === oldName) return;

    const oldPath = folder + '/' + oldName;

    const formData = new FormData();
    formData.append('old_name', oldPath);
    formData.append('new_name', newName);
    formData.append('user_id', userId);

    try {
        const res = await fetch('rename_file.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) location.reload();
        else alert(data.message || 'Rename failed');
    } catch (err) {
        console.error(err);
        alert('Rename request failed');
    }
}

async function deleteFileInFolder(file, folder, userId) {
    if (!confirm("Delete file: " + file + " ?")) return;

    const formData = new FormData();
    formData.append('filename', folder + '/' + file);
    formData.append('user_id', userId);

    try {
        const res = await fetch('delete_file.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) location.reload();
        else alert(data.message || 'Delete failed');
    } catch (err) {
        console.error(err);
        alert('Request failed: ' + err.message);
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
