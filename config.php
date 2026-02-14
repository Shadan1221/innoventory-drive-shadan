<?php
$DBSERVER   = "localhost";
$DBUSERNAME = "root";
$DBPASSWORD = "";
$DBNAME     = "innoventory_db"; // Make sure this DB exists

$db = mysqli_connect($DBSERVER, $DBUSERNAME, $DBPASSWORD, $DBNAME);

if (!$db) {
    die("Connection failed: " . mysqli_connect_error());
}

// Add status_notified column if it doesn't exist
$checkColumn = mysqli_query($db, "SHOW COLUMNS FROM users LIKE 'status_notified'");
if (mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($db, "ALTER TABLE users ADD COLUMN status_notified VARCHAR(20) DEFAULT NULL AFTER status");
}

// Add reason column if it doesn't exist
$checkReason = mysqli_query($db, "SHOW COLUMNS FROM users LIKE 'reason'");
if (mysqli_num_rows($checkReason) == 0) {
    mysqli_query($db, "ALTER TABLE users ADD COLUMN reason TEXT NULL");
}

// Ensure all users have a valid status (fix empty/NULL statuses)
mysqli_query($db, "UPDATE users SET status = 'pending' WHERE status IS NULL OR status = ''");

// Add default value and NOT NULL constraint to status column
$checkStatus = mysqli_query($db, "SHOW COLUMNS FROM users LIKE 'status'");
if ($checkStatus && $row = mysqli_fetch_assoc($checkStatus)) {
    // If status column doesn't have a default, add it
    if (strpos($row['Default'], 'pending') === false) {
        mysqli_query($db, "ALTER TABLE users MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }
}
?>