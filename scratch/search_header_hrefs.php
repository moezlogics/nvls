<?php
$lines = file(__DIR__ . '/../includes/header.php');
foreach ($lines as $i => $line) {
    if (strpos($line, 'href=') !== false && (strpos($line, 'a ') !== false || strpos($line, 'nav') !== false || strpos($line, 'base_path') !== false)) {
        echo "Line " . ($i + 1) . ": " . trim($line) . PHP_EOL;
    }
}
