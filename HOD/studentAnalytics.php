<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'HOD') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
include '../Includes/analytics_helper.php';
$deptId = (int)$_SESSION['deptId'];
$hodId  = (int)$_SESSION['userId'];

// Resolve student: by Id or by roll
$studentId = 0;
if (isset($_GET['id'])) {
    $studentId = (int)$_GET['id'];
} elseif (isset($_GET['roll'])) {
    $r = $conn->query("SELECT Id FROM tblstudent WHERE rollNumber='".mysqli_real_escape_string($conn,trim($_GET['roll']))."' AND deptId=$deptId");
    if ($r && $r->num_rows) $studentId = $r->fetch_assoc()['Id'];
}
if (!$studentId) { header("Location: viewStudents.php"); exit; }

$student  = getStudentFullRecord($conn, $studentId);
if (!$student || $student['deptId'] != $deptId) { header("Location: viewStudents.php"); exit; }

$chartData = getStudentSubjectAttendance($conn, $studentId);

$overallAtt = $conn->query("SELECT COUNT(*) as total, SUM(status) as present FROM tblattendance WHERE studentId=$studentId")->fetch_assoc();
$totalDays   = $overallAtt['total'] ?? 0;
$presentDays = $overallAtt['present'] ?? 0;
$absentDays  = $totalDays - $presentDays;
$overallPct  = $totalDays > 0 ? round($presentDays/$totalDays*100,1) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Student Analytics - PIMT HOD</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <style>
        .analytics-hero{background:linear-gradient(135deg,#0f172a,#1e3a5f);border:1px solid rgba(79,99,210,0.35);border-radius:20px;padding:28px;margin-bottom:24px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;}
        .analytics-avatar{width:96px;height:96px;border-radius:18px;object-fit:cover;border:3px solid rgba(79,99,210,0.5);}
        .analytics-avatar-fallback{width:96px;height:96px;border-radius:18px;background:linear-gradient(135deg,var(--indigo),var(--teal));display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:800;color:#fff;border:3px solid rgba(79,99,210,0.5);}
        .subject-att-card{background:var(--glass);border:1px solid var(--glass-border);border-radius:14px;padding:18px;}
        .subject-att-bar-bg{background:rgba(255,255,255,0.07);border-radius:6px;height:8px;overflow:hidden;margin-top:6px;}
        .subject-att-bar-fill{height:8px;border-radius:6px;transition:width 1s ease;}
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
                <h1 class="page-title"><span>Student</span> Analytics</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><a href="viewStudents.php">Students</a><i class="fas fa-chevron-right"></i><span><?=htmlspecialchars($student['firstName'].' '.$student['lastName'])?></span></nav>
            </div>

            <!-- Student Hero -->
            <div class="analytics-hero">
                <?php if(!empty($student['photo'])): ?><img src="../uploads/students/<?=htmlspecialchars($student['photo'])?>" class="analytics-avatar">
                <?php else: ?><div class="analytics-avatar-fallback"><?=strtoupper(substr($student['firstName'],0,1).substr($student['lastName'],0,1))?></div><?php endif; ?>
                <div style="flex:1">
                    <h2 style="font-family:'Outfit',sans-serif;font-size:26px;font-weight:800;margin-bottom:4px"><?=htmlspecialchars($student['firstName'].' '.$student['lastName'])?></h2>
                    <div style="color:var(--teal);font-size:13px;font-weight:600;margin-bottom:8px"><?=htmlspecialchars($student['deptName']??'')?></div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        <div style="background:rgba(255,255,255,0.06);padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600"><i class="fas fa-id-badge" style="color:var(--indigo-bright);margin-right:6px"></i><?=htmlspecialchars($student['rollNumber'])?></div>
                        <div style="background:rgba(255,255,255,0.06);padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600"><i class="fas fa-book-open" style="color:var(--teal);margin-right:6px"></i><?=htmlspecialchars($student['courseCode'])?></div>
                    </div>
                </div>
                <div style="text-align:center">
                    <?php if($overallPct!==null): ?>
                    <div style="font-family:'Outfit',sans-serif;font-size:48px;font-weight:900;color:<?=$overallPct<75?'var(--red-alert)':'var(--success)'?>;line-height:1"><?=$overallPct?>%</div>
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px">Overall Attendance</div>
                    <?php else: ?><div style="font-size:18px;color:var(--text-muted)">No data yet</div><?php endif; ?>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid" style="margin-bottom:24px">
                <div class="stat-card indigo"><div class="stat-icon"><i class="fas fa-calendar-alt"></i></div><div class="stat-info"><div class="stat-label">Total Classes</div><div class="stat-value"><?=$totalDays?></div></div></div>
                <div class="stat-card success"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-info"><div class="stat-label">Present</div><div class="stat-value"><?=$presentDays?></div></div></div>
                <div class="stat-card" style="border-color:rgba(239,35,60,0.3)"><div class="stat-icon" style="color:var(--red-alert)"><i class="fas fa-times-circle"></i></div><div class="stat-info"><div class="stat-label">Absent</div><div class="stat-value" style="color:var(--red-alert)"><?=$absentDays?></div></div></div>
                <div class="stat-card gold"><div class="stat-icon"><i class="fas fa-book"></i></div><div class="stat-info"><div class="stat-label">Subjects</div><div class="stat-value"><?=count($student['subjects'])?></div></div></div>
            </div>

            <!-- Charts -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
                <div class="cams-card" style="margin-bottom:0">
                    <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie"></i> Subject-wise Attendance</div></div>
                    <div class="card-body" style="position:relative;height:300px;display:flex;justify-content:center">
                        <?php if(!empty($chartData['labels'])): ?><canvas id="subjectPieChart"></canvas>
                        <?php else: ?><div class="empty-state"><div class="empty-icon"><i class="fas fa-chart-pie"></i></div><div class="empty-title">No data yet</div></div><?php endif; ?>
                    </div>
                </div>
                <div class="cams-card" style="margin-bottom:0">
                    <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar"></i> Attendance % per Subject</div></div>
                    <div class="card-body" style="position:relative;height:300px">
                        <?php if(!empty($chartData['labels'])): ?><canvas id="subjectBarChart"></canvas>
                        <?php else: ?><div class="empty-state"><div class="empty-icon"><i class="fas fa-chart-bar"></i></div><div class="empty-title">No data yet</div></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Subject Breakdown -->
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-book"></i> Subject-wise Attendance Breakdown</div></div>
                <div class="card-body">
                    <?php if(!empty($student['subjects'])): ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">
                        <?php foreach($student['subjects'] as $sb):
                            $pct=$sb['pct'];
                            $fillColor=$pct===null?'#475569':($pct<75?'#ef233c':($pct<90?'#f59e0b':'#06d6a0'));
                        ?>
                        <div class="subject-att-card">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
                                <div><div style="font-weight:700;font-size:14px"><?=htmlspecialchars($sb['subjectName'])?></div><div style="font-size:11px"><span class="badge badge-indigo"><?=$sb['subjectCode']?></span></div></div>
                                <div style="font-weight:800;font-size:22px;color:<?=$fillColor?>"><?=$pct!==null?$pct.'%':'N/A'?></div>
                            </div>
                            <div class="subject-att-bar-bg"><div class="subject-att-bar-fill" style="width:<?=$pct??0?>%;background:<?=$fillColor?>"></div></div>
                            <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:11px;color:var(--text-muted)">
                                <span>Present: <?=$sb['presentDays']??0?></span>
                                <span>Absent: <?=($sb['totalDays']-($sb['presentDays']??0))?></span>
                                <span>Total: <?=$sb['totalDays']?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?><div class="empty-state"><div class="empty-icon"><i class="fas fa-book"></i></div><div class="empty-title">No subjects enrolled yet</div></div><?php endif; ?>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.color='#7f8eaa'; Chart.defaults.font.family="'Inter',sans-serif";
<?php if(!empty($chartData['labels'])): ?>
const labels=<?=json_encode($chartData['labels'])?>;
const present=<?=json_encode($chartData['present'])?>;
const pcts=<?=json_encode($chartData['pcts'])?>;
const palette=['#06d6a0','#4f63d2','#f59e0b','#ef233c','#60a5fa','#a78bfa','#fb923c','#34d399','#f472b6','#facc15'];
new Chart(document.getElementById('subjectPieChart').getContext('2d'),{
    type:'doughnut', data:{labels:labels,datasets:[{data:present,backgroundColor:palette.slice(0,labels.length),borderWidth:2,borderColor:'#0d1b2e',hoverOffset:8}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{padding:14,usePointStyle:true}}},cutout:'55%'}
});
new Chart(document.getElementById('subjectBarChart').getContext('2d'),{
    type:'bar', data:{labels:labels,datasets:[{label:'Attendance %',data:pcts,backgroundColor:pcts.map(p=>p>=75?'rgba(6,214,160,0.8)':'rgba(239,35,60,0.8)'),borderRadius:6,barThickness:30}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100,grid:{color:'rgba(255,255,255,0.05)'},ticks:{stepSize:25}},x:{grid:{display:false}}}}
});
<?php endif; ?>
</script>
</body></html>
