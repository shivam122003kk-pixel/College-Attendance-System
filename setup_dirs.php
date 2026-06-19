<?php
// Create upload directories
$dirs = ['uploads/directors', 'uploads/teachers', 'uploads/students'];
foreach ($dirs as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0755, true);
        echo "Created $d/<br>";
    } else {
        echo "$d/ already exists<br>";
    }
}
echo "Done.";
?>
