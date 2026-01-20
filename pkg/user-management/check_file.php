<?php
$folderPath = "innoventory_0357921846";
$filePath = $folderPath . "/welcome.txt";

if (file_exists($filePath)) {
    echo "File exists! <br>";
    $content = file_get_contents($filePath);
    echo "File content: <br><pre>$content</pre>";
} else {
    echo "File does not exist!";
}
?>
