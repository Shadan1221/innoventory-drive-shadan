<?php
require_once "../../session.php";
require_once "../../config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$starredBy = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

$action   = $_POST['action'] ?? '';
$filename = basename($_POST['filename'] ?? '');

if ($filename === '' || ($action !== 'star' && $action !== 'unstar')) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

/*
 owner_id:
 - user: always their own files
 - admin: can pass owner_id in POST (from Users Drive)
*/
$ownerId = $starredBy;

if ($role === 'admin' && isset($_POST['owner_id'])) {
    $ownerId = (int) $_POST['owner_id'];
}

if ($ownerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid owner id']);
    exit;
}

/* Ensure table exists (NEW SCHEMA) */
$db->query("
CREATE TABLE IF NOT EXISTS starred_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    starred_by INT NOT NULL,
    owner_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_star (starred_by, owner_id, filename)
)
");

if ($action === 'star') {
    $stmt = $db->prepare("INSERT IGNORE INTO starred_files (starred_by, owner_id, filename) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $starredBy, $ownerId, $filename);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $ok]);
    exit;
}

if ($action === 'unstar') {
    $stmt = $db->prepare("DELETE FROM starred_files WHERE starred_by=? AND owner_id=? AND filename=?");
    $stmt->bind_param("iis", $starredBy, $ownerId, $filename);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $ok]);
    exit;
}
