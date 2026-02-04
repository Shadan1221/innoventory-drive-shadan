<?php
require_once "config.php";

$tables = [];
$res = $db->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    echo "TABLE: $table\n";
    $res = $db->query("DESCRIBE $table");
    while ($row = $res->fetch_assoc()) {
        echo "  " . $row['Field'] . " " . $row['Type'] . "\n";
    }
    echo "\n";
}
?>
