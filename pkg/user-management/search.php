<?php
require_once "../../session.php";
require_once "../../config.php";

// SECURITY: only logged in users
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../../index.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

$q = trim($_GET["q"] ?? "");
$results = [];

if ($q !== "") {
    // Get the upload directory for the logged-in user
    $uploadDir = "../../uploads/user_" . $user_id;
    
    if (is_dir($uploadDir)) {
        // Get all files from the user's directory
        $allFiles = array_diff(scandir($uploadDir), ['.', '..']);
        
        // Filter files that match the search query
        foreach ($allFiles as $filename) {
            if (stripos($filename, $q) !== false) {
                $filePath = $uploadDir . "/" . $filename;
                $results[] = [
                    'filename' => $filename,
                    'size' => filesize($filePath),
                    'modified' => filemtime($filePath),
                    'path' => $filePath
                ];
            }
        }
    }
}

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Search Files - Innoventory</title>
    <link rel="stylesheet" href="../../css/main.css" />
</head>

<body>

<?php include "../../common/header.php"; ?>

<div class="app-grid">

    <!-- SIDEBAR -->
    <?php include "../../common/menu.php"; ?>

    <!-- MAIN -->
    <main>
        <div class="dashboard-card">

            <div class="dashboard-header">
                <h1>Search Files</h1>
                <span>Results for: <b><?= htmlspecialchars($q) ?></b></span>
            </div>

            <!-- Search box -->
            <form method="GET" action="search.php" style="margin-bottom: 20px;">
                <div class="global-search">
                    <input type="text" name="q"
                           placeholder="Search files in My Drive..."
                           value="<?= htmlspecialchars($q) ?>" />
                    <button type="submit">Search</button>
                </div>
            </form>

            <?php if ($q === ""): ?>
                <div class="empty-state">
                    Type something in the search box to find files.
                </div>

            <?php elseif (empty($results)): ?>
                <div class="empty-state">
                    No files found matching "<?= htmlspecialchars($q) ?>".
                </div>

            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                        <tr>
                            <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border);">Filename</th>
                            <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border);">Size</th>
                            <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border);">Last Modified</th>
                            <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border);">Action</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($results as $file): ?>
                            <tr>
                                <td style="padding:12px; border-bottom:1px solid var(--border);">
                                    <?= htmlspecialchars($file["filename"]) ?>
                                </td>
                                <td style="padding:12px; border-bottom:1px solid var(--border);">
                                    <?= formatFileSize($file["size"]) ?>
                                </td>
                                <td style="padding:12px; border-bottom:1px solid var(--border);">
                                    <?= date('Y-m-d H:i:s', $file["modified"]) ?>
                                </td>
                                <td style="padding:12px; border-bottom:1px solid var(--border);">
                                    <a href="../file-management/download.php?file=<?= urlencode($file['filename']) ?>"
                                       style="color:var(--accent); text-decoration:none; font-weight:600; margin-right: 15px;">
                                        Download
                                    </a>
                                    <a href="../file-management/my_drive.php"
                                       style="color:var(--accent); text-decoration:none; font-weight:600;">
                                        View in Drive
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </main>

</div>

</body>
</html>
