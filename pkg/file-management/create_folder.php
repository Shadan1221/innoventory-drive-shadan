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
$folderName = trim($_POST['folder_name'] ?? '');

if (empty($folderName)) {
    echo json_encode(['success' => false, 'message' => 'Folder name is required']);
    exit;
}

// Sanitize folder name - remove special characters but keep spaces and hyphens
$sanitizedName = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $folderName);
$sanitizedName = trim($sanitizedName);

if (empty($sanitizedName)) {
    echo json_encode(['success' => false, 'message' => 'Invalid folder name']);
    exit;
}

// Determine whose folder to create
$viewUserId = $userId;
if ($role === 'admin' && isset($_POST['view_user_id'])) {
    $viewUserId = (int) $_POST['view_user_id'];
}

// Set upload directory
$baseDir = "../../uploads/user_" . $viewUserId;

// Create base directory if it doesn't exist
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0775, true);
}

$folderPath = $baseDir . "/" . $sanitizedName;

// Check if folder already exists
if (is_dir($folderPath)) {
    echo json_encode(['success' => false, 'message' => 'Folder already exists']);
    exit;
}

// Create the folder
if (mkdir($folderPath, 0775, true)) {
    echo json_encode(['success' => true, 'message' => 'Folder created successfully', 'folder_name' => $sanitizedName]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create folder']);
}
?>
