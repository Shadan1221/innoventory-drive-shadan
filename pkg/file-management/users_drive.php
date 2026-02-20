<?php
require_once "../../session.php";
require_once "../../config.php";

/* ================= SECURITY ================= */
if (!isset($_SESSION['loggedin']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../index.php");
    exit;
}

$adminId = (int) ($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
if ($adminId <= 0) {
    header("Location: ../../index.php");
    exit;
}

/* ================= STARRED TABLE (ADMIN-FRIENDLY) ================= */
/*
This schema supports:
- who starred the file  => starred_by
- whose file it is      => owner_id
- which file            => filename
*/
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

/* ================= GET USER LIST ================= */
$usersList = [];
$res = $db->query("
    SELECT id, name, status
    FROM users
    WHERE role='user'
    ORDER BY 
        CASE status 
            WHEN 'approved' THEN 1 
            WHEN 'pending' THEN 2 
            WHEN 'denied' THEN 3 
            ELSE 4 
        END, 
        name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $usersList[] = $row;
    }
}

/* ================= WHICH USER TO VIEW ================= */
$viewUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

$viewUserName = "";
if ($viewUserId > 0) {
    $stmt = $db->prepare("SELECT name FROM users WHERE id=? AND role='user' LIMIT 1");
    $stmt->bind_param("i", $viewUserId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($r) {
        $viewUserName = $r['name'];
    } else {
        $viewUserId = 0;
    }
}

/* ================= FILES + STARRED LIST ================= */
$files = [];
$starredFiles = [];

if ($viewUserId > 0) {
    $uploadDir = "../../uploads/user_" . $viewUserId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $files = array_diff(scandir($uploadDir), ['.', '..']);

    // Load starred files for THIS admin + THIS viewed user
    $stmt = $db->prepare("
        SELECT filename
        FROM starred_files
        WHERE starred_by = ? AND owner_id = ?
    ");
    $stmt->bind_param("ii", $adminId, $viewUserId);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $starredFiles[] = $row['filename'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users Drive - Innoventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/main.css">
    <style>
        .user-link.denied { opacity: 0.7; }
        .user-link.denied:hover { opacity: 1; }
        .user-link.pending { opacity: 0.75; }
        .user-link.pending:hover { opacity: 1; }
        .user-link.other-status { opacity: 0.6; }
        .user-link.other-status:hover { opacity: 1; }
        .user-search-input:focus { outline: none; border-color: var(--accent) !important; }
        [data-theme="dark"] .user-search-input { background: rgba(255,255,255,0.05); border-color: var(--border); color: var(--text); }
        [data-theme="dark"] .sort-btn { background: rgba(255,255,255,0.05); border-color: var(--border); color: var(--text); }
        [data-theme="dark"] .sort-btn.active, [data-theme="dark"] .sort-btn[style*="var(--accent)"] { background: var(--accent) !important; color: white !important; }
    </style>
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
                <h1>Users Drive</h1>
                <span>Browse user files</span>
            </div>

            <div class="drive-layout with-sidebar">

                <!-- LEFT USERS -->
                <aside class="drive-users">
                    <h3>Users</h3>

                    <!-- Search and Sort Controls -->
                    <div class="users-controls" style="margin-bottom: 12px;">
                        <input type="text" id="userSearch" class="user-search-input" placeholder="Search users..." autocomplete="off" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; background: var(--bg); color: var(--text); margin-bottom: 8px;">
                        <div style="display: flex; gap: 6px;">
                            <button type="button" id="sortNameBtn" class="sort-btn active" onclick="sortUsers('name')" style="flex: 1; padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--accent); color: white; font-size: 12px; cursor: pointer;">A-Z</button>
                            <button type="button" id="sortStatusBtn" class="sort-btn" onclick="sortUsers('status')" style="flex: 1; padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg); color: var(--text); font-size: 12px; cursor: pointer;">Status</button>
                        </div>
                    </div>

                    <div id="usersList">
                    <?php if (empty($usersList)): ?>
                        <div class="empty-state" style="padding: 20px 0;">No users found.</div>
                    <?php else: ?>
                        <?php foreach ($usersList as $u): ?>
                            <?php 
                                $active = ($viewUserId === (int)$u['id']); 
                                $userStatus = $u['status'];
                                $statusLabel = '';
                                $statusClass = '';
                                if ($userStatus === 'denied') {
                                    $statusLabel = ' <span style="color: #ef4444; font-size: 11px;">(Denied)</span>';
                                    $statusClass = ' denied';
                                } elseif ($userStatus === 'pending') {
                                    $statusLabel = ' <span style="color: #f59e0b; font-size: 11px;">(Pending)</span>';
                                    $statusClass = ' pending';
                                } elseif ($userStatus !== 'approved') {
                                    $statusLabel = ' <span style="color: #6b7280; font-size: 11px;">(' . ucfirst(htmlspecialchars($userStatus)) . ')</span>';
                                    $statusClass = ' other-status';
                                }
                                $displayName = htmlspecialchars($u['name']) . $statusLabel;
                            ?>
                            <a class="user-link <?= $active ? 'active' : '' ?><?= $statusClass ?>"
                               href="users_drive.php?user_id=<?= (int)$u['id'] ?>"
                               data-name="<?= htmlspecialchars(strtolower($u['name'])) ?>"
                               data-status="<?= htmlspecialchars($u['status']) ?>">
                                <?= $displayName ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                    <div id="noUsersMsg" class="empty-state" style="padding: 20px 0; display: none;">No users match your search.</div>
                </aside>

                <!-- RIGHT FILES -->
                <section class="drive-files">

                    <?php if ($viewUserId <= 0): ?>
                        <div class="empty-state">Select a user to view their files.</div>
                    <?php else: ?>

                        <div style="margin-bottom:16px;">
                            <strong>Files of:</strong>
                            <span style="color:var(--muted);">
                                <?= htmlspecialchars($viewUserName) ?> (User ID: <?= $viewUserId ?>)
                            </span>
                        </div>

                        <?php if (empty($files)): ?>
                            <div class="empty-state">No files uploaded.</div>
                        <?php else: ?>

                            <div class="file-grid">
                                <?php foreach ($files as $file): ?>
                                    <?php
                                        $isStarred = in_array($file, $starredFiles);
                                        $safeFile = htmlspecialchars($file, ENT_QUOTES);
                                        $menuId = "kebab_" . md5($file);
                                    ?>

                                    <div class="file-card">

                                        <!-- kebab button -->
                                        <button class="kebab-btn" type="button"
                                        onclick="toggleKebab(event, '<?= $menuId ?>')">
                                        ⋮
                                        </button>


                                        <!-- kebab dropdown -->
                                        <div class="kebab-dropdown" id="<?= $menuId ?>">
                                            <button class="kebab-item"
                                                    onclick="toggleStar('<?= $safeFile ?>', <?= $isStarred ? 'true' : 'false' ?>, <?= $viewUserId ?>)">
                                                <?= $isStarred ? '⭐ Remove Star' : '⭐ Star File' ?>
                                            </button>
                                            
                                            <button class="kebab-item"
                                                    onclick="previewFile('<?= $safeFile ?>', <?= $viewUserId ?>)">
                                                👁️ Preview
                                            </button>
                                            
                                            <button class="kebab-item"
                                                    onclick="renameFile('<?= $safeFile ?>', <?= $viewUserId ?>)">
                                                ✏️ Rename
                                            </button>

                                            <button class="kebab-item delete"
                                                    onclick="deleteFile('<?= $safeFile ?>', <?= $viewUserId ?>)">
                                                🗑 Delete
                                            </button>
                                        </div>

                                        <div class="file-icon" onclick="previewFile('<?= $safeFile ?>', <?= $viewUserId ?>)" style="cursor: pointer;">
                                            <?= $isStarred
                                                ? '<svg class="ico-star" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27Z"/></svg>'
                                                : '<svg class="ico-file" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 2h8l4 4v16H6V2Zm8 1.5V7h3.5L14 3.5Z"/></svg>'
                                            ?>
                                        </div>
                                        <div class="file-name" onclick="previewFile('<?= $safeFile ?>', <?= $viewUserId ?>)" style="cursor: pointer;"><?= htmlspecialchars($file) ?></div>

                                        <a class="file-download"
                                           href="../../pkg/file-management/download.php?user_id=<?= $viewUserId ?>&file=<?= urlencode($file) ?>">
                                            Download
                                        </a>
                                    </div>

                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>
                    <?php endif; ?>

                </section>

            </div>

        </div>
    </main>

</div>

<script>
function toggleKebab(e, id) {
    e.stopPropagation();

    // close other dropdowns
    document.querySelectorAll('.kebab-dropdown').forEach(d => {
        if (d.id !== id) d.classList.remove('show');
    });

    const el = document.getElementById(id);
    if (el) el.classList.toggle('show');
}

// close all on click outside
document.addEventListener('click', function() {
    document.querySelectorAll('.kebab-dropdown').forEach(d => d.classList.remove('show'));
});

async function toggleStar(file, isStarred, ownerId) {
    const action = isStarred ? 'unstar' : 'star';

    const formData = new FormData();
    formData.append('action', action);
    formData.append('filename', file);
    formData.append('owner_id', ownerId); // important for users drive

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

async function previewFile(file, userId) {
    try {
        const res = await fetch(`preview.php?file=${encodeURIComponent(file)}&user_id=${userId}`);
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

async function renameFile(oldName, userId) {
    const newName = await showRenameModal({
        title: 'Rename file',
        label: 'New file name',
        defaultValue: oldName,
        confirmText: 'Rename'
    });
    if (!newName || newName.trim() === '' || newName === oldName) return;

    const formData = new FormData();
    formData.append('old_name', oldName);
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

async function deleteFile(file, ownerId) {
    const ok = await showConfirmModal({
        title: 'Delete file',
        message: `Delete "${file}"?`,
        confirmText: 'Delete',
        danger: true
    });
    if (!ok) return;

    const formData = new FormData();
    formData.append('filename', file);
    formData.append('user_id', ownerId); // delete for that owner

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

function showConfirmModal({ title, message, confirmText = 'Confirm', cancelText = 'Cancel', danger = false }) {
    return new Promise((resolve) => {
        const modal = document.getElementById('actionModal');
        const titleEl = modal.querySelector('[data-action-title]');
        const messageEl = modal.querySelector('[data-action-message]');
        const inputEl = modal.querySelector('[data-action-input]');
        const hintEl = modal.querySelector('[data-action-hint]');
        const cancelBtn = modal.querySelector('[data-action-cancel]');
        const confirmBtn = modal.querySelector('[data-action-confirm]');
        const closeBtn = modal.querySelector('[data-action-close]');

        titleEl.textContent = title || 'Confirm';
        messageEl.textContent = message || '';
        inputEl.style.display = 'none';
        inputEl.value = '';
        hintEl.style.display = 'none';
        hintEl.textContent = '';
        confirmBtn.textContent = confirmText;
        confirmBtn.className = `action-btn ${danger ? 'action-btn-danger' : 'action-btn-primary'}`;
        confirmBtn.disabled = false;
        cancelBtn.textContent = cancelText;

        function cleanup(result) {
            modal.classList.remove('show');
            cancelBtn.removeEventListener('click', onCancel);
            confirmBtn.removeEventListener('click', onConfirm);
            closeBtn.removeEventListener('click', onCancel);
            modal.removeEventListener('click', onBackdrop);
            document.removeEventListener('keydown', onKey);
            resolve(result);
        }

        function onCancel() {
            cleanup(false);
        }

        function onConfirm() {
            cleanup(true);
        }

        function onBackdrop(e) {
            if (e.target === modal) onCancel();
        }

        function onKey(e) {
            if (e.key === 'Escape') onCancel();
        }

        cancelBtn.addEventListener('click', onCancel);
        confirmBtn.addEventListener('click', onConfirm);
        closeBtn.addEventListener('click', onCancel);
        modal.addEventListener('click', onBackdrop);
        document.addEventListener('keydown', onKey);

        modal.classList.add('show');
        confirmBtn.focus();
    });
}

function showRenameModal({ title, label, defaultValue = '', confirmText = 'Rename', cancelText = 'Cancel' }) {
    return new Promise((resolve) => {
        const modal = document.getElementById('actionModal');
        const titleEl = modal.querySelector('[data-action-title]');
        const messageEl = modal.querySelector('[data-action-message]');
        const inputEl = modal.querySelector('[data-action-input]');
        const hintEl = modal.querySelector('[data-action-hint]');
        const cancelBtn = modal.querySelector('[data-action-cancel]');
        const confirmBtn = modal.querySelector('[data-action-confirm]');
        const closeBtn = modal.querySelector('[data-action-close]');

        titleEl.textContent = title || 'Rename';
        messageEl.textContent = label || 'New name';
        inputEl.style.display = 'block';
        inputEl.value = defaultValue;
        inputEl.setSelectionRange(0, inputEl.value.length);
        hintEl.style.display = 'block';
        hintEl.textContent = 'Press Enter to confirm or Escape to cancel.';
        confirmBtn.textContent = confirmText;
        confirmBtn.className = 'action-btn action-btn-primary';
        cancelBtn.textContent = cancelText;

        function updateState() {
            confirmBtn.disabled = inputEl.value.trim() === '';
        }

        function cleanup(result) {
            modal.classList.remove('show');
            cancelBtn.removeEventListener('click', onCancel);
            confirmBtn.removeEventListener('click', onConfirm);
            closeBtn.removeEventListener('click', onCancel);
            modal.removeEventListener('click', onBackdrop);
            inputEl.removeEventListener('input', updateState);
            inputEl.removeEventListener('keydown', onInputKey);
            document.removeEventListener('keydown', onKey);
            resolve(result);
        }

        function onCancel() {
            cleanup(null);
        }

        function onConfirm() {
            if (inputEl.value.trim() === '') return;
            cleanup(inputEl.value.trim());
        }

        function onBackdrop(e) {
            if (e.target === modal) onCancel();
        }

        function onKey(e) {
            if (e.key === 'Escape') onCancel();
        }

        function onInputKey(e) {
            if (e.key === 'Enter') onConfirm();
        }

        cancelBtn.addEventListener('click', onCancel);
        confirmBtn.addEventListener('click', onConfirm);
        closeBtn.addEventListener('click', onCancel);
        modal.addEventListener('click', onBackdrop);
        inputEl.addEventListener('input', updateState);
        inputEl.addEventListener('keydown', onInputKey);
        document.addEventListener('keydown', onKey);

        updateState();
        modal.classList.add('show');
        inputEl.focus();
    });
}

// ==================== USER SEARCH & SORT ====================
let currentSort = 'name';
let sortDirection = 'asc';

document.getElementById('userSearch')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase().trim();
    filterUsers(searchTerm);
});

function filterUsers(searchTerm) {
    const userLinks = document.querySelectorAll('#usersList .user-link');
    let visibleCount = 0;

    userLinks.forEach(link => {
        const name = link.getAttribute('data-name') || '';
        if (searchTerm === '' || name.includes(searchTerm)) {
            link.style.display = '';
            visibleCount++;
        } else {
            link.style.display = 'none';
        }
    });

    const noMsg = document.getElementById('noUsersMsg');
    if (noMsg) {
        noMsg.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

function sortUsers(by) {
    const container = document.getElementById('usersList');
    if (!container) return;

    const links = Array.from(container.querySelectorAll('.user-link'));
    if (links.length === 0) return;

    // Toggle direction if same column
    if (currentSort === by) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort = by;
        sortDirection = 'asc';
    }

    // Update button styles
    const nameBtn = document.getElementById('sortNameBtn');
    const statusBtn = document.getElementById('sortStatusBtn');
    if (nameBtn && statusBtn) {
        nameBtn.style.background = by === 'name' ? 'var(--accent)' : 'var(--bg)';
        nameBtn.style.color = by === 'name' ? 'white' : 'var(--text)';
        statusBtn.style.background = by === 'status' ? 'var(--accent)' : 'var(--bg)';
        statusBtn.style.color = by === 'status' ? 'white' : 'var(--text)';
    }

    links.sort((a, b) => {
        let valA, valB;
        if (by === 'name') {
            valA = a.getAttribute('data-name') || '';
            valB = b.getAttribute('data-name') || '';
        } else {
            valA = a.getAttribute('data-status') || '';
            valB = b.getAttribute('data-status') || '';
        }

        let cmp = valA.localeCompare(valB);
        return sortDirection === 'asc' ? cmp : -cmp;
    });

    links.forEach(link => container.appendChild(link));
}
</script>

<!-- Action Modal -->
<div id="actionModal" class="action-modal" aria-hidden="true">
    <div class="action-content" role="dialog" aria-modal="true">
        <div class="action-header">
            <h3 class="action-title" data-action-title>Action</h3>
            <button class="action-close" type="button" data-action-close>×</button>
        </div>
        <div class="action-body">
            <p class="action-message" data-action-message></p>
            <input class="action-input" data-action-input type="text" autocomplete="off" />
            <div class="action-hint" data-action-hint></div>
        </div>
        <div class="action-footer">
            <button class="action-btn action-btn-ghost" type="button" data-action-cancel>Cancel</button>
            <button class="action-btn action-btn-primary" type="button" data-action-confirm>Confirm</button>
        </div>
    </div>
</div>

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
