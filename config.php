<?php
$DBSERVER   = "localhost";
$DBUSERNAME = "root";
$DBPASSWORD = "";
$DBNAME     = "innoventory_db"; // Make sure this DB exists

$db = mysqli_connect($DBSERVER, $DBUSERNAME, $DBPASSWORD, $DBNAME);

if (!$db) {
    die("Connection failed: " . mysqli_connect_error());
}
?>