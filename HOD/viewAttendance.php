<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'HOD') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
$deptId = (int)$_SESSION['deptId'];
$hodId  = (int)$_SESSION['userId'];

$filterCourse  = isset($_GET['course'])  ? (int)$_GET['course']  : 0;
$filterSubject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$filterDate    = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : '';
$rollSearch    = isset($_GET['roll'])    ? trim($_GET['roll'])    : '';

$where = "WHERE (s.deptId=$deptId OR a.courseId IN (SELECT courseId FROM tblhod_course WHERE hodId=$hodId))";
if ($filterCourse)  $where .= " AND a.courseId=$filterCourse";
if ($filterSubject) $where .= " AND a.subjectId=$filterSubject";
if ($filterDate)    $where .= " AND a.dateTaken='".mysqli_real_escape_string($conn,$filterDate)."'";
if ($rollSearch)    $where .= " AND s.rollNumber LIKE '".mysqli_real_escape_string($conn,'%'.$rollSearch.'%')."'";

$summary = $conn->query("
    SELECT s.Id, s.firstName,s.lastName,s.rollNumber,s.photo,s.gender,
           c.courseName,c.courseCode,
           sb.subjectName, sb.subjectCode,
           COUNT(a.Id) as tot, SUM(a.status) as pres,
           CASE WHEN COUNT(a.Id)>0 THEN ROUND(SUM(a.status)/COUNT(a.Id)*100,1) ELSE NULL END as pct
    FROM tblattendance a
    JOIN tblstudent s ON a.studentId=s.Id
    JOIN tblcourse c ON a.courseId=c.Id
    LEFT JOIN tblsubject sb ON a.subjectId=sb.Id
    $where
    GROUP BY s.Id, c.Id, a.subjectId ORDER BY pct ASC
");

$hodCourses  = $conn->query("SELECT c.Id,c.courseName,c.courseCode FROM tblhod_course hc JOIN tblcourse c ON hc.courseId=c.Id WHERE hc.hodId=$hodId ORDER BY c.courseName");
$hodSubjects = $conn->query("SELECT sb.Id,sb.subjectName,sb.subjectCode FROM tblsubject sb WHERE sb.deptId=$deptId ORDER BY sb.subjectName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Attendance Report - PIMT HOD</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <style>.flatpickr-input{padding:10px 14px !important;background:rgba(255,255,255,0.06) !important;border:1.5px solid rgba(255,255,255,0.12) !important;border-radius:10px !important;color:var(--text-light) !important;font-size:14px !important;cursor:pointer !important;min-width:150px;}</style>
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
                <h1 class="page-title">Attendance <span>Report</span></h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Attendance</span></nav>
            </div>

            <!-- Advanced Filters -->
            <div class="cams-card">
                <div class="card-body" style="padding:14px 20px">
                    <form method="GET" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Course</label>
                            <select name="course" class="form-input" style="min-width:200px">
                                <option value="0">-- All Courses --</option>
                                <?php while($c=$hodCourses->fetch_assoc()): ?>
                                <option value="<?=$c['Id']?>" <?=$filterCourse==$c['Id']?'selected':''?>>[<?=$c['courseCode']?>] <?=htmlspecialchars($c['courseName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Subject</label>
                            <select name="subject" class="form-input" style="min-width:180px">
                                <option value="0">-- All Subjects --</option>
                                <?php while($sb=$hodSubjects->fetch_assoc()): ?>
                                <option value="<?=$sb['Id']?>" <?=$filterSubject==$sb['Id']?'selected':''?>>[<?=$sb['subjectCode']?>] <?=htmlspecialchars($sb['subjectName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Date</label>
                            <input type="text" name="date" id="datePicker" class="flatpickr-input" value="<?=$filterDate?>" readonly="readonly" placeholder="Select date...">
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Roll No</label>
                            <input type="text" name="roll" class="form-input" value="<?=htmlspecialchars($rollSearch)?>" placeholder="Roll number..." style="min-width:150px">
                        </div>
                        <div style="display:flex;gap:8px;margin-top:22px">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                            <a href="viewAttendance.php" class="btn btn-sm" style="background:var(--glass);border:1px solid var(--glass-border)">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-bar"></i> Student Attendance Summary</div>
                    <div style="display:flex;gap:10px">
                        <button onclick="exportExcel()" class="btn btn-sm" style="background:#107c41;color:#fff"><i class="fas fa-file-excel"></i> Excel</button>
                        <button onclick="exportPDF()" class="btn btn-sm" style="background:#d32f2f;color:#fff"><i class="fas fa-file-pdf"></i> PDF</button>
                    </div>
                </div>
                <div class="table-wrapper" id="pdfContainer">
                    <table class="cams-table" id="summaryTable">
                        <thead><tr><th>#</th><th>Photo</th><th>Student</th><th>Roll No</th><th>Course</th><th>Subject</th><th>Present</th><th>Total</th><th>Attendance %</th><th>Analytics</th></tr></thead>
                        <tbody>
                        <?php if($summary && $summary->num_rows>0): $i=1; while($r=$summary->fetch_assoc()): $isLow=($r['pct']!==null&&$r['pct']<75); ?>
                        <tr class="<?=$isLow?'att-row-danger':''?>">
                            <td><?=$i++?></td>
                            <td><?php if(!empty($r['photo'])): ?><img src="../uploads/students/<?=htmlspecialchars($r['photo'])?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover"><?php else: ?><div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--indigo),var(--teal));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff"><?=strtoupper(substr($r['firstName'],0,1).substr($r['lastName'],0,1))?></div><?php endif; ?></td>
                            <td><strong><?=htmlspecialchars($r['firstName'].' '.$r['lastName'])?></strong></td>
                            <td><span class="badge badge-indigo"><?=$r['rollNumber']?></span></td>
                            <td><span class="badge badge-info"><?=$r['courseCode']?></span></td>
                            <td><?=$r['subjectCode']?htmlspecialchars($r['subjectCode']):'<span style="color:var(--text-muted)">-</span>'?></td>
                            <td><?=$r['pres']??0?></td>
                            <td><?=$r['tot']?></td>
                            <td><?=$r['pct']!==null?'<span class="att-pct '.($isLow?'danger':'safe').'">'.$r['pct'].'%</span>':'<span class="badge badge-warning">No data</span>'?></td>
                            <td><a href="studentAnalytics.php?id=<?=$r['Id']?>" class="btn btn-sm" style="background:linear-gradient(135deg,#7c3aed,#9f67fa);color:#fff"><i class="fas fa-chart-pie"></i></a></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="10"><div class="empty-state"><div class="empty-icon"><i class="fas fa-calendar-check"></i></div><div class="empty-title">No data yet</div></div></td></tr>
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
flatpickr('#datePicker',{dateFormat:'Y-m-d',disableMobile:true});
function exportExcel(){XLSX.writeFile(XLSX.utils.table_to_book(document.getElementById('summaryTable'),{sheet:"Attendance"}),'PIMT_HOD_Attendance.xlsx');}
function exportPDF(){html2pdf(document.getElementById('pdfContainer'),{margin:10,filename:'PIMT_HOD_Attendance.pdf',image:{type:'jpeg',quality:0.98},html2canvas:{scale:2},jsPDF:{unit:'mm',format:'a4',orientation:'landscape'}});}
</script>
</body></html>
