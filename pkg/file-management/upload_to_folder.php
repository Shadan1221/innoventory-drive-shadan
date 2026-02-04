<?php
require_once "../../session.php";
require_once "../../config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'user';
$targetFolder = $_POST['target_folder'] ?? '';

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

if (empty($targetFolder)) {
    echo json_encode(['success' => false, 'message' => 'No target folder specified']);
    exit;
}

// Security: sanitize folder name
$targetFolder = basename($targetFolder);

// Upload directory
$baseDir = "../../uploads/user_" . $userId;
$targetPath = $baseDir . "/" . $targetFolder;

// Verify target folder exists
if (!is_dir($targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Target folder not found']);
    exit;
}

// Handle file upload
if (!isset($_FILES['upload']) || $_FILES['upload']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['upload'];
$filename = basename($file['name']);
$destination = $targetPath . "/" . $filename;

// Check if file already exists
if (file_exists($destination)) {
    // Add timestamp to filename to avoid conflicts
    $pathInfo = pathinfo($filename);
    $filename = $pathInfo['filename'] . '_' . time() . '.' . $pathInfo['extension'];
    $destination = $targetPath . "/" . $filename;
}

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'filename' => $filename
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
}
?>
