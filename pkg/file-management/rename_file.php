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

// Get file names from POST
$oldName = trim($_POST['old_name'] ?? '');
$newName = trim($_POST['new_name'] ?? '');
$viewUserId = (int) ($_POST['user_id'] ?? $userId);

if (empty($oldName) || empty($newName)) {
    echo json_encode(['success' => false, 'message' => 'Old and new file names are required']);
    exit;
}

// Sanitize old name to prevent directory traversal
$oldName = str_replace(['..', '\\'], ['', '/'], $oldName);

// Check if oldName contains a folder path
$folderPath = '';
if (strpos($oldName, '/') !== false) {
    $folderPath = dirname($oldName) . '/';
    $oldFileName = basename($oldName);
} else {
    $oldFileName = $oldName;
}

// Validate new name (prevent path traversal and slashes in the new name only)
if (strpos($newName, '/') !== false || strpos($newName, '\\') !== false || $newName === '.' || $newName === '..') {
    echo json_encode(['success' => false, 'message' => 'Invalid file name']);
    exit;
}

// Security: user can only rename their own files, admin can rename any user's files
if ($role !== 'admin' && $viewUserId !== $userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Set upload directory
$baseDir = "../../uploads/user_" . $viewUserId;
$oldPath = $baseDir . "/" . $oldName;
$newPath = $baseDir . "/" . $folderPath . $newName;

// Verify old file exists
if (!is_file($oldPath)) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

// Check if new file name already exists
if (file_exists($newPath)) {
    echo json_encode(['success' => false, 'message' => 'A file with this name already exists']);
    exit;
}

// Rename file
if (rename($oldPath, $newPath)) {
    echo json_encode(['success' => true, 'message' => 'File renamed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to rename file']);
}
?>
