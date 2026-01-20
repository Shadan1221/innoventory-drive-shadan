<?php
require_once "../../session.php";
require_once "../../config.php";

/* SECURITY */
if (!isset($_SESSION['loggedin'])) {
    header("Location: ../../index.php");
    exit;
}

$starredBy = (int) ($_SESSION['user_id'] ?? $_SESSION['id']);
$role = $_SESSION['role'] ?? 'user';

/* ================= ADMIN USER LIST ================= */
$usersList = [];
$viewUserId = 0; // 0 = show admin's own starred files

if ($role === 'admin') {
    // Get all approved users
    $res = $db->query("
        SELECT id, name 
        FROM users 
        WHERE role='user' AND status='approved'
        ORDER BY name
    ");
    while ($row = $res->fetch_assoc()) {
        $usersList[] = $row;
    }
    
    // Determine which user's starred files to view
    if (isset($_GET['user_id'])) {
        $viewUserId = (int) $_GET['user_id'];
    }
    // If viewUserId is 0, admin views their own starred files (default)
}

// Determine who we're viewing starred files for
$targetUserId = ($role === 'admin' && $viewUserId > 0) ? $viewUserId : $starredBy;

/* Ensure table exists (NEW SCHEMA) */
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

/*
We show:
- All files starred by the current logged in person
- Even if they starred another user's file (admin use case)
*/
$stmt = $db->prepare("
    SELECT owner_id, filename, created_at
    FROM starred_files
    WHERE starred_by = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $targetUserId);
$stmt->execute();
$result = $stmt->get_result();

$starredFiles = [];
while ($row = $result->fetch_assoc()) {
    $ownerId = (int)$row['owner_id'];
    $file = $row['filename'];

    $path = "../../uploads/user_" . $ownerId . "/" . $file;
    if (file_exists($path)) {
        $starredFiles[] = [
            'owner_id' => $ownerId,
            'filename' => $file
        ];
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Starred Files - Innoventory</title>
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
                <h1>Starred</h1>
                <span>
                    <?php if ($role === 'admin' && $viewUserId > 0): ?>
                        Viewing starred files of User ID: <?= $viewUserId ?>
                    <?php else: ?>
                        Your important files
                    <?php endif; ?>
                </span>
            </div>

            <div class="drive-layout">

                <?php if ($role === 'admin'): ?>
                    <!-- USERS LIST -->
                    <aside class="drive-users">
                        <h3>Users</h3>

                        <a href="starred.php"
                           class="user-link <?= $viewUserId === 0 ? 'active' : '' ?>">
                            📁 My Starred Files
                        </a>

                        <?php foreach ($usersList as $u): ?>
                            <a href="starred.php?user_id=<?= (int)$u['id'] ?>"
                               class="user-link <?= $viewUserId === (int)$u['id'] ? 'active' : '' ?>">
                                👤 <?= htmlspecialchars($u['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </aside>
                <?php endif; ?>

                <!-- FILES -->
                <section class="drive-files">

            <?php if (empty($starredFiles)): ?>
                <div class="empty-state">
                    No starred files found.
                    <br><br>
                    <small>Go to <a href="my_drive.php">My Drive</a> to star some files!</small>
                </div>
            <?php else: ?>
                <div class="file-grid">
                    <?php foreach ($starredFiles as $item): ?>
                        <?php
                        $ownerId = (int)$item['owner_id'];
                        $file = $item['filename'];
                        ?>
                        <div class="file-card">

                            <div class="file-icon">⭐</div>

                            <div class="file-name">
                                <?= htmlspecialchars($file) ?>
                                <br>
                                <small style="color:var(--muted);">Owner ID: <?= $ownerId ?></small>
                            </div>

                            <a class="file-download"
                               href="../../pkg/file-management/download.php?user_id=<?= $ownerId ?>&file=<?= urlencode($file) ?>">
                                Download
                            </a>

                            <div style="margin-top:10px;">
                                <button class="btn-ghost"
                                  onclick="unstarFile('<?= htmlspecialchars($file, ENT_QUOTES) ?>', <?= $ownerId ?>)">
                                  Remove Star
                                </button>
                            </div>

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
function unstarFile(filename, ownerId) {
    const formData = new FormData();
    formData.append('action', 'unstar');
    formData.append('filename', filename);
    formData.append('owner_id', ownerId);

    fetch('star_action.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) location.reload();
            else alert("Error: " + (data.message || "Failed"));
        });
}
</script>

</body>
</html>
