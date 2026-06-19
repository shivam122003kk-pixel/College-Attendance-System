<?php
$logoSource = 'C:\Users\Asus\.gemini\antigravity\brain\28a21cef-e537-4f7a-8365-0e8b747b5726\pimt_logo_1777747527350.png';
$logoTarget = 'c:\xampp\htdocs\sas-main\img\pimt-logo.png';

if (file_exists($logoSource)) {
    copy($logoSource, $logoTarget);
    echo "Logo copied.\n";
} else {
    echo "Logo source not found.\n";
}

$directories = [
    'c:\xampp\htdocs\sas-main',
    'c:\xampp\htdocs\sas-main\Admin',
    'c:\xampp\htdocs\sas-main\Admin\Includes',
    'c:\xampp\htdocs\sas-main\HOD',
    'c:\xampp\htdocs\sas-main\HOD\Includes',
    'c:\xampp\htdocs\sas-main\ClassTeacher',
    'c:\xampp\htdocs\sas-main\ClassTeacher\Includes',
    'c:\xampp\htdocs\sas-main\Student',
    'c:\xampp\htdocs\sas-main\Student\Includes',
];

foreach ($directories as $dir) {
    $files = glob($dir . '\*.php');
    if ($files) {
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $original = $content;

            // Replace strings
            $content = str_ireplace('Punjab Institute of Management & Technology', 'Punjab Institute of Management & Technology', $content);
            $content = str_replace('PIMT', 'PIMT', $content);
            
            // Sidebar logo replacements
            $content = preg_replace('/<div class="sidebar-brand-icon"[^>]*>\s*<i class="[^"]*"(?:\s*style="[^"]*")?><\/i>\s*<\/div>/s', '<img src="../img/pimt-logo.png" style="width:36px;height:36px;border-radius:8px;object-fit:cover;">', $content);

            if ($content !== $original) {
                file_put_contents($file, $content);
                echo "Updated: $file\n";
            }
        }
    }
}

// Update index.php logo specifically
$indexFile = 'c:\xampp\htdocs\sas-main\index.php';
$content = file_get_contents($indexFile);
$content = str_replace('<div class="brand-logo"><i class="fas fa-university"></i></div>', '<div class="brand-logo" style="background:none;box-shadow:none;"><img src="img/pimt-logo.png" style="width:100%;height:100%;border-radius:20px;object-fit:cover;box-shadow:0 12px 40px rgba(79,99,210,.4);"></div>', $content);
file_put_contents($indexFile, $content);
echo "Updated index.php logo.\n";
?>
