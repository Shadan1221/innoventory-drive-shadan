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
    
    // Update user status to approved
    $query = $db->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
    $query->bind_param("i", $id);
    
    if ($query->execute()) {
        header("Location: admin_dashboard.php?msg=User approved successfully");
    } else {
        header("Location: admin_dashboard.php?msg=Error approving user");
    }
    
    $query->close();
} else {
    header("Location: admin_dashboard.php");
}

exit;
?>

