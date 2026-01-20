<?php
session_start();

echo "<h1>Session Diagnostic Tool</h1>";

echo "<h3>1. Session Status</h3>";
if (session_status() == PHP_SESSION_NONE) {
    echo "Session NOT started.<br>";
} else {
    echo "Session Active.<br>";
}

echo "<h3>2. Session ID</h3>";
echo session_id() . "<br>";

echo "<h3>3. Current Session Variables</h3>";
if (!empty($_SESSION)) {
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
} else {
    echo "No session variables found (You are logged out).<br>";
}

echo "<hr>";
echo "<a href='index.php'>Go to Home</a>";
?>

