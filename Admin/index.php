<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Director') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';

// â”€â”€ College-wide Stats â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$totalDepts       = $conn->query("SELECT COUNT(*) c FROM tbldepartment")->fetch_assoc()['c'];
$totalHODs        = $conn->query("SELECT COUNT(*) c FROM tblhod")->fetch_assoc()['c'];
$totalTeachers    = $conn->query("SELECT COUNT(*) c FROM tblteacher")->fetch_assoc()['c'];
$totalCourses     = $conn->query("SELECT COUNT(*) c FROM tblcourse")->fetch_assoc()['c'];
$totalSubjects    = $conn->query("SELECT COUNT(*) c FROM tblsubject")->fetch_assoc()['c'];
$totalStudents    = $conn->query("SELECT COUNT(*) c FROM tblstudent")->fetch_assoc()['c'];
$totalAttendance  = $conn->query("SELECT COUNT(*) c FROM tblattendance")->fetch_assoc()['c'];
$assignments      = $conn->query("SELECT COUNT(*) c FROM tblteacher_course")->fetch_assoc()['c'];

// â”€â”€ Attendance Aggregates â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$overallAtt = $conn->query("SELECT SUM(status=1) totalPresent, SUM(status=0) totalAbsent FROM tblattendance")->fetch_assoc();
$totalPresent = $overallAtt['totalPresent'] ?? 0;
$totalAbsent  = $overallAtt['totalAbsent']  ?? 0;

// â”€â”€ Department-wise Analytics â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$deptStats = $conn->query("
    SELECT d.Id, d.deptName, d.deptCode,
           COUNT(DISTINCT tc.courseId) as courses,
           COUNT(DISTINCT t.Id)        as teachers,
           COUNT(DISTINCT s.Id)        as students,
           COUNT(DISTINCT sb.Id)       as subjects
    FROM tbldepartment d
    LEFT JOIN tblteacher   t  ON t.deptId  = d.Id
    LEFT JOIN tblteacher_course tc ON tc.teacherId = t.Id
    LEFT JOIN tblstudent   s  ON s.deptId  = d.Id
    LEFT JOIN tblsubject   sb ON sb.deptId = d.Id
    GROUP BY d.Id ORDER BY d.deptName
");
$deptLabels = []; $deptStudents = []; $deptTeachers = [];
$deptRows = [];
if ($deptStats) {
    while ($r = $deptStats->fetch_assoc()) {
        $deptLabels[]   = $r['deptCode'];
        $deptStudents[] = (int)$r['students'];
        $deptTeachers[] = (int)$r['teachers'];
        $deptRows[]     = $r;
    }
}

// â”€â”€ Course-wise Attendance â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$courseAtt = $conn->query("
    SELECT c.courseCode, ROUND(SUM(a.status)/COUNT(a.Id)*100,1) as pct
    FROM tblattendance a JOIN tblcourse c ON a.courseId = c.Id
    GROUP BY c.Id ORDER BY pct DESC
");
$courseLabels = []; $coursePcts = [];
if ($courseAtt) {
    while ($r = $courseAtt->fetch_assoc()) { $courseLabels[] = $r['courseCode']; $coursePcts[] = $r['pct']; }
}

// â”€â”€ Recent Students â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$recentStudents = $conn->query("
    SELECT s.firstName, s.lastName, s.rollNumber, s.dateCreated, c.courseName, d.deptName
    FROM tblstudent s JOIN tblcourse c ON s.courseId=c.Id
    LEFT JOIN tbldepartment d ON s.deptId=d.Id
    ORDER BY s.dateCreated DESC LIMIT 5
");

// â”€â”€ Low Attendance â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$lowAtt = $conn->query("
    SELECT s.firstName, s.lastName, s.rollNumber, c.courseName,
           COUNT(*) as total, SUM(a.status) as present,
           ROUND(SUM(a.status)/COUNT(*)*100,1) as pct
    FROM tblattendance a
    JOIN tblstudent s ON a.studentId=s.Id
    JOIN tblcourse c ON a.courseId=c.Id
    GROUP BY a.studentId, a.courseId HAVING pct<75 ORDER BY pct ASC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Director Dashboard - PIMT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <style>
        .big-stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:24px; }
        .big-stat { background:var(--glass); border:1px solid var(--glass-border); border-radius:16px; padding:20px 16px; text-align:center; transition:transform .2s; }
        .big-stat:hover { transform:translateY(-3px); }
        .big-stat-val { font-family:'Outfit',sans-serif; font-size:32px; font-weight:800; }
        .big-stat-lbl { font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-top:4px; }
        .big-stat-icon { font-size:20px; margin-bottom:8px; }
        .dept-analytics-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
        .dept-card { background:var(--glass); border:1px solid var(--glass-border); border-radius:14px; padding:18px; }
        .dept-card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
        .dept-card-name { font-weight:700; font-size:14px; }
        .dept-card-code { font-size:11px; background:var(--indigo-light); color:var(--indigo-bright); padding:3px 8px; border-radius:6px; }
        .dept-mini-stats { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .dept-mini { background:rgba(255,255,255,.03); border-radius:8px; padding:8px; text-align:center; }
        .dept-mini-val { font-weight:700; font-size:18px; }
        .dept-mini-lbl { font-size:10px; color:var(--text-muted); text-transform:uppercase; }
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
                <h1 class="page-title"><span>Director</span> Dashboard</h1>
                <nav class="breadcrumb"><i class="fas fa-home"></i><span>Dashboard</span></nav>
            </div>

            <!-- College-Wide Stats (7 cards) -->
            <div class="big-stats-grid">
                <div class="big-stat"><div class="big-stat-icon" style="color:var(--indigo-bright)"><i class="fas fa-building"></i></div><div class="big-stat-val" style="color:var(--indigo-bright)"><?= $totalDepts ?></div><div class="big-stat-lbl">Departments</div></div>
                <div class="big-stat"><div class="big-stat-icon" style="color:var(--teal)"><i class="fas fa-sitemap"></i></div><div class="big-stat-val" style="color:var(--teal)"><?= $totalHODs ?></div><div class="big-stat-lbl">HODs</div></div>
                <div class="big-stat"><div class="big-stat-icon" style="color:#7c83fd"><i class="fas fa-chalkboard-teacher"></i></div><div class="big-stat-val" style="color:#7c83fd"><?= $totalTeachers ?></div><div class="big-stat-lbl">Teachers</div></div>
                <div class="big-stat"><div class="big-stat-icon" style="color:var(--gold)"><i class="fas fa-book-open"></i></div><div class="big-stat-val" style="color:var(--gold)"><?= $totalCourses ?></div><div class="big-stat-lbl">Courses</div></div>
                <div class="big-stat"><div class="big-stat-icon" style="color:#fb923c"><i class="fas fa-book"></i></div><div class="big-stat-val" style="color:#fb923c"><?= $totalSubjects ?></div><div class="big-stat-lbl">Subjects</div></div>
                <div class="big-stat"><div class="big-stat-icon" style="color:var(--success)"><i class="fas fa-user-graduate"></i></div><div class="big-stat-val" style="color:var(--success)"><?= $totalStudents ?></div><div class="big-stat-lbl">Students</div></div>
                <div class="big-stat"><div class="big-stat-icon" style="color:#60a5fa"><i class="fas fa-calendar-check"></i></div><div class="big-stat-val" style="color:#60a5fa"><?= $totalAttendance ?></div><div class="big-stat-lbl">Attendance Rec.</div></div>
            </div>

            <!-- Charts Row -->
            <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;margin-bottom:24px;">
                <div class="cams-card" style="margin-bottom:0;">
                    <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie"></i> Overall Attendance</div></div>
                    <div class="card-body" style="display:flex;justify-content:center;position:relative;height:280px;">
                        <canvas id="overallChart"></canvas>
                    </div>
                </div>
                <div class="cams-card" style="margin-bottom:0;">
                    <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar"></i> Course-wise Attendance %</div></div>
                    <div class="card-body" style="position:relative;height:280px;">
                        <canvas id="courseChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Dept vs Students vs Teachers Chart -->
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar"></i> Department Analytics</div></div>
                <div class="card-body" style="position:relative;height:260px;">
                    <canvas id="deptChart"></canvas>
                </div>
            </div>

            <!-- Department Cards -->
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-building"></i> Department Overview</div></div>
                <div class="card-body">
                    <div class="dept-analytics-grid">
                        <?php foreach ($deptRows as $dept): ?>
                        <div class="dept-card">
                            <div class="dept-card-header">
                                <div class="dept-card-name"><?= htmlspecialchars($dept['deptName']) ?></div>
                                <div class="dept-card-code"><?= htmlspecialchars($dept['deptCode']) ?></div>
                            </div>
                            <div class="dept-mini-stats">
                                <div class="dept-mini"><div class="dept-mini-val" style="color:var(--gold)"><?= $dept['courses'] ?></div><div class="dept-mini-lbl">Courses</div></div>
                                <div class="dept-mini"><div class="dept-mini-val" style="color:#fb923c"><?= $dept['subjects'] ?></div><div class="dept-mini-lbl">Subjects</div></div>
                                <div class="dept-mini"><div class="dept-mini-val" style="color:#7c83fd"><?= $dept['teachers'] ?></div><div class="dept-mini-lbl">Teachers</div></div>
                                <div class="dept-mini"><div class="dept-mini-val" style="color:var(--success)"><?= $dept['students'] ?></div><div class="dept-mini-lbl">Students</div></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Low Attendance Alert -->
            <?php if ($lowAtt && $lowAtt->num_rows > 0): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <strong><?= $lowAtt->num_rows ?> student(s)</strong>&nbsp;have attendance below 75% - Immediate attention required!</div>
            <div class="cams-card" style="border-color:rgba(239,35,60,0.25)">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-exclamation-circle" style="color:#ef233c"></i> Low Attendance Students (&lt;75%)</div>
                    <a href="viewAllAttendance.php" class="btn btn-sm btn-danger">View All</a>
                </div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>#</th><th>Student</th><th>Roll No</th><th>Course</th><th>Attendance %</th></tr></thead>
                        <tbody>
                        <?php $i=1; while($r = $lowAtt->fetch_assoc()): ?>
                        <tr class="att-row-danger">
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($r['firstName'].' '.$r['lastName']) ?></td>
                            <td><span class="badge badge-absent"><?= $r['rollNumber'] ?></span></td>
                            <td><?= htmlspecialchars($r['courseName']) ?></td>
                            <td><span class="att-pct danger"><?= $r['pct'] ?>%</span></td>
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
                        <a href="manageTeachers.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Teacher</a>
                        <a href="manageCourses.php" class="btn btn-success"><i class="fas fa-plus"></i> Add Course</a>
                        <a href="manageSubjects.php" class="btn btn-warning"><i class="fas fa-book"></i> Add Subject</a>
                        <a href="assignCourse.php" class="btn btn-warning"><i class="fas fa-link"></i> Assign Course</a>
                        <a href="viewAllStudents.php" class="btn btn-primary" style="background:linear-gradient(135deg,#5c6bc0,#7986cb)"><i class="fas fa-users"></i> View Students</a>
                        <a href="viewAllAttendance.php" class="btn btn-primary" style="background:linear-gradient(135deg,#0096b7,#00b4d8)"><i class="fas fa-calendar-check"></i> View Attendance</a>
                        <a href="studentAnalytics.php" class="btn btn-primary" style="background:linear-gradient(135deg,#7c3aed,#9f67fa)"><i class="fas fa-chart-pie"></i> Student Analytics</a>
                    </div>
                </div>
            </div>

            <!-- Recent Students -->
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-user-plus"></i> Recently Added Students</div>
                    <a href="viewAllStudents.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>#</th><th>Name</th><th>Roll No</th><th>Course</th><th>Department</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php if ($recentStudents && $recentStudents->num_rows > 0):
                            $i = 1; while($r = $recentStudents->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($r['firstName'].' '.$r['lastName']) ?></td>
                            <td><span class="badge badge-indigo"><?= $r['rollNumber'] ?></span></td>
                            <td><?= htmlspecialchars($r['courseName']) ?></td>
                            <td><?= htmlspecialchars($r['deptName'] ?? 'N/A') ?></td>
                            <td><?= date('d M Y', strtotime($r['dateCreated'])) ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:32px">No students added yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><!-- /page-content -->
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.color = '#7f8eaa';
Chart.defaults.font.family = "'Inter', sans-serif";

// Overall Attendance Doughnut
new Chart(document.getElementById('overallChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Absent'],
        datasets: [{ data: [<?= $totalPresent ?>, <?= $totalAbsent ?>], backgroundColor: ['#06d6a0', '#ef233c'], borderWidth: 0, hoverOffset: 4 }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{position:'bottom',labels:{padding:20,usePointStyle:true}} }, cutout:'70%' }
});

// Course Performance Bar
new Chart(document.getElementById('courseChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($courseLabels) ?>,
        datasets: [{ label:'Attendance %', data:<?= json_encode($coursePcts) ?>, backgroundColor:'rgba(79,99,210,0.8)', borderRadius:6, barThickness:28 }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,max:100,grid:{color:'rgba(255,255,255,0.05)'},ticks:{stepSize:20}},x:{grid:{display:false}}} }
});

// Department Analytics Grouped Bar
new Chart(document.getElementById('deptChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($deptLabels) ?>,
        datasets: [
            { label:'Students', data:<?= json_encode($deptStudents) ?>, backgroundColor:'rgba(6,214,160,0.75)', borderRadius:5 },
            { label:'Teachers', data:<?= json_encode($deptTeachers) ?>, backgroundColor:'rgba(124,131,253,0.75)', borderRadius:5 }
        ]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}}, scales:{y:{beginAtZero:true,grid:{color:'rgba(255,255,255,0.05)'}},x:{grid:{display:false}}} }
});
</script>
</body>
</html>
