<?php
require_once "../../session.php";

// security
if (!isset($_SESSION["loggedin"])) {
    header("Location: ../../index.php");
    exit;
}

if (!isset($_FILES["upload"])) {
    header("Location: ../user-management/user_dashboard.php?upload=fail");
    exit;
}

$uid = $_SESSION["user_id"] ?? $_SESSION['id'];
$baseDir = "../../uploads";
$userDir = $baseDir . "/user_" . $uid;

// create folders if missing
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}
if (!is_dir($userDir)) {
    mkdir($userDir, 0777, true);
}

$fileName = basename($_FILES["upload"]["name"]);
$targetPath = $userDir . "/" . $fileName;

// move file
if (move_uploaded_file($_FILES["upload"]["tmp_name"], $targetPath)) {
    header("Location: ../user-management/user_dashboard.php?upload=success");
} else {
    header("Location: ../user-management/user_dashboard.php?upload=fail");
}
exit;
