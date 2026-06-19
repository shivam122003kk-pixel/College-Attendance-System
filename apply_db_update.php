<?php
include 'Includes/dbcon.php';

$q1 = "CREATE TABLE IF NOT EXISTS tblteacher_attendance (
    Id INT(10) NOT NULL AUTO_INCREMENT,
    teacherId INT(10) NOT NULL,
    deptId INT(10) NOT NULL,
    status INT(10) NOT NULL,
    dateTaken DATE NOT NULL,
    takenByHodId INT(10) NOT NULL,
    dateTimeTaken TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (Id),
    UNIQUE KEY (teacherId, dateTaken)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($q1) === TRUE) {
    echo "Table tblteacher_attendance created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$studentColumns = [
    'gender' => "ENUM('Male','Female','Other') NOT NULL DEFAULT 'Male'",
    'photo' => "VARCHAR(255) DEFAULT NULL",
    'phoneNo' => "VARCHAR(20) DEFAULT NULL",
    'deptId' => "INT(10) DEFAULT NULL"
];

foreach ($studentColumns as $column => $type) {
    $check = $conn->query("SHOW COLUMNS FROM tblstudent LIKE '$column'");
    if ($check && $check->num_rows == 0) {
        if ($conn->query("ALTER TABLE tblstudent ADD COLUMN `$column` $type") === TRUE) {
            echo "Column $column added to tblstudent successfully\n";
        } else {
            echo "Error adding $column column: " . $conn->error . "\n";
        }
    }
}
?>
