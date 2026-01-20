<?php
require_once "../../session.php";
require_once "../../config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$role         = $_SESSION['role'] ?? 'user';
$currentUserId = (int) ($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$action       = $_POST['action'] ?? '';
$filename     = basename($_POST['filename'] ?? '');
$targetUserId = $currentUserId;

if ($role === 'admin' && isset($_POST['user_id'])) {
    $targetUserId = (int) $_POST['user_id'];
} else {
    $targetUserId = $currentUserId;
}

if ($targetUserId <= 0 || $filename === '' || ($action !== 'restore' && $action !== 'delete')) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Ensure trash table exists
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

// Fetch entry
$stmt = $db->prepare("SELECT id, original_path, trashed_path FROM trashed_files WHERE user_id = ? AND filename = ? LIMIT 1");
$stmt->bind_param("is", $targetUserId, $filename);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Item not found in Bin']);
    exit;
}

$originalPath = $row['original_path'];
$trashedPath  = $row['trashed_path'];

if ($action === 'restore') {
    $origDir = dirname($originalPath);
    if (!is_dir($origDir) && !mkdir($origDir, 0775, true)) {
        echo json_encode(['success' => false, 'message' => 'Cannot recreate original folder']);
        exit;
    }
    if (file_exists($originalPath)) {
        echo json_encode(['success' => false, 'message' => 'A file with this name already exists in the destination']);
        exit;
    }
    if (!file_exists($trashedPath)) {
        echo json_encode(['success' => false, 'message' => 'Trashed file missing on disk']);
        exit;
    }
    if (!rename($trashedPath, $originalPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to restore file']);
        exit;
    }

    $del = $db->prepare("DELETE FROM trashed_files WHERE user_id = ? AND filename = ?");
    $del->bind_param("is", $targetUserId, $filename);
    $del->execute();
    $del->close();

    echo json_encode(['success' => true, 'message' => 'File restored']);
    exit;
}

// Permanent delete
if (file_exists($trashedPath)) {
    @unlink($trashedPath);
}
$del = $db->prepare("DELETE FROM trashed_files WHERE user_id = ? AND filename = ?");
$del->bind_param("is", $targetUserId, $filename);
$del->execute();
$del->close();

echo json_encode(['success' => true, 'message' => 'File deleted permanently']);
exit;
?>
