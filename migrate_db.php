<?php
include 'Includes/dbcon.php';

$tables = [
    'tbldirector' => ['photo' => "VARCHAR(255) DEFAULT NULL", 'phoneNo' => "VARCHAR(20) DEFAULT NULL"],
    'tblhod'      => ['photo' => "VARCHAR(255) DEFAULT NULL", 'phoneNo' => "VARCHAR(20) DEFAULT NULL"],
    'tblteacher'  => ['photo' => "VARCHAR(255) DEFAULT NULL", 'phoneNo' => "VARCHAR(20) DEFAULT NULL", 'deptId' => "INT(10) DEFAULT NULL"],
    'tblstudent'  => [
        'photo' => "VARCHAR(255) DEFAULT NULL",
        'phoneNo' => "VARCHAR(20) DEFAULT NULL",
        'gender' => "ENUM('Male','Female','Other') NOT NULL DEFAULT 'Male'",
        'deptId' => "INT(10) DEFAULT NULL"
    ]
];

foreach ($tables as $table => $columns) {
    foreach ($columns as $column => $type) {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check->num_rows == 0) {
            $conn->query("ALTER TABLE `$table` ADD `$column` $type");
            echo "Added $column to $table<br>";
        } else {
            echo "$column already exists in $table<br>";
        }
    }
}
echo "Migration complete.";
?>
