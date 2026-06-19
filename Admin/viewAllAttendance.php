<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Director') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';

$filterDept    = isset($_GET['dept'])    ? (int)$_GET['dept']    : 0;
$filterCourse  = isset($_GET['course'])  ? (int)$_GET['course']  : 0;
$filterDate    = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : '';
$filterSubject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$rollSearch    = isset($_GET['roll'])    ? trim($_GET['roll'])    : '';

$where = "WHERE 1=1";
if ($filterDept)    $where .= " AND s.deptId = $filterDept";
if ($filterCourse)  $where .= " AND a.courseId = $filterCourse";
if ($filterSubject) $where .= " AND a.subjectId = $filterSubject";
if ($filterDate)    $where .= " AND a.dateTaken = '".mysqli_real_escape_string($conn,$filterDate)."'";
if ($rollSearch)    $where .= " AND s.rollNumber LIKE '".mysqli_real_escape_string($conn,'%'.$rollSearch.'%')."'";

$records = $conn->query("
    SELECT a.*, s.firstName, s.lastName, s.rollNumber, s.deptId,
           c.courseName, c.courseCode,
           COALESCE(t.firstName,h.firstName) as tFn, COALESCE(t.lastName,h.lastName) as tLn,
           d.deptName,
           sb.subjectName, sb.subjectCode
    FROM tblattendance a
    JOIN tblstudent s ON a.studentId=s.Id
    JOIN tblcourse c ON a.courseId=c.Id
    LEFT JOIN tblteacher t ON a.takenByTeacherId=t.Id
    LEFT JOIN tblhod h ON a.takenByTeacherId=h.Id
    LEFT JOIN tbldepartment d ON s.deptId=d.Id
    LEFT JOIN tblsubject sb ON a.subjectId=sb.Id
    $where
    ORDER BY a.dateTaken DESC, c.courseName, s.firstName
    LIMIT 500
");

$summaryWhere = preg_replace('/s\.deptId/', 'std.deptId', $where);
$summaryWhere = preg_replace('/s\.rollNumber/', 'std.rollNumber', $summaryWhere);
$summary = $conn->query("
    SELECT std.firstName, std.lastName, std.rollNumber, c.courseName, c.courseCode, d.deptName,
           sb.subjectName, sb.subjectCode,
           COUNT(a.Id) as totalDays, SUM(a.status) as presentDays,
           ROUND(SUM(a.status)/COUNT(a.Id)*100,1) as pct
    FROM tblattendance a
    JOIN tblstudent std ON a.studentId=std.Id
    JOIN tblcourse c ON a.courseId=c.Id
    LEFT JOIN tbldepartment d ON std.deptId=d.Id
    LEFT JOIN tblsubject sb ON a.subjectId=sb.Id
    $summaryWhere
    GROUP BY a.studentId, a.courseId, a.subjectId
    ORDER BY pct ASC
");

$depts    = $conn->query("SELECT * FROM tbldepartment ORDER BY deptName");
$courses  = $conn->query("SELECT * FROM tblcourse ORDER BY courseName");
$subjects = $conn->query("SELECT sb.*, c.courseCode FROM tblsubject sb JOIN tblcourse c ON sb.courseId=c.Id ORDER BY sb.subjectName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Attendance Report - PIMT Director</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <style>
        .flatpickr-input{padding:10px 40px 10px 14px !important;background:rgba(255,255,255,0.06) !important;border:1.5px solid rgba(255,255,255,0.12) !important;border-radius:10px !important;color:var(--text-light) !important;font-size:14px !important;cursor:pointer !important;min-width:160px;outline:none;}
        .fp-cal-icon{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--indigo-bright);font-size:14px;pointer-events:none;}
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
                <h1 class="page-title"><span>Attendance</span> Report</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Attendance</span></nav>
            </div>

            <!-- Advanced Filters -->
            <div class="cams-card">
                <div class="card-body" style="padding:16px 24px">
                    <form method="GET" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Department</label>
                            <select name="dept" class="form-input" style="min-width:160px">
                                <option value="0">-- All Depts --</option>
                                <?php while($d=$depts->fetch_assoc()): ?>
                                <option value="<?=$d['Id']?>" <?=$filterDept==$d['Id']?'selected':''?>><?=htmlspecialchars($d['deptName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Course</label>
                            <select name="course" class="form-input" style="min-width:200px">
                                <option value="0">-- All Courses --</option>
                                <?php while($c=$courses->fetch_assoc()): ?>
                                <option value="<?=$c['Id']?>" <?=$filterCourse==$c['Id']?'selected':''?>>[<?=$c['courseCode']?>] <?=htmlspecialchars($c['courseName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Subject</label>
                            <select name="subject" class="form-input" style="min-width:180px">
                                <option value="0">-- All Subjects --</option>
                                <?php while($sb=$subjects->fetch_assoc()): ?>
                                <option value="<?=$sb['Id']?>" <?=$filterSubject==$sb['Id']?'selected':''?>>[<?=$sb['courseCode']?>] <?=htmlspecialchars($sb['subjectName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Date</label>
                            <div style="position:relative">
                                <input type="text" name="date" id="datePicker" class="flatpickr-input" value="<?=$filterDate?>" readonly="readonly" placeholder="Select date...">
                                <i class="fas fa-calendar-alt fp-cal-icon"></i>
                            </div>
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Roll No Search</label>
                            <input type="text" name="roll" class="form-input" value="<?=htmlspecialchars($rollSearch)?>" placeholder="e.g. CSE2024001" style="min-width:160px">
                        </div>
                        <div style="display:flex;gap:8px;margin-top:22px">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                            <a href="viewAllAttendance.php" class="btn btn-sm" style="background:var(--glass);border:1px solid var(--glass-border)">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Table -->
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-bar"></i> Student Attendance Summary</div>
                    <div style="display:flex;gap:10px">
                        <span style="font-size:12px;color:var(--text-muted);align-self:center">75% minimum</span>
                        <button onclick="exportExcel()" class="btn btn-sm" style="background:#107c41;color:#fff"><i class="fas fa-file-excel"></i> Excel</button>
                        <button onclick="exportPDF()" class="btn btn-sm" style="background:#d32f2f;color:#fff"><i class="fas fa-file-pdf"></i> PDF</button>
                    </div>
                </div>
                <div class="table-wrapper" id="pdfContainer">
                    <table class="cams-table" id="summaryTable">
                        <thead><tr><th>#</th><th>Student</th><th>Roll No</th><th>Department</th><th>Course</th><th>Subject</th><th>Total Days</th><th>Present</th><th>Absent</th><th>Attendance %</th><th>Analytics</th></tr></thead>
                        <tbody>
                        <?php if ($summary && $summary->num_rows > 0):
                            $i=1; while($r=$summary->fetch_assoc()): $isLow=($r['pct']<75); ?>
                        <tr class="<?=$isLow?'att-row-danger':''?>">
                            <td><?=$i++?></td>
                            <td><strong><?=htmlspecialchars($r['firstName'].' '.$r['lastName'])?></strong></td>
                            <td><span class="badge badge-indigo"><?=$r['rollNumber']?></span></td>
                            <td><?=htmlspecialchars($r['deptName']??'N/A')?></td>
                            <td><span class="badge badge-info"><?=$r['courseCode']?></span> <?=htmlspecialchars($r['courseName'])?></td>
                            <td><?=$r['subjectCode']?htmlspecialchars($r['subjectCode'].' - '.$r['subjectName']):'All subjects'?></td>
                            <td><?=$r['totalDays']?></td>
                            <td><?=$r['presentDays']?></td>
                            <td><?=$r['totalDays']-$r['presentDays']?></td>
                            <td><span class="att-pct <?=$isLow?'danger':'safe'?>"><?=$r['pct']?>%</span><?php if($isLow): ?><small style="color:#ef233c;margin-left:4px"><i class="fas fa-exclamation-triangle"></i></small><?php endif; ?></td>
                            <td><a href="studentAnalytics.php?roll=<?=urlencode($r['rollNumber'])?>" class="btn btn-sm" style="background:linear-gradient(135deg,#7c3aed,#9f67fa);color:#fff"><i class="fas fa-chart-pie"></i></a></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="11"><div class="empty-state"><div class="empty-icon"><i class="fas fa-calendar-check"></i></div><div class="empty-title">No attendance records yet</div></div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Daily Records -->
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-list"></i> Daily Records (<?= $records ? $records->num_rows : 0 ?>)</div></div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>#</th><th>Date</th><th>Student</th><th>Department</th><th>Course</th><th>Subject</th><th>Status</th><th>Taken By</th></tr></thead>
                        <tbody>
                        <?php if($records && $records->num_rows > 0): $i=1; while($r=$records->fetch_assoc()): ?>
                        <tr>
                            <td><?=$i++?></td>
                            <td><?=date('d M Y', strtotime($r['dateTaken']))?></td>
                            <td><?=htmlspecialchars($r['firstName'].' '.$r['lastName'])?> <span style="font-size:11px;color:var(--text-muted)">(<?=$r['rollNumber']?>)</span></td>
                            <td><?=htmlspecialchars($r['deptName']??'N/A')?></td>
                            <td><span class="badge badge-indigo"><?=$r['courseCode']?></span></td>
                            <td><?=$r['subjectCode']?htmlspecialchars($r['subjectCode']):'<span style="color:var(--text-muted)">-</span>'?></td>
                            <td><?=$r['status']==1?'<span class="badge badge-present"><i class="fas fa-check"></i> Present</span>':'<span class="badge badge-absent"><i class="fas fa-times"></i> Absent</span>'?></td>
                            <td><?=htmlspecialchars($r['tFn'].' '.$r['tLn'])?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px">No records found.</td></tr>
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
flatpickr('#datePicker', { dateFormat:'Y-m-d', disableMobile:true });
function exportExcel() {
    let wb = XLSX.utils.table_to_book(document.getElementById('summaryTable'), {sheet:"Attendance"});
    XLSX.writeFile(wb, 'PIMT_Attendance_Summary.xlsx');
}
function exportPDF() {
    html2pdf(document.getElementById('pdfContainer'), { margin:10, filename:'PIMT_Attendance_Summary.pdf', image:{type:'jpeg',quality:0.98}, html2canvas:{scale:2}, jsPDF:{unit:'mm',format:'a4',orientation:'landscape'} });
}
</script>
</body>
</html>
