<?php
$directories = [
    'c:/xampp/htdocs/sas-main',
    'c:/xampp/htdocs/sas-main/Admin',
    'c:/xampp/htdocs/sas-main/Admin/Includes',
    'c:/xampp/htdocs/sas-main/HOD',
    'c:/xampp/htdocs/sas-main/HOD/Includes',
    'c:/xampp/htdocs/sas-main/ClassTeacher',
    'c:/xampp/htdocs/sas-main/ClassTeacher/Includes',
    'c:/xampp/htdocs/sas-main/Student',
    'c:/xampp/htdocs/sas-main/Student/Includes',
];

foreach ($directories as $dir) {
    $files = glob($dir . '/*.php');
    if (is_array($files)) {
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $original = $content;

            // Replace strings
            $content = str_ireplace('Punjab Institute of Management & Technology', 'Punjab Institute of Management & Technology', $content);
            $content = str_replace('PIMT', 'PIMT', $content);
            
            // Sidebar logo replacements
            $content = preg_replace('/<div class="sidebar-brand-icon"[^>]*>\s*<i class="[^"]*"(?:\s*style="[^"]*")?><\/i>\s*<\/div>/is', '<img src="../img/pimt-logo.png" style="width:36px;height:36px;border-radius:8px;object-fit:cover;">', $content);

            if ($content !== $original) {
                file_put_contents($file, $content);
                echo "Updated: $file\n";
            }
        }
    }
}
?>
