<?php
require_once "../../session.php";
require_once "../../config.php";

// ONLY ADMIN CAN ACCESS THIS PAGE
if ($_SESSION["role"] !== "admin") {
    header("Location: ../../index.php");
    exit;
}

if (isset($_GET["id"])) {
    $id = intval($_GET["id"]);
    
    // Update user status to denied
    $query = $db->prepare("UPDATE users SET status = 'denied' WHERE id = ?");
    $query->bind_param("i", $id);
    
    if ($query->execute()) {
        $redirect = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'users.php') !== false ? 'users.php' : 'admin_dashboard.php';
        header("Location: {$redirect}?msg=User denied successfully");
    } else {
        $redirect = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'users.php') !== false ? 'users.php' : 'admin_dashboard.php';
        header("Location: {$redirect}?msg=Error denying user");
    }
    
    $query->close();
} else {
    header("Location: admin_dashboard.php");
}

exit;
?>

