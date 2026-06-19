<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'HOD') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
$deptId = (int)$_SESSION['deptId'];
$hodId  = (int)$_SESSION['userId'];

// â”€â”€ Department Info â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$dept        = $conn->query("SELECT * FROM tbldepartment WHERE Id=$deptId")->fetch_assoc();
$teachers    = $conn->query("SELECT COUNT(*) c FROM tblteacher WHERE deptId=$deptId")->fetch_assoc()['c'];
$students    = $conn->query("SELECT COUNT(DISTINCT s.Id) c FROM tblstudent s WHERE s.deptId=$deptId")->fetch_assoc()['c'];
$attRecords  = $conn->query("SELECT COUNT(*) c FROM tblattendance a JOIN tblteacher t ON a.takenByTeacherId=t.Id WHERE t.deptId=$deptId")->fetch_assoc()['c'];

// â”€â”€ Courses in dept â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$coursesQuery = $conn->query("
    SELECT c.Id, c.courseName, c.courseCode,
           COUNT(DISTINCT sb.Id)  as subjectCount,
           COUNT(DISTINCT s.Id)   as studentCount,
           COUNT(DISTINCT tc.teacherId) as teacherCount
    FROM tblhod_course hc
    JOIN tblcourse c ON hc.courseId=c.Id
    LEFT JOIN tblsubject sb ON sb.courseId=c.Id
    LEFT JOIN tblstudent s  ON s.courseId=c.Id AND s.deptId=$deptId
    LEFT JOIN tblteacher_course tc ON tc.courseId=c.Id
    WHERE hc.hodId=$hodId
    GROUP BY c.Id ORDER BY c.courseName
");
$totalCourses = $coursesQuery ? $coursesQuery->num_rows : 0;
$totalSubjects = $conn->query("SELECT COUNT(*) c FROM tblsubject WHERE deptId=$deptId")->fetch_assoc()['c'];

// â”€â”€ Attendance analytics â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$overallAtt = $conn->query("
    SELECT SUM(a.status=1) as totalPresent, SUM(a.status=0) as totalAbsent
    FROM tblattendance a JOIN tblteacher t ON a.takenByTeacherId=t.Id
    WHERE t.deptId=$deptId
")->fetch_assoc();
$totalPresent = $overallAtt['totalPresent'] ?? 0;
$totalAbsent  = $overallAtt['totalAbsent']  ?? 0;

// Course-wise attendance
$courseAtt = $conn->query("
    SELECT c.courseCode, ROUND(SUM(a.status)/COUNT(a.Id)*100,1) as pct
    FROM tblattendance a
    JOIN tblcourse c ON a.courseId=c.Id
    JOIN tblteacher t ON a.takenByTeacherId=t.Id
    WHERE t.deptId=$deptId
    GROUP BY c.Id ORDER BY pct DESC
");
$courseLabels=[]; $coursePcts=[];
if($courseAtt) while($r=$courseAtt->fetch_assoc()){ $courseLabels[]=$r['courseCode']; $coursePcts[]=$r['pct']; }

// Low attendance
$lowAtt = $conn->query("
    SELECT s.firstName,s.lastName,s.rollNumber,c.courseName,
           COUNT(a.Id) as tot, SUM(a.status) as pres,
           ROUND(SUM(a.status)/COUNT(a.Id)*100,1) as pct
    FROM tblattendance a
    JOIN tblstudent s ON a.studentId=s.Id
    JOIN tblcourse c ON a.courseId=c.Id
    JOIN tblteacher t ON a.takenByTeacherId=t.Id
    WHERE t.deptId=$deptId
    GROUP BY a.studentId,a.courseId HAVING pct<75 ORDER BY pct ASC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>HOD Dashboard - PIMT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <style>
        .hod-big-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:24px; }
        .hod-big-stat { background:var(--glass); border:1px solid var(--glass-border); border-radius:14px; padding:18px; text-align:center; transition:transform .2s; }
        .hod-big-stat:hover { transform:translateY(-3px); }
        .hod-big-stat-val { font-family:'Outfit',sans-serif; font-size:30px; font-weight:800; }
        .hod-big-stat-lbl { font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-top:4px; }
        .course-summary-card { background:var(--glass); border:1px solid var(--glass-border); border-radius:12px; padding:14px; }
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
                <div>
                    <h1 class="page-title">HOD <span>Dashboard</span></h1>
                    <p style="font-size:13px;color:var(--text-muted);margin-top:4px">
                        <i class="fas fa-building" style="color:var(--teal);margin-right:5px"></i>
                        <?= htmlspecialchars($dept['deptName'] ?? 'Department') ?>
                        &nbsp;&middot;&nbsp; <?= $dept['deptCode'] ?? '' ?>
                    </p>
                </div>
                <nav class="breadcrumb"><i class="fas fa-home"></i><span>Dashboard</span></nav>
            </div>

            <!-- 6-stat Overview -->
            <div class="hod-big-stats">
                <div class="hod-big-stat"><div style="font-size:20px;color:var(--gold);margin-bottom:6px"><i class="fas fa-book-open"></i></div><div class="hod-big-stat-val" style="color:var(--gold)"><?=$totalCourses?></div><div class="hod-big-stat-lbl">Courses</div></div>
                <div class="hod-big-stat"><div style="font-size:20px;color:#fb923c;margin-bottom:6px"><i class="fas fa-book"></i></div><div class="hod-big-stat-val" style="color:#fb923c"><?=$totalSubjects?></div><div class="hod-big-stat-lbl">Subjects</div></div>
                <div class="hod-big-stat"><div style="font-size:20px;color:#7c83fd;margin-bottom:6px"><i class="fas fa-chalkboard-teacher"></i></div><div class="hod-big-stat-val" style="color:#7c83fd"><?=$teachers?></div><div class="hod-big-stat-lbl">Teachers</div></div>
                <div class="hod-big-stat"><div style="font-size:20px;color:var(--success);margin-bottom:6px"><i class="fas fa-user-graduate"></i></div><div class="hod-big-stat-val" style="color:var(--success)"><?=$students?></div><div class="hod-big-stat-lbl">Students</div></div>
                <div class="hod-big-stat"><div style="font-size:20px;color:var(--teal);margin-bottom:6px"><i class="fas fa-calendar-check"></i></div><div class="hod-big-stat-val" style="color:var(--teal)"><?=$attRecords?></div><div class="hod-big-stat-lbl">Att. Records</div></div>
                <div class="hod-big-stat"><div style="font-size:20px;color:var(--indigo-bright);margin-bottom:6px"><i class="fas fa-building"></i></div><div class="hod-big-stat-val" style="color:var(--indigo-bright)">1</div><div class="hod-big-stat-lbl">Department</div></div>
            </div>

            <!-- Charts -->
            <div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;margin-bottom:24px">
                <div class="cams-card" style="margin-bottom:0">
                    <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie"></i> Dept Attendance</div></div>
                    <div class="card-body" style="display:flex;justify-content:center;position:relative;height:280px">
                        <canvas id="overallChart"></canvas>
                    </div>
                </div>
                <div class="cams-card" style="margin-bottom:0">
                    <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar"></i> Course Performance</div></div>
                    <div class="card-body" style="position:relative;height:280px">
                        <canvas id="courseChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Course Breakdown -->
            <?php if($coursesQuery && $coursesQuery->num_rows > 0): ?>
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-book-open"></i> Course-wise Breakdown</div></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px">
                        <?php $coursesQuery->data_seek(0); while($c=$coursesQuery->fetch_assoc()): ?>
                        <div class="course-summary-card">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                                <div>
                                    <div style="font-weight:700"><?=htmlspecialchars($c['courseName'])?></div>
                                    <span class="badge badge-indigo" style="font-size:10px"><?=$c['courseCode']?></span>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;text-align:center">
                                <div style="background:rgba(255,200,68,0.08);border-radius:8px;padding:8px"><div style="font-weight:700;color:var(--gold)"><?=$c['subjectCount']?></div><div style="font-size:10px;color:var(--text-muted)">Subjects</div></div>
                                <div style="background:rgba(6,214,160,0.08);border-radius:8px;padding:8px"><div style="font-weight:700;color:var(--success)"><?=$c['studentCount']?></div><div style="font-size:10px;color:var(--text-muted)">Students</div></div>
                                <div style="background:rgba(124,131,253,0.08);border-radius:8px;padding:8px"><div style="font-weight:700;color:#7c83fd"><?=$c['teacherCount']?></div><div style="font-size:10px;color:var(--text-muted)">Teachers</div></div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Low Attendance -->
            <?php if ($lowAtt && $lowAtt->num_rows > 0): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <strong><?=$lowAtt->num_rows?> student(s)</strong> below 75% attendance in your department!</div>
            <div class="cams-card" style="border-color:rgba(239,35,60,.25)">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-exclamation-circle" style="color:#ef233c"></i> Low Attendance Students</div>
                    <a href="viewAttendance.php" class="btn btn-sm btn-danger">Full Report</a>
                </div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>#</th><th>Student</th><th>Roll No</th><th>Course</th><th>Attendance</th></tr></thead>
                        <tbody>
                        <?php $i=1; while($r=$lowAtt->fetch_assoc()): ?>
                        <tr class="att-row-danger">
                            <td><?=$i++?></td>
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
                        <a href="manageTeachers.php" class="btn btn-primary"><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</a>
                        <a href="manageStudents.php" class="btn btn-success"><i class="fas fa-user-graduate"></i> Manage Students</a>
                        <a href="myDepartment.php" class="btn btn-warning"><i class="fas fa-building"></i> My Department</a>
                        <a href="viewAttendance.php" class="btn btn-primary" style="background:linear-gradient(135deg,#0096b7,#00b4d8)"><i class="fas fa-chart-bar"></i> Attendance Report</a>
                        <a href="studentAnalytics.php" class="btn btn-primary" style="background:linear-gradient(135deg,#7c3aed,#9f67fa)"><i class="fas fa-chart-pie"></i> Student Analytics</a>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.color='#7f8eaa'; Chart.defaults.font.family="'Inter', sans-serif";
new Chart(document.getElementById('overallChart').getContext('2d'),{
    type:'doughnut',
    data:{labels:['Present','Absent'],datasets:[{data:[<?=$totalPresent?>,<?=$totalAbsent?>],backgroundColor:['#06d6a0','#ef233c'],borderWidth:0,hoverOffset:4}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{padding:20,usePointStyle:true}}},cutout:'70%'}
});
new Chart(document.getElementById('courseChart').getContext('2d'),{
    type:'bar',
    data:{labels:<?=json_encode($courseLabels)?>,datasets:[{label:'Attendance %',data:<?=json_encode($coursePcts)?>,backgroundColor:'rgba(0,180,216,0.8)',borderRadius:6,barThickness:30}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100,grid:{color:'rgba(255,255,255,0.05)'},ticks:{stepSize:20}},x:{grid:{display:false}}}}
});
</script>
</body></html>
