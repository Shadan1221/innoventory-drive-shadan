<?php
// Capture all output and errors
ob_start();

try {
    require_once "../../session.php";
    require_once "../../config.php";
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Init error: ' . $e->getMessage()]);
    exit;
}

$output = ob_get_clean();

header('Content-Type: application/json');

// Check if DB connection exists
if (!isset($db) || !$db) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

/* 1) Security */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUserId = (int) ($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$role = $_SESSION['role'] ?? '';

/* 2) Data */
$filename = $_POST['filename'] ?? '';
if ($filename === '') {
    echo json_encode(['success' => false, 'message' => 'Filename missing']);
    exit;
}

// Sanitize path to prevent directory traversal attacks
$filename = str_replace(['..', '\\'], ['', '/'], $filename);
$filename = ltrim($filename, '/');

/* 3) Determine target */
$targetUserId = $currentUserId;

if ($role === 'admin' && isset($_POST['user_id'])) {
    $targetUserId = (int) $_POST['user_id'];
}

if ($targetUserId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid target user']);
    exit;
}

/* 4) Paths */
$sourcePath = "../../uploads/user_" . $targetUserId . "/" . $filename;
$trashDir   = "../../uploads/bin/user_" . $targetUserId;

// If file is in a folder, preserve the folder structure in trash
$trashPath  = $trashDir . "/" . $filename;
$trashFileDir = dirname($trashPath);

if (!file_exists($sourcePath)) {
    echo json_encode(['success' => false, 'message' => 'File not found: ' . $filename]);
    exit;
}

// Create trash directory structure if needed
if (!is_dir($trashFileDir) && !mkdir($trashFileDir, 0775, true)) {
    echo json_encode(['success' => false, 'message' => 'Cannot create trash directory']);
    exit;
}

// If trash already has a file with same name, remove it so we can replace
if (file_exists($trashPath)) {
    @unlink($trashPath);
}

if (!rename($sourcePath, $trashPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to move file to Bin']);
    exit;
}

/* 5) Ensure tables */
$ok1 = $db->query("
    CREATE TABLE IF NOT EXISTS starred_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        starred_by INT NOT NULL,
        owner_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_star (starred_by, owner_id, filename)
    )
");

$ok2 = $db->query("
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

if (!$ok1 || !$ok2) {
    echo json_encode(['success' => false, 'message' => 'DB init failed: ' . $db->error]);
    exit;
}

/* 6) Remove all star entries for this file */
$stmt = $db->prepare("DELETE FROM starred_files WHERE owner_id = ? AND filename = ?");
$stmt->bind_param("is", $targetUserId, $filename);
$stmt->execute();
$stmt->close();

/* 7) Upsert trash record */
$stmt = $db->prepare("INSERT INTO trashed_files (user_id, filename, original_path, trashed_path, deleted_by, deleted_at)
VALUES (?, ?, ?, ?, ?, NOW())
ON DUPLICATE KEY UPDATE
    original_path = VALUES(original_path),
    trashed_path  = VALUES(trashed_path),
    deleted_by    = VALUES(deleted_by),
    deleted_at    = NOW()");
$stmt->bind_param("isssi", $targetUserId, $filename, $sourcePath, $trashPath, $currentUserId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Failed to record trash entry: ' . $db->error]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Moved to Bin']);
exit;
?>
