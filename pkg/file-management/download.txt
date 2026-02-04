<?php
require_once "../../session.php";

if (!isset($_SESSION['loggedin'])) {
    exit("Unauthorized");
}

$userId = (int) $_GET['user_id'];
$file   = basename($_GET['file']);

/* Security check */
if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] !== $userId) {
    http_response_code(403);
    exit("Access denied");
}

$path = "../../uploads/user_$userId/$file";

if (!file_exists($path)) {
    exit("File not found");
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file . '"');
readfile($path);
exit;
