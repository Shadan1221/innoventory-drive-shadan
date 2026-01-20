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
$files = array_diff(scandir($uploadDir), ['.', '..']);

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
        <div class="dashboard-card">

            <div class="dashboard-header">
                <h1>My Drive</h1>
                <span>Your uploaded files (Admin)</span>
            </div>

            <?php if (empty($files)): ?>
                <div class="empty-state">No files uploaded yet.</div>
            <?php else: ?>
                <div class="file-grid">
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

                                <button class="kebab-item delete"
                                        onclick="deleteFile('<?= $safeFile ?>')">
                                    🗑 Delete
                                </button>
                            </div>

                            <div class="file-icon"><?= $isStarred ? "⭐" : "📄" ?></div>

                            <div class="file-name"><?= htmlspecialchars($file) ?></div>

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

<script>
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
</script>

</body>
</html>
