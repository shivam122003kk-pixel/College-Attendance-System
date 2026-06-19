<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
$teacherId = $_SESSION['userId'];

// Get teacher's courses
$myCourses = $conn->query("
    SELECT c.Id, c.courseName, c.courseCode, c.duration, c.description,
           COUNT(DISTINCT s.Id) as studentCount,
           COUNT(DISTINCT a.dateTaken) as attendanceDays
    FROM tblteacher_course tc
    JOIN tblcourse c ON tc.courseId = c.Id
    LEFT JOIN tblstudent s ON c.Id = s.courseId
    LEFT JOIN tblattendance a ON c.Id = a.courseId AND a.takenByTeacherId = $teacherId
    WHERE tc.teacherId = $teacherId
    GROUP BY c.Id
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>My Courses - PIMT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <script src="../js/pimt-alerts.js"></script>
    <script src="../js/pimt-actions.js"></script>
</head>
<body>
<div id="wrapper">
    <?php include 'Includes/sidebar.php'; ?>
    <div id="content-wrapper">
        <?php include 'Includes/topbar.php'; ?>
        <div id="page-content" class="fade-in">
            <div class="page-header">
                <h1 class="page-title">My <span>Courses</span></h1>
                <nav class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <i class="fas fa-chevron-right"></i><span>My Courses</span>
                </nav>
            </div>

            <?php if ($myCourses->num_rows > 0): ?>
            <div class="course-grid">
                <?php while ($c = $myCourses->fetch_assoc()): ?>
                <div class="course-card" style="border:1px solid var(--glass-border)">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
                        <div class="course-card-code"><?= $c['courseCode'] ?></div>
                        <span class="badge badge-info"><?= $c['duration'] ?></span>
                    </div>
                    <div class="course-card-name"><?= htmlspecialchars($c['courseName']) ?></div>
                    <div class="course-card-desc"><?= htmlspecialchars($c['description']) ?></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0;padding:12px;background:rgba(255,255,255,0.03);border-radius:8px">
                        <div style="text-align:center">
                            <div style="font-family:'Outfit',sans-serif;font-size:22px;font-weight:800;color:var(--indigo-bright)"><?= $c['studentCount'] ?></div>
                            <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px">Students</div>
                        </div>
                        <div style="text-align:center">
                            <div style="font-family:'Outfit',sans-serif;font-size:22px;font-weight:800;color:var(--teal)"><?= $c['attendanceDays'] ?></div>
                            <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px">Classes Taken</div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a href="takeAttendance.php?courseId=<?= $c['Id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-calendar-check"></i> Take Attendance</a>
                        <a href="addStudents.php?courseId=<?= $c['Id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-user-plus"></i> Add Students</a>
                        <a href="viewAttendance.php?courseId=<?= $c['Id'] ?>" class="btn btn-sm" style="background:var(--indigo-light);border:1px solid var(--indigo);color:var(--indigo-bright)"><i class="fas fa-chart-bar"></i> Report</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="cams-card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-book-open"></i></div>
                        <div class="empty-title">No courses assigned yet</div>
                        <div class="empty-text">Contact the College Director to get courses assigned to you.</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>

</body>
</html>
