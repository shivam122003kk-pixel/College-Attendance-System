<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Director') {
    header("Location: ../index.php");
    exit;
}

include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
include '../Includes/db_helpers.php';

resequenceCollegeAttendanceIds($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IDs Resequenced - PIMT</title>
    <link href="../css/cams.css" rel="stylesheet">
</head>
<body>
<div id="page-content" class="fade-in">
    <div class="alert alert-success">Database IDs have been resequenced from 1.</div>
    <a class="btn btn-primary" href="index.php">Back to Dashboard</a>
</div>
</body>
</html>
