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
    SELECT id, name
    FROM users
    WHERE role='user' AND status='approved'
    ORDER BY name
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

            <div class="drive-layout">

                <!-- LEFT USERS -->
                <aside class="drive-users">
                    <h3>Users</h3>

                    <?php if (empty($usersList)): ?>
                        <div class="empty-state" style="padding: 20px 0;">No approved users.</div>
                    <?php else: ?>
                        <?php foreach ($usersList as $u): ?>
                            <?php $active = ($viewUserId === (int)$u['id']); ?>
                            <a class="user-link <?= $active ? 'active' : '' ?>"
                               href="users_drive.php?user_id=<?= (int)$u['id'] ?>">
                                <?= htmlspecialchars($u['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
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

                                            <button class="kebab-item delete"
                                                    onclick="deleteFile('<?= $safeFile ?>', <?= $viewUserId ?>)">
                                                🗑 Delete
                                            </button>
                                        </div>

                                        <div class="file-icon"><?= $isStarred ? "⭐" : "📄" ?></div>
                                        <div class="file-name"><?= htmlspecialchars($file) ?></div>

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

async function deleteFile(file, ownerId) {
    if (!confirm("Delete file: " + file + " ?")) return;

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
</script>

</body>
</html>
