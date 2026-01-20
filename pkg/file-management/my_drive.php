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

/* ================= DETERMINE WHOSE FILES ================= */
$viewUserId = $loggedInUserId;
if ($role === 'admin' && isset($_GET['user_id'])) {
    $viewUserId = (int) $_GET['user_id'];
}

/* ================= FILE PATH ================= */
$uploadDir = "../../uploads/user_" . $viewUserId;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

/* ================= FILE LIST ================= */
$files = array_diff(scandir($uploadDir), ['.', '..']);

/* ================= STARRED FILES TABLE ================= */
$db->query("
    CREATE TABLE IF NOT EXISTS starred_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_star (user_id, filename)
    )
");

/* ================= GET STARRED FILES FOR VIEW USER ================= */
$starredFiles = [];
$stmt = $db->prepare("SELECT filename FROM starred_files WHERE starred_by = ?");
$stmt->bind_param("i", $viewUserId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $starredFiles[] = $row['filename'];
}
$stmt->close();

/* ================= ADMIN USER LIST ================= */
$usersList = [];
if ($role === 'admin') {
    $res = $db->query("
        SELECT id, name 
        FROM users 
        WHERE role='user' AND status='approved'
        ORDER BY name
    ");
    while ($row = $res->fetch_assoc()) {
        $usersList[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Drive - Innoventory</title>
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
                <h1>My Drive</h1>
                <span>
                    <?php if ($role === 'admin' && $viewUserId !== $loggedInUserId): ?>
                        Viewing files of User ID: <?= $viewUserId ?>
                    <?php else: ?>
                        Your uploaded files
                    <?php endif; ?>
                </span>
            </div>

            <div class="drive-layout">

                <?php if ($role === 'admin'): ?>
                    <!-- USERS LIST -->
                    <aside class="drive-users">
                        <h3>Users</h3>

                        <a href="my_drive.php"
                           class="user-link <?= $viewUserId === $loggedInUserId ? 'active' : '' ?>">
                            📁 My Files
                        </a>

                        <?php foreach ($usersList as $u): ?>
                            <a href="my_drive.php?user_id=<?= (int)$u['id'] ?>"
                               class="user-link <?= $viewUserId === (int)$u['id'] ? 'active' : '' ?>">
                                👤 <?= htmlspecialchars($u['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </aside>
                <?php endif; ?>

                <!-- FILES -->
                <section class="drive-files">

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

                                    <!-- kebab -->
                                    <button class="kebab-btn" type="button"
                                            onclick="toggleKebab(event, '<?= $menuId ?>')">
                                        ⋮
                                    </button>

                                    <div class="kebab-dropdown" id="<?= $menuId ?>">
                                        <button class="kebab-item"
                                                onclick="toggleStar('<?= $safeFile ?>', <?= $isStarred ? 'true' : 'false' ?>)">
                                            <?= $isStarred ? '⭐ Remove Star' : '⭐ Star File' ?>
                                        </button>

                                        <button class="kebab-item delete"
                                                onclick="deleteFile('<?= $safeFile ?>', <?= (int)$viewUserId ?>)">
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

                </section>

            </div>
        </div>
    </main>

</div>

<script>
function toggleKebab(e, id) {
    e.stopPropagation();

    // close others
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

async function deleteFile(file, userId) {
    if (!confirm("Delete file: " + file + " ?")) return;

    const formData = new FormData();
    formData.append('filename', file);
    formData.append('user_id', userId);

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
