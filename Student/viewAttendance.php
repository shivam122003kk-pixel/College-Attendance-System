<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Student') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';

$studentId = $_SESSION['userId'];
$assignedCourses = $conn->query("
    SELECT DISTINCT c.Id,c.courseName,c.courseCode
    FROM tblcourse c
    JOIN (
        SELECT courseId FROM tblstudent WHERE Id=$studentId
        UNION
        SELECT courseId FROM tblstudent_course WHERE studentId=$studentId
    ) x ON c.Id=x.courseId
    ORDER BY c.courseName
");
$courseId = isset($_GET['courseId']) ? (int)$_GET['courseId'] : (int)$_SESSION['courseId'];
if ($assignedCourses && $assignedCourses->num_rows > 0) {
    $validCourses = [];
    while($vc = $assignedCourses->fetch_assoc()) $validCourses[] = (int)$vc['Id'];
    if (!in_array($courseId, $validCourses, true)) $courseId = $validCourses[0];
    $assignedCourses->data_seek(0);
}

// Course info
$course = $conn->query("SELECT * FROM tblcourse WHERE Id = $courseId")->fetch_assoc();
$subjectSummary = $conn->query("
    SELECT s.Id, s.subjectName, s.subjectCode, s.semester, COALESCE(ss.duration, s.duration) as duration,
           COUNT(a.Id) as totalDays,
           COALESCE(SUM(a.status),0) as presentDays,
           CASE WHEN COUNT(a.Id)>0 THEN ROUND(SUM(a.status)/COUNT(a.Id)*100,1) ELSE 0 END as pct
    FROM tblstudent_subject ss
    JOIN tblsubject s ON ss.subjectId=s.Id
    LEFT JOIN tblattendance a ON a.studentId=ss.studentId AND a.subjectId=s.Id
    WHERE ss.studentId=$studentId AND s.courseId=$courseId
    GROUP BY s.Id
    ORDER BY s.subjectName
");

// Summary
$att = $conn->query("
    SELECT COUNT(*) as totalDays, SUM(status) as presentDays
    FROM tblattendance WHERE studentId = $studentId AND courseId = $courseId
")->fetch_assoc();

$totalDays   = $att['totalDays'] ?? 0;
$presentDays = $att['presentDays'] ?? 0;
$absentDays  = $totalDays - $presentDays;
$pct         = ($totalDays > 0) ? round($presentDays / $totalDays * 100, 1) : null;
$isLow       = ($pct !== null && $pct < 75);

// Filter
$filterDate = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : '';

$whereDate = '';
if ($filterDate) {
    $whereDate = "AND dateTaken = '".mysqli_real_escape_string($conn,$filterDate)."'";
}

// All records
$records = $conn->query("
    SELECT a.dateTaken, a.status, COALESCE(s.subjectName, 'General Course Attendance') as subjectName, COALESCE(s.subjectCode, c.courseCode) as subjectCode
    FROM tblattendance a
    JOIN tblcourse c ON a.courseId=c.Id
    LEFT JOIN tblsubject s ON a.subjectId=s.Id
    WHERE a.studentId = $studentId AND a.courseId = $courseId $whereDate
    ORDER BY dateTaken DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>My Attendance - PIMT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <style>
        .flatpickr-input { padding:10px 40px 10px 14px !important; background:rgba(255,255,255,0.06) !important; border:1.5px solid rgba(255,255,255,0.12) !important; border-radius:10px !important; color:var(--text-light) !important; font-family:'Inter',sans-serif !important; font-size:14px !important; cursor:pointer !important; min-width:170px; outline:none; }
        .flatpickr-input:focus { border-color:var(--indigo-bright) !important; box-shadow:0 0 0 3px rgba(92,107,192,0.2) !important; }
        .flatpickr-calendar { background:#131e35 !important; border:1px solid rgba(255,255,255,.1) !important; border-radius:16px !important; }
        .fp-cal-icon { position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--indigo-bright);font-size:14px;pointer-events:none; }
        .att-summary-bar {
            display:flex; align-items:center; gap:20px;
            padding:20px 24px;
            background:var(--glass);
            border:1px solid var(--glass-border);
            border-radius:16px;
            margin-bottom:24px;
            flex-wrap:wrap;
        }
        .meter-ring { --pct: 0deg; }
        .mini-ring {
            width: 90px; height: 90px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .mini-ring.safe { background: conic-gradient(var(--success) var(--pct,0deg), rgba(6,214,160,0.1) 0); }
        .mini-ring.danger { background: conic-gradient(var(--red-alert) var(--pct,0deg), rgba(239,35,60,0.1) 0); }
        .mini-inner {
            width: 66px; height: 66px;
            background: var(--navy);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-direction: column;
        }
        .mini-pct { font-family:'Outfit',sans-serif; font-size:18px; font-weight:800; }
        .mini-ring.safe .mini-pct { color:var(--success); }
        .mini-ring.danger .mini-pct { color:var(--red-alert); }
        .att-day-row {
            display:flex; align-items:center; gap:14px;
            padding:12px 20px;
            border-bottom:1px solid rgba(255,255,255,0.04);
            transition:background 0.2s;
        }
        .att-day-row:hover { background:rgba(255,255,255,0.02); }
        .att-day-row:last-child { border-bottom:none; }
        .att-day-icon {
            width:36px; height:36px;
            border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:14px; flex-shrink:0;
        }
        .att-day-icon.p { background:rgba(6,214,160,0.15); color:var(--success); }
        .att-day-icon.a { background:rgba(239,35,60,0.12); color:var(--red-alert); }
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
                <h1 class="page-title">My <span>Attendance</span></h1>
                <nav class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <i class="fas fa-chevron-right"></i><span>My Attendance</span>
                </nav>
            </div>

            <!-- Alert Banner -->
            <?php if ($isLow): ?>
            <div class="alert alert-danger" style="font-size:14px;padding:16px 20px;margin-bottom:20px">
                <i class="fas fa-exclamation-triangle fa-lg"></i>
                <div>
                    <strong><i class="fas fa-exclamation-triangle"></i> DANGER - Attendance Below Minimum!</strong><br>
                    Your attendance is <strong><?= $pct ?>%</strong> which is <strong>below the mandatory 75%</strong>.
                    You may be barred from examinations. Please attend all remaining classes!
                </div>
            </div>
            <?php elseif ($pct !== null): ?>
            <div class="alert alert-success" style="margin-bottom:20px">
                <i class="fas fa-shield-alt"></i>
                Your attendance is <strong><?= $pct ?>%</strong> - above the 75% requirement. Keep attending regularly!
            </div>
            <?php endif; ?>

            <!-- Summary Bar -->
            <div class="att-summary-bar">
                <?php if ($pct !== null): ?>
                <div class="mini-ring <?= $isLow ? 'danger' : 'safe' ?>" style="--pct:<?= round($pct*3.6) ?>deg">
                    <div class="mini-inner">
                        <div class="mini-pct"><?= $pct ?>%</div>
                        <div style="font-size:9px;color:var(--text-muted);letter-spacing:1px">ATT.</div>
                    </div>
                </div>
                <?php endif; ?>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;flex:1">
                    <div style="text-align:center;padding:12px;background:var(--glass);border-radius:10px">
                        <div style="font-family:'Outfit',sans-serif;font-size:24px;font-weight:800;color:var(--indigo-bright)"><?= $totalDays ?></div>
                        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px">Total</div>
                    </div>
                    <div style="text-align:center;padding:12px;background:var(--glass);border-radius:10px">
                        <div style="font-family:'Outfit',sans-serif;font-size:24px;font-weight:800;color:var(--success)"><?= $presentDays ?></div>
                        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px">Present</div>
                    </div>
                    <div style="text-align:center;padding:12px;background:var(--glass);border-radius:10px">
                        <div style="font-family:'Outfit',sans-serif;font-size:24px;font-weight:800;color:var(--red-alert)"><?= $absentDays ?></div>
                        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px">Absent</div>
                    </div>
                </div>
                <?php if ($course): ?>
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">Course</div>
                    <span class="badge badge-indigo" style="font-size:13px;padding:5px 12px"><?= $course['courseCode'] ?></span>
                    <div style="font-size:12px;color:var(--text-light);margin-top:4px;max-width:180px;text-align:right"><?= htmlspecialchars($course['courseName']) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filter -->
            <div class="cams-card">
                <div class="card-body" style="padding:14px 20px">
                    <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                        <label class="form-label" style="margin:0;font-size:13px">Course:</label>
                        <select name="courseId" class="form-input" style="max-width:260px">
                            <?php if($assignedCourses): while($ac=$assignedCourses->fetch_assoc()): ?>
                            <option value="<?=$ac['Id']?>" <?=$courseId==(int)$ac['Id']?'selected':''?>>[<?=htmlspecialchars($ac['courseCode'])?>] <?=htmlspecialchars($ac['courseName'])?></option>
                            <?php endwhile; endif; ?>
                        </select>
                        <label class="form-label" style="margin:0;font-size:13px">Date:</label>
                        <div style="position:relative">
                            <input type="text" name="date" id="datePicker" class="flatpickr-input" style="max-width:200px" value="<?= $filterDate ?>" readonly="readonly" placeholder="Select date...">
                            <i class="fas fa-calendar-alt fp-cal-icon"></i>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                        <a href="viewAttendance.php?courseId=<?=$courseId?>" class="btn btn-sm" style="background:var(--glass);border:1px solid var(--glass-border)">Show All</a>
                    </form>
                </div>
            </div>

            <?php if ($subjectSummary && $subjectSummary->num_rows > 0): ?>
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-pie"></i> Subject Attendance Comparison</div>
                    <div class="table-tools"><div class="search-box"><i class="fas fa-search"></i><input class="form-input" data-table-search="#subjectSummaryTable" placeholder="Search subjects..."></div></div>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:minmax(220px,320px) 1fr;gap:24px;align-items:start">
                        <canvas id="subjectPieChart" width="320" height="320" style="max-width:100%"></canvas>
                        <div class="table-wrapper scroll-y">
                            <table class="cams-table" id="subjectSummaryTable">
                                <thead><tr><th>Subject</th><th>Semester</th><th>Duration</th><th>Total</th><th>Present</th><th>Attendance</th></tr></thead>
                                <tbody>
                                <?php
                                $chartLabels=[]; $chartValues=[];
                                $subjectSummary->data_seek(0);
                                while($sub=$subjectSummary->fetch_assoc()):
                                    $chartLabels[] = $sub['subjectCode'];
                                    $chartValues[] = (float)$sub['pct'];
                                    $low = ((float)$sub['pct'] < 75 && (int)$sub['totalDays'] > 0);
                                ?>
                                <tr class="<?=$low?'att-row-danger':''?>">
                                    <td><strong><?=htmlspecialchars($sub['subjectName'])?></strong><br><span class="badge badge-info"><?=htmlspecialchars($sub['subjectCode'])?></span></td>
                                    <td><?=htmlspecialchars($sub['semester'] ?: '-')?></td>
                                    <td><?=htmlspecialchars($sub['duration'] ?: '-')?></td>
                                    <td><?=$sub['totalDays']?></td>
                                    <td><?=$sub['presentDays']?></td>
                                    <td><span class="att-pct <?=$low?'danger':'safe'?>"><?=$sub['pct']?>%</span></td>
                                </tr>
                                <?php endwhile; ?>
                                <tr class="search-empty-row"><td colspan="6"><div class="empty-state"><div class="empty-title">No matching subjects</div></div></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Records -->
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-calendar-check"></i> Attendance Records</div>
                    <span style="font-size:12px;color:var(--text-muted)"><?= $records ? $records->num_rows : 0 ?> record(s)</span>
                </div>
                <div>
                <?php if ($records && $records->num_rows > 0):
                    while($r = $records->fetch_assoc()):
                    $isPresent = ($r['status'] == 1);
                ?>
                    <div class="att-day-row">
                    <div class="att-day-icon <?= $isPresent ? 'p' : 'a' ?>">
                        <i class="fas fa-<?= $isPresent ? 'check' : 'times' ?>"></i>
                    </div>
                    <div style="flex:1">
                        <div style="font-weight:600;font-size:14px"><?= date('l', strtotime($r['dateTaken'])) ?></div>
                        <div style="font-size:12px;color:var(--text-muted)"><?= date('d F Y', strtotime($r['dateTaken'])) ?> &middot; <?=htmlspecialchars($r['subjectCode'].' - '.$r['subjectName'])?></div>
                    </div>
                    <span class="badge <?= $isPresent ? 'badge-present' : 'badge-absent' ?>">
                        <i class="fas fa-<?= $isPresent ? 'check-circle' : 'times-circle' ?>"></i>
                        <?= $isPresent ? 'Present' : 'Absent' ?>
                    </span>
                </div>
                <?php endwhile; else: ?>
                <div class="empty-state" style="padding:60px">
                    <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
                    <div class="empty-title">No attendance records</div>
                    <div class="empty-text">
                        <?= $filterDate ? 'No records for this date.' : 'Your teacher has not taken attendance yet.' ?>
                    </div>
                </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../js/cams-search.js"></script>
<script>

flatpickr('#datePicker', {
    dateFormat: 'Y-m-d',
    disableMobile: true,
    theme: 'dark'
});
const subjectPie = document.getElementById('subjectPieChart');
if (subjectPie && window.Chart) {
    new Chart(subjectPie, {
        type: 'pie',
        data: {
            labels: <?=json_encode($chartLabels ?? [])?>,
            datasets: [{
                data: <?=json_encode($chartValues ?? [])?>,
                backgroundColor: ['#5c6bc0','#00b4d8','#06d6a0','#ffd166','#ef233c','#f472b6','#60a5fa','#fca311']
            }]
        },
        options: {
            plugins: { legend: { labels: { color: '#e0e7ff' } } }
        }
    });
}
</script>
</body>
</html>
