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
                            <div class="file-icon">🗑️</div>
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
    if (!confirm('Delete permanently: ' + file + ' ?')) return;
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
</script>
</body>
</html>
