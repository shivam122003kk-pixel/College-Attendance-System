<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
$teacherId = (int)$_SESSION['userId'];

// Teacher's department info
$teacher = $conn->query("
    SELECT t.*, d.deptName, d.deptCode
    FROM tblteacher t
    LEFT JOIN tbldepartment d ON t.deptId=d.Id
    WHERE t.Id=$teacherId
")->fetch_assoc();

// My assigned courses
$myCourses = $conn->query("
    SELECT c.*, COUNT(DISTINCT s.Id) as studentCount,
           COUNT(DISTINCT sb.Id) as subjectCount
    FROM tblteacher_course tc
    JOIN tblcourse c ON tc.courseId = c.Id
    LEFT JOIN tblstudent s ON c.Id = s.courseId
    LEFT JOIN tblsubject sb ON sb.courseId=c.Id
    WHERE tc.teacherId = $teacherId
    GROUP BY c.Id
");

// My subjects
$mySubjects = $conn->query("
    SELECT sb.subjectName, sb.subjectCode, c.courseName, c.courseCode,
           COUNT(DISTINCT ss.studentId) as studentCount
    FROM tblteacher_subject ts
    JOIN tblsubject sb ON ts.subjectId=sb.Id
    JOIN tblcourse c ON sb.courseId=c.Id
    LEFT JOIN tblstudent_subject ss ON ss.subjectId=sb.Id
    WHERE ts.teacherId=$teacherId
    GROUP BY sb.Id
    ORDER BY c.courseName, sb.subjectName
");

$totalStudents = $conn->query("
    SELECT COUNT(DISTINCT s.Id) as c FROM tblstudent s
    JOIN tblteacher_course tc ON s.courseId=tc.courseId
    WHERE tc.teacherId=$teacherId
")->fetch_assoc()['c'];

$totalCourses = $conn->query("SELECT COUNT(*) as c FROM tblteacher_course WHERE teacherId=$teacherId")->fetch_assoc()['c'];
$totalSubjects = $conn->query("SELECT COUNT(*) as c FROM tblteacher_subject WHERE teacherId=$teacherId")->fetch_assoc()['c'];

$todayAttTaken = $conn->query("
    SELECT COUNT(DISTINCT courseId) as c FROM tblattendance
    WHERE takenByTeacherId=$teacherId AND dateTaken=CURDATE()
")->fetch_assoc()['c'];

// Low attendance in my courses
$lowAtt = $conn->query("
    SELECT s.firstName, s.lastName, s.rollNumber, c.courseName,
           COUNT(a.Id) as total, SUM(a.status) as present,
           ROUND(SUM(a.status)/COUNT(a.Id)*100,1) as pct
    FROM tblattendance a
    JOIN tblstudent s ON a.studentId=s.Id
    JOIN tblcourse c ON a.courseId=c.Id
    JOIN tblteacher_course tc ON a.courseId=tc.courseId
    WHERE tc.teacherId=$teacherId
    GROUP BY a.studentId, a.courseId HAVING pct<75 ORDER BY pct ASC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Teacher Dashboard - PIMT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <style>
        .teacher-hero { background:linear-gradient(135deg,#0f172a,#162942); border:1px solid rgba(0,180,216,0.25); border-radius:18px; padding:22px; margin-bottom:24px; display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
        .teacher-info-chip { background:rgba(255,255,255,.06); padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
        .subject-mini-card { background:var(--glass); border:1px solid var(--glass-border); border-radius:12px; padding:14px; }
    </style>
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
                <h1 class="page-title">Welcome, <span><?= htmlspecialchars($_SESSION['firstName']) ?></span>!</h1>
                <nav class="breadcrumb"><i class="fas fa-home"></i><span>Dashboard</span></nav>
            </div>

            <!-- Teacher Identity Hero -->
            <div class="teacher-hero">
                <div style="width:64px;height:64px;border-radius:14px;background:linear-gradient(135deg,var(--indigo),var(--teal));display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;color:#fff;flex-shrink:0">
                    <?= strtoupper(substr($_SESSION['firstName'],0,1)) ?>
                </div>
                <div style="flex:1">
                    <div style="font-family:'Outfit',sans-serif;font-size:20px;font-weight:800;margin-bottom:4px"><?= htmlspecialchars($_SESSION['firstName'].' '.$_SESSION['lastName']) ?></div>
                    <div style="color:var(--teal);font-size:13px;font-weight:600;margin-bottom:10px"><i class="fas fa-building" style="margin-right:5px"></i><?= htmlspecialchars($teacher['deptName']??'Not assigned') ?> <?= $teacher['deptCode']?'('.$teacher['deptCode'].')':'' ?></div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <div class="teacher-info-chip"><i class="fas fa-book-open" style="color:var(--gold)"></i><?=$totalCourses?> Course<?=$totalCourses!=1?'s':''?></div>
                        <div class="teacher-info-chip"><i class="fas fa-book" style="color:#fb923c"></i><?=$totalSubjects?> Subject<?=$totalSubjects!=1?'s':''?></div>
                        <div class="teacher-info-chip"><i class="fas fa-user-graduate" style="color:var(--success)"></i><?=$totalStudents?> Student<?=$totalStudents!=1?'s':''?></div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card teal"><div class="stat-icon"><i class="fas fa-book-open"></i></div><div class="stat-info"><div class="stat-label">My Courses</div><div class="stat-value"><?=$totalCourses?></div><div class="stat-sub">Assigned to you</div></div></div>
                <div class="stat-card indigo"><div class="stat-icon"><i class="fas fa-book"></i></div><div class="stat-info"><div class="stat-label">My Subjects</div><div class="stat-value"><?=$totalSubjects?></div><div class="stat-sub">Total subjects</div></div></div>
                <div class="stat-card gold"><div class="stat-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><div class="stat-label">My Students</div><div class="stat-value"><?=$totalStudents?></div><div class="stat-sub">Across all courses</div></div></div>
                <div class="stat-card success"><div class="stat-icon"><i class="fas fa-calendar-check"></i></div><div class="stat-info"><div class="stat-label">Today's Attendance</div><div class="stat-value"><?=$todayAttTaken?></div><div class="stat-sub">Courses marked today</div></div></div>
            </div>

            <!-- My Subjects -->
            <?php if($mySubjects && $mySubjects->num_rows > 0): ?>
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-book"></i> My Subjects</div></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
                        <?php while($sb=$mySubjects->fetch_assoc()): ?>
                        <div class="subject-mini-card">
                            <div style="font-weight:700;font-size:14px;margin-bottom:4px"><?=htmlspecialchars($sb['subjectName'])?></div>
                            <div style="margin-bottom:8px"><span class="badge badge-indigo" style="font-size:10px"><?=$sb['subjectCode']?></span> <span class="badge badge-info" style="font-size:10px"><?=$sb['courseCode']?></span></div>
                            <div style="font-size:11px;color:var(--text-muted)"><?=htmlspecialchars($sb['courseName'])?></div>
                            <div style="font-size:12px;font-weight:600;color:var(--success);margin-top:6px"><i class="fas fa-users" style="margin-right:4px"></i><?=$sb['studentCount']?> students</div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Low attendance alert -->
            <?php if ($lowAtt && $lowAtt->num_rows > 0): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <strong><?=$lowAtt->num_rows?> student(s)</strong> in your courses have below 75% attendance!</div>
            <div class="cams-card" style="border-color:rgba(239,35,60,0.25)">
                <div class="card-header"><div class="card-title"><i class="fas fa-exclamation-circle" style="color:#ef233c"></i> Students Below 75%</div><a href="viewAttendance.php" class="btn btn-sm btn-danger">View All</a></div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>Student</th><th>Roll No</th><th>Course</th><th>Attendance %</th></tr></thead>
                        <tbody>
                        <?php $lowAtt->data_seek(0); while($r=$lowAtt->fetch_assoc()): ?>
                        <tr class="att-row-danger">
                            <td><?=htmlspecialchars($r['firstName'].' '.$r['lastName'])?></td>
                            <td><span class="badge badge-absent"><?=$r['rollNumber']?></span></td>
                            <td><?=htmlspecialchars($r['courseName'])?></td>
                            <td><span class="att-pct danger"><?=$r['pct']?>%</span></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-bolt"></i> Quick Actions</div></div>
                <div class="card-body">
                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        <a href="takeAttendance.php" class="btn btn-primary"><i class="fas fa-calendar-check"></i> Take Attendance</a>
                        <a href="addStudents.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Add Student</a>
                        <a href="viewAttendance.php" class="btn btn-primary" style="background:linear-gradient(135deg,#0096b7,#00b4d8)"><i class="fas fa-chart-bar"></i> View Report</a>
                        <a href="myCourses.php" class="btn btn-warning"><i class="fas fa-book-open"></i> My Courses</a>
                        <a href="studentAnalytics.php" class="btn btn-primary" style="background:linear-gradient(135deg,#7c3aed,#9f67fa)"><i class="fas fa-chart-pie"></i> Student Analytics</a>
                    </div>
                </div>
            </div>

            <!-- My Courses Cards -->
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-book-open"></i> My Assigned Courses</div><a href="myCourses.php" class="btn btn-sm btn-primary">View All</a></div>
                <div class="card-body">
                    <div class="course-grid">
                    <?php $myCourses->data_seek(0); if($myCourses->num_rows>0): while($c=$myCourses->fetch_assoc()): ?>
                    <div class="course-card">
                        <div class="course-card-code"><?=$c['courseCode']?></div>
                        <div class="course-card-name"><?=htmlspecialchars($c['courseName'])?></div>
                        <div class="course-card-desc"><?=htmlspecialchars(substr($c['description'],0,80)).'...'?></div>
                        <div class="course-card-meta">
                            <span><i class="fas fa-users" style="margin-right:4px;color:var(--teal)"></i><?=$c['studentCount']?> students</span>
                            <span><i class="fas fa-book" style="margin-right:4px;color:#fb923c"></i><?=$c['subjectCount']?> subjects</span>
                            <span class="badge badge-info"><?=$c['duration']?></span>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                    <div class="empty-state"><div class="empty-icon"><i class="fas fa-book-open"></i></div><div class="empty-title">No courses assigned yet</div><div class="empty-text">Contact the Director to get courses assigned.</div></div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
</body>
</html>
