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
$filename = $_GET['file'] ?? '';
$viewUserId = (int) ($_GET['user_id'] ?? $userId);

// Security: user can only preview their own files, admin can preview any user's files
if ($role !== 'admin' && $viewUserId !== $userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (empty($filename)) {
    echo json_encode(['success' => false, 'message' => 'No file specified']);
    exit;
}

// Sanitize filename to prevent directory traversal
$filename = str_replace(['..', '\\'], ['', '/'], $filename);

$uploadDir = "../../uploads/user_" . $viewUserId;
$filePath = $uploadDir . "/" . $filename;

if (!file_exists($filePath) || !is_file($filePath)) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

// Get file info
$fileSize = filesize($filePath);
$fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeType = mime_content_type($filePath);

// Determine if file is previewable
$previewableImages = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
$previewableText = ['txt', 'php', 'js', 'css', 'html', 'json', 'xml', 'md', 'csv'];
$previewableVideo = ['mp4', 'webm', 'ogg'];
$previewableAudio = ['mp3', 'wav', 'ogg', 'aac'];
$previewablePdf = ['pdf'];

$previewType = 'download'; // default
$canPreview = false;

if (in_array($fileExt, $previewableImages)) {
    $previewType = 'image';
    $canPreview = true;
} elseif (in_array($fileExt, $previewableText)) {
    $previewType = 'text';
    $canPreview = true;
} elseif (in_array($fileExt, $previewableVideo)) {
    $previewType = 'video';
    $canPreview = true;
} elseif (in_array($fileExt, $previewableAudio)) {
    $previewType = 'audio';
    $canPreview = true;
} elseif (in_array($fileExt, $previewablePdf)) {
    $previewType = 'pdf';
    $canPreview = true;
}

// Get file content for text preview
$content = '';
if ($previewType === 'text' && $fileSize < 1048576) { // Max 1MB for text preview
    $content = file_get_contents($filePath);
}

// Build preview URL - encode each path segment separately to preserve slashes
$pathParts = explode('/', $filename);
$encodedParts = array_map('rawurlencode', $pathParts);
$encodedPath = implode('/', $encodedParts);
$previewUrl = "../../uploads/user_" . $viewUserId . "/" . $encodedPath;

echo json_encode([
    'success' => true,
    'filename' => $filename,
    'size' => $fileSize,
    'sizeFormatted' => formatFileSize($fileSize),
    'type' => $previewType,
    'mimeType' => $mimeType,
    'extension' => $fileExt,
    'canPreview' => $canPreview,
    'previewUrl' => $previewUrl,
    'content' => $content
]);

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}
?>
