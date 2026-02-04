<?php
require_once "../../session.php";
require_once "../../config.php";

// Security check
if (!isset($_SESSION['loggedin'])) {
    header("Location: ../../index.php");
    exit;
}

// Check if files were uploaded
if (!isset($_FILES['folder']) || empty($_FILES['folder']['name'][0])) {
    $role = $_SESSION['role'] ?? 'user';
    $redirectPage = ($role === 'admin') ? 'admin_drive.php' : 'my_drive.php';
    header("Location: " . $redirectPage . "?upload=fail&error=nofiles");
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$role = $_SESSION['role'] ?? 'user';
$baseDir = "../../uploads";
$userDir = $baseDir . "/user_" . $userId;

// Create base directory if missing
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}
if (!is_dir($userDir)) {
    mkdir($userDir, 0777, true);
}

$uploadedCount = 0;
$failedCount = 0;
$folderName = '';

// Process folder upload
if (is_array($_FILES['folder']['name'])) {
    // Multiple files (folder upload)
    for ($i = 0; $i < count($_FILES['folder']['name']); $i++) {
        if ($_FILES['folder']['error'][$i] == UPLOAD_ERR_OK) {
            $fileName = basename($_FILES['folder']['name'][$i]);
            $tmpName = $_FILES['folder']['tmp_name'][$i];
            
            // Extract folder structure path
            $fullPath = $_FILES['folder']['full_path'][$i] ?? $_FILES['folder']['webkitRelativePath'][$i] ?? $_FILES['folder']['name'][$i];
            
            // Normalize path separators
            $fullPath = str_replace('\\', '/', $fullPath);
            
            // Get the root folder name
            $pathParts = explode('/', $fullPath);
            if (empty($folderName) && count($pathParts) > 0) {
                $folderName = $pathParts[0];
            }
            
            // Build target path maintaining folder structure
            $relativePath = $fullPath;
            $targetPath = $userDir . "/" . $relativePath;
            
            // Create necessary subdirectories
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            // Move file to target directory
            if (move_uploaded_file($tmpName, $targetPath)) {
                $uploadedCount++;
            } else {
                $failedCount++;
            }
        } else {
            $failedCount++;
        }
    }
}

// Determine redirect page based on role
$redirectPage = ($role === 'admin') ? 'admin_drive.php' : 'my_drive.php';

// Redirect with result
if ($uploadedCount > 0) {
    header("Location: " . $redirectPage . "?upload=success&files=$uploadedCount&folder=" . urlencode($folderName));
} else {
    header("Location: " . $redirectPage . "?upload=fail&failed=$failedCount");
}
exit;
?>
