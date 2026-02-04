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
$viewUserId = (int) ($_POST['user_id'] ?? $userId);

if (empty($folderName)) {
    echo json_encode(['success' => false, 'message' => 'Folder name is required']);
    exit;
}

// Security: user can only delete their own folder, admin can delete any user's folder
if ($role !== 'admin' && $viewUserId !== $userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Set upload directory
$baseDir = "../../uploads/user_" . $viewUserId;
$folderPath = $baseDir . "/" . basename($folderName);

// Verify folder exists and is actually a directory
if (!is_dir($folderPath)) {
    echo json_encode(['success' => false, 'message' => 'Folder not found']);
    exit;
}

// Recursively delete folder
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $filePath = $dir . '/' . $file;
        
        if (is_dir($filePath)) {
            deleteDirectory($filePath);
        } else {
            unlink($filePath);
        }
    }
    
    return rmdir($dir);
}

if (deleteDirectory($folderPath)) {
    echo json_encode(['success' => true, 'message' => 'Folder deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete folder']);
}
?>
