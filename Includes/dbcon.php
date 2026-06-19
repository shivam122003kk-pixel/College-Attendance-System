<?php
// College Attendance Management System
// Single database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbName = "college_attendance";

$conn = new mysqli($host, $user, $pass, $dbName);

if ($conn->connect_error) {
    die(json_encode(['error' => "Connection failed: " . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");
?>
