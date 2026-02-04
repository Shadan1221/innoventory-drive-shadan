<?php
require_once "../../session.php";
require_once "../../config.php";

// Security check
if (!isset($_SESSION['loggedin'])) {
    header("Location: ../../index.php");
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$role = $_SESSION['role'] ?? 'user';
$viewUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $userId;

// Security check for non-admin users
if ($role !== 'admin' && $viewUserId !== $userId) {
    die("Unauthorized");
}

$folderName = $_GET['folder'] ?? '';
if (empty($folderName)) {
    die("Folder name required");
}

$baseDir = "../../uploads/user_" . $viewUserId;
$folderPath = $baseDir . "/" . basename($folderName);

// Verify folder exists
if (!is_dir($folderPath)) {
    die("Folder not found");
}

// Check if ZipArchive is available
if (!class_exists('ZipArchive')) {
    die("Error: ZIP extension is not enabled. Please enable php_zip extension in php.ini and restart Apache.");
}

// Create zip file
$zipFileName = $folderName . '.zip';
$zipFilePath = sys_get_temp_dir() . '/' . uniqid() . '.zip';

$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Failed to create zip file");
}

// Add files to zip recursively
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($folderPath) + 1);
        $relativePath = str_replace('\\', '/', $relativePath);
        $zip->addFile($filePath, $relativePath);
    }
}

$numFiles = $zip->numFiles;
$zip->close();

if ($numFiles === 0) {
    unlink($zipFilePath);
    die("Folder is empty");
}

// Send zip file to browser
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
header('Content-Length: ' . filesize($zipFilePath));
readfile($zipFilePath);

// Delete temporary zip file
unlink($zipFilePath);
exit;
?>
