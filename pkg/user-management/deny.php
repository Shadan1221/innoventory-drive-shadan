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
        header("Location: admin_dashboard.php?msg=User denied successfully");
    } else {
        header("Location: admin_dashboard.php?msg=Error denying user");
    }
    
    $query->close();
} else {
    header("Location: admin_dashboard.php");
}

exit;
?>

