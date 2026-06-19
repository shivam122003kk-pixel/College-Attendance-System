<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
$teacherId = $_SESSION['userId'];

// Teacher's courses
$myCourses = $conn->query("
    SELECT c.Id, c.courseName, c.courseCode
    FROM tblteacher_course tc JOIN tblcourse c ON tc.courseId = c.Id
    WHERE tc.teacherId = $teacherId ORDER BY c.courseName
");

$filterCourse  = isset($_GET['courseId']) ? (int)$_GET['courseId'] : 0;
$filterSubject = isset($_GET['subject'])  ? (int)$_GET['subject']  : 0;
$filterDate    = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : '';
$rollSearch    = isset($_GET['roll'])   ? trim($_GET['roll'])  : '';

if (!$filterCourse && $myCourses->num_rows > 0) {
    $myCourses->data_seek(0);
    $first = $myCourses->fetch_assoc();
    $filterCourse = $first['Id'];
    $myCourses->data_seek(0);
}

// Summary
$summary = [];
if ($filterCourse) {
    $attWhere = "WHERE courseId = $filterCourse";
    if ($filterSubject) $attWhere .= " AND subjectId = $filterSubject";
    $res = $conn->query("
        SELECT s.Id, s.firstName, s.lastName, s.rollNumber,
               COALESCE(a.totalDays,0) as totalDays,
               COALESCE(a.presentDays,0) as presentDays,
               CASE WHEN a.totalDays > 0 THEN ROUND(a.presentDays/a.totalDays*100,1) ELSE NULL END as pct
        FROM tblstudent s
        LEFT JOIN (
            SELECT studentId, COUNT(*) as totalDays, COALESCE(SUM(status),0) as presentDays
            FROM tblattendance
            $attWhere
            GROUP BY studentId
        ) a ON a.studentId = s.Id
        WHERE (s.courseId = $filterCourse OR EXISTS (
            SELECT 1 FROM tblstudent_course sc WHERE sc.studentId=s.Id AND sc.courseId=$filterCourse
        ))
        " . ($rollSearch ? " AND s.rollNumber LIKE '%".mysqli_real_escape_string($conn,$rollSearch)."%'" : "") . "
        ORDER BY pct ASC
    ");
    while ($r = $res->fetch_assoc()) $summary[] = $r;
}
$mySubjects = $conn->query("SELECT sb.Id,sb.subjectName,sb.subjectCode FROM tblsubject sb JOIN tblteacher_course tc ON sb.courseId=tc.courseId WHERE tc.teacherId=$teacherId AND sb.courseId=$filterCourse ORDER BY sb.subjectName");

// Daily records
$whereClause = "WHERE tc.teacherId = $teacherId";
if ($filterCourse) $whereClause .= " AND a.courseId = $filterCourse";
if ($filterSubject) $whereClause .= " AND a.subjectId = $filterSubject";
if ($filterDate)   $whereClause .= " AND a.dateTaken = '".mysqli_real_escape_string($conn,$filterDate)."'";

$records = $conn->query("
    SELECT a.*, s.firstName, s.lastName, s.rollNumber, c.courseCode, sb.subjectName, sb.subjectCode
    FROM tblattendance a
    JOIN tblstudent s ON a.studentId = s.Id
    JOIN tblcourse c  ON a.courseId = c.Id
    JOIN tblteacher_course tc ON a.courseId = tc.courseId
    LEFT JOIN tblsubject sb ON a.subjectId = sb.Id
    $whereClause
    ORDER BY a.dateTaken DESC, s.firstName
    LIMIT 300
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>View Attendance - PIMT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        .flatpickr-input { padding:10px 40px 10px 14px !important; background:rgba(255,255,255,0.06) !important; border:1.5px solid rgba(255,255,255,0.12) !important; border-radius:10px !important; color:var(--text-light) !important; font-family:'Inter',sans-serif !important; font-size:14px !important; cursor:pointer !important; min-width:170px; outline:none; }
        .flatpickr-input:focus { border-color:var(--indigo-bright) !important; box-shadow:0 0 0 3px rgba(92,107,192,0.2) !important; }
        .flatpickr-calendar { background:#131e35 !important; border:1px solid rgba(255,255,255,.1) !important; border-radius:16px !important; }
        .fp-cal-icon { position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--indigo-bright);font-size:14px;pointer-events:none; }
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
                <h1 class="page-title">Attendance <span>Report</span></h1>
                <nav class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <i class="fas fa-chevron-right"></i><span>View Attendance</span>
                </nav>
            </div>

            <!-- Filters -->
            <div class="cams-card">
                <div class="card-body" style="padding:16px 24px">
                    <form method="GET" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Course</label>
                            <select name="courseId" class="form-input" style="min-width:240px" onchange="this.form.submit()">
                                <?php $myCourses->data_seek(0); while($c = $myCourses->fetch_assoc()): ?>
                                <option value="<?= $c['Id'] ?>" <?= $filterCourse==$c['Id']?'selected':'' ?>>[<?= $c['courseCode'] ?>] <?= htmlspecialchars($c['courseName']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Subject</label>
                            <select name="subject" class="form-input" style="min-width:180px">
                                <option value="0">-- All Subjects --</option>
                                <?php if($mySubjects) while($sb=$mySubjects->fetch_assoc()): ?>
                                <option value="<?=$sb['Id']?>" <?=$filterSubject==$sb['Id']?'selected':''?>>[<?=$sb['subjectCode']?>] <?=htmlspecialchars($sb['subjectName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Roll No</label>
                            <input type="text" name="roll" class="form-input" value="<?=htmlspecialchars($rollSearch)?>" placeholder="Roll number..." style="min-width:150px">
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Date</label>
                            <div style="position:relative">
                                <input type="text" name="date" id="datePicker" class="flatpickr-input" value="<?= $filterDate ?>" readonly="readonly" placeholder="Select date...">
                                <i class="fas fa-calendar-alt fp-cal-icon"></i>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;margin-top:22px">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                            <a href="viewAttendance.php?courseId=<?= $filterCourse ?>" class="btn btn-sm" style="background:var(--glass);border:1px solid var(--glass-border)">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Table -->
            <?php if (count($summary) > 0): ?>
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-bar"></i> Student Summary</div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-size:11px;color:var(--red-alert);font-weight:600"><i class="fas fa-exclamation-triangle"></i> Red = Below 75% (mandatory)</span>
                        <button onclick="exportExcel()" class="btn btn-sm" style="background:#107c41;color:#fff;border:none;"><i class="fas fa-file-excel"></i> Excel</button>
                        <button onclick="exportPDF()" class="btn btn-sm" style="background:#d32f2f;color:#fff;border:none;"><i class="fas fa-file-pdf"></i> PDF</button>
                    </div>
                </div>
                <div class="table-wrapper" id="pdfContainer">
                    <table class="cams-table" id="summaryTable">
                        <thead><tr><th>#</th><th>Student</th><th>Roll No</th><th>Total Days</th><th>Present</th><th>Absent</th><th>Attendance %</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php $i=1; foreach ($summary as $s):
                            $pct = $s['pct'];
                            $isLow = ($pct !== null && $pct < 75);
                            $trClass = $isLow ? 'att-row-danger' : '';
                        ?>
                        <tr class="<?= $trClass ?>">
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($s['firstName'].' '.$s['lastName']) ?></strong></td>
                            <td><span class="badge badge-indigo"><?= $s['rollNumber'] ?></span></td>
                            <td><?= $s['totalDays'] ?></td>
                            <td><?= $s['presentDays'] ?? 0 ?></td>
                            <td><?= $s['totalDays'] - ($s['presentDays'] ?? 0) ?></td>
                            <td>
                                <?php if ($pct !== null): ?>
                                <span class="att-pct <?= $isLow ? 'danger' : 'safe' ?>"><?= $pct ?>%</span>
                                <?php else: ?>
                                <span class="badge badge-warning">No data</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pct === null): ?>
                                <span class="badge badge-warning">N/A</span>
                                <?php elseif ($isLow): ?>
                                <span class="badge badge-absent"><i class="fas fa-exclamation-triangle"></i> Low Risk</span>
                                <?php else: ?>
                                <span class="badge badge-present"><i class="fas fa-check"></i> Good</span>
                                <?php endif; ?>
                            </td>
                            <td><a href="studentAnalytics.php?id=<?=$s['Id']?>" class="btn btn-sm" style="background:linear-gradient(135deg,#7c3aed,#9f67fa);color:#fff"><i class="fas fa-chart-pie"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Daily Records -->
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-list"></i> Daily Records (<?= $records ? $records->num_rows : 0 ?>)</div>
                </div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>#</th><th>Date</th><th>Student</th><th>Roll No</th><th>Subject</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if ($records && $records->num_rows > 0):
                            $i=1; while($r = $records->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= date('d M Y', strtotime($r['dateTaken'])) ?></td>
                            <td><?= htmlspecialchars($r['firstName'].' '.$r['lastName']) ?></td>
                            <td><span class="badge badge-indigo"><?= $r['rollNumber'] ?></span></td>
                            <td><?= $r['subjectCode'] ? htmlspecialchars($r['subjectCode'].' - '.$r['subjectName']) : '<span style="color:var(--text-muted)">General</span>' ?></td>
                            <td>
                                <?php if ($r['status'] == 1): ?>
                                <span class="badge badge-present"><i class="fas fa-check"></i> Present</span>
                                <?php else: ?>
                                <span class="badge badge-absent"><i class="fas fa-times"></i> Absent</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6"><div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-calendar-check"></i></div>
                            <div class="empty-title">No attendance records</div>
                            <div class="empty-text">Take attendance first using the Take Attendance page.</div>
                        </div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>

flatpickr('#datePicker', {
    dateFormat: 'Y-m-d',
    disableMobile: true,
    theme: 'dark'
});

function exportExcel() {
    let table = document.getElementById('summaryTable');
    let wb = XLSX.utils.table_to_book(table, {sheet:"Attendance"});
    XLSX.writeFile(wb, "PIMT_Teacher_Attendance.xlsx");
}

function exportPDF() {
    let element = document.getElementById('pdfContainer');
    html2pdf(element, {
        margin: 10,
        filename: 'PIMT_Teacher_Attendance.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
    });
}
</script>
</body>
</html>
