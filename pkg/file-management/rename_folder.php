<?php
require_once "../../session.php";
require_once "../../config.php";

header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'user';

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

// Get folder name from POST
$oldName = trim($_POST['old_name'] ?? '');
$newName = trim($_POST['new_name'] ?? '');
$viewUserId = (int) ($_POST['user_id'] ?? $userId);

if (empty($oldName) || empty($newName)) {
    echo json_encode(['success' => false, 'message' => 'Old and new folder names are required']);
    exit;
}

// Validate new name (prevent path traversal)
if (strpos($newName, '/') !== false || strpos($newName, '\\') !== false || $newName === '.' || $newName === '..') {
    echo json_encode(['success' => false, 'message' => 'Invalid folder name']);
    exit;
}

// Security: user can only rename their own folder, admin can rename any user's folder
if ($role !== 'admin' && $viewUserId !== $userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Set upload directory
$baseDir = "../../uploads/user_" . $viewUserId;
$oldPath = $baseDir . "/" . basename($oldName);
$newPath = $baseDir . "/" . basename($newName);

// Verify old folder exists
if (!is_dir($oldPath)) {
    echo json_encode(['success' => false, 'message' => 'Folder not found']);
    exit;
}

// Check if new folder name already exists
if (file_exists($newPath)) {
    echo json_encode(['success' => false, 'message' => 'A folder with this name already exists']);
    exit;
}

// Rename folder
if (rename($oldPath, $newPath)) {
    echo json_encode(['success' => true, 'message' => 'Folder renamed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to rename folder']);
}
?>
