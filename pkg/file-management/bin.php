<?php
require_once "../../session.php";
require_once "../../config.php";

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit;
}

$currentUserId = (int) ($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$role = $_SESSION['role'] ?? 'user';
$targetUserId = $currentUserId;
if ($role === 'admin' && isset($_GET['user_id'])) {
    $targetUserId = (int) $_GET['user_id'];
}

// Ensure table exists
$db->query("
    CREATE TABLE IF NOT EXISTS trashed_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_path VARCHAR(255) NOT NULL,
        trashed_path VARCHAR(255) NOT NULL,
        deleted_by INT NOT NULL,
        deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_file (user_id, filename)
    )
");

$trashed = [];
$stmt = $db->prepare("SELECT filename, trashed_path, original_path, deleted_at FROM trashed_files WHERE user_id = ? ORDER BY deleted_at DESC");
$stmt->bind_param("i", $targetUserId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $trashed[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bin - Innoventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/main.css">
</head>
<body>
<div class="app-grid">
    <?php include "../../common/menu.php"; ?>
    <?php include "../../common/header.php"; ?>

    <main>
        <div class="dashboard-card">
            <div class="dashboard-header">
                <h1>Bin</h1>
                <span>Files you've deleted<?php if ($role==='admin' && $targetUserId!==$currentUserId) echo " (user {$targetUserId})"; ?></span>
            </div>

            <?php if (empty($trashed)): ?>
                <div class="empty-state">Bin is empty.</div>
            <?php else: ?>
                <div class="file-grid">
                    <?php foreach ($trashed as $row): ?>
                        <?php
                        $safeFile = htmlspecialchars($row['filename'], ENT_QUOTES);
                        $menuId = "trash_" . md5($row['filename']);
                        ?>
                        <div class="file-card">
                            <button class="kebab-btn" type="button" onclick="toggleKebab(event, '<?= $menuId ?>')">⋮</button>
                            <div class="kebab-dropdown" id="<?= $menuId ?>">
                                <button class="kebab-item" onclick="restoreFile('<?= $safeFile ?>')">Restore</button>
                                <button class="kebab-item delete" onclick="deletePermanent('<?= $safeFile ?>')">Delete Permanently</button>
                            </div>
                            <div class="file-icon">
                                <svg class="ico-bin" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 3h6l1 2h5v2H3V5h5l1-2Zm1 6h2v9h-2V9Zm4 0h2v9h-2V9ZM7 9h2v9H7V9Z"/></svg>
                            </div>
                            <div class="file-name"><?= htmlspecialchars($row['filename']); ?></div>
                            <div class="file-meta">Deleted: <?= htmlspecialchars($row['deleted_at']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function toggleKebab(e, id) {
    e.stopPropagation();
    document.querySelectorAll('.kebab-dropdown').forEach(d => { if (d.id !== id) d.classList.remove('show'); });
    const el = document.getElementById(id);
    if (el) el.classList.toggle('show');
}

document.addEventListener('click', () => {
    document.querySelectorAll('.kebab-dropdown').forEach(d => d.classList.remove('show'));
});

const targetUserId = <?= (int)$targetUserId ?>;

async function restoreFile(file) {
    const formData = new FormData();
    formData.append('action', 'restore');
    formData.append('filename', file);
    formData.append('user_id', targetUserId);
    try {
        const res = await fetch('bin_action.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message || 'Restore failed');
    } catch (e) {
        console.error(e); alert('Request failed');
    }
}

async function deletePermanent(file) {
    const ok = await showConfirmModal({
        title: 'Delete permanently',
        message: `Delete "${file}" forever? This cannot be undone.`,
        confirmText: 'Delete',
        danger: true
    });
    if (!ok) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('filename', file);
    formData.append('user_id', targetUserId);
    try {
        const res = await fetch('bin_action.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message || 'Delete failed');
    } catch (e) {
        console.error(e); alert('Request failed');
    }
}

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
</script>

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
</body>
</html>
