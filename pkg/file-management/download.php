<?php
require_once "../../session.php";

if (!isset($_SESSION['loggedin'])) {
    exit("Unauthorized");
}

$userId = (int) $_GET['user_id'];
$file   = $_GET['file'] ?? '';

// Remove any dangerous path traversal attempts
$file = str_replace(['../', '..\\'], '', $file);

/* Security check */
if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] !== $userId) {
    http_response_code(403);
    exit("Access denied");
}

$path = "../../uploads/user_$userId/" . $file;

if (!file_exists($path)) {
    exit("File not found: " . htmlspecialchars($file));
}

if (is_dir($path)) {
    exit("Cannot download directories directly");
}

// Get just the filename for the download
$downloadName = basename($file);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
