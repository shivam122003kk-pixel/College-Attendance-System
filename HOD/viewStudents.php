<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'HOD') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
include '../Includes/db_helpers.php';
include '../Includes/analytics_helper.php';
$deptId = (int)$_SESSION['deptId'];
$hodId  = (int)$_SESSION['userId'];

// Filters
$filterDept    = $deptId; // HOD only sees own dept
$filterCourse  = isset($_GET['course'])  ? (int)$_GET['course']  : 0;
$filterSubject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$rollSearch    = isset($_GET['roll'])    ? trim($_GET['roll'])    : '';

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM tblstudent_course WHERE studentId=$id");
    $conn->query("DELETE FROM tblstudent WHERE Id=$id");
    $conn->query("DELETE FROM tblattendance WHERE studentId=$id");
    resequenceCollegeAttendanceIds($conn);
}

// Edit
if (isset($_POST['editStudent'])) {
    $id     = (int)$_POST['editId'];
    $fn     = trim($_POST['firstName']); $ln = trim($_POST['lastName']);
    $gender = trim($_POST['gender']); $roll = trim($_POST['rollNumber']);
    $courseIds = array_values(array_filter(array_map('intval', $_POST['courseIds'] ?? [])));
    $cid    = (int)($courseIds[0] ?? 0);
    if (!empty($_FILES['editPhoto']['name'])) {
        $ext = strtolower(pathinfo($_FILES['editPhoto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
            $fname = 'student_'.time().'.'.$ext;
            move_uploaded_file($_FILES['editPhoto']['tmp_name'], '../uploads/students/'.$fname);
            $st = $conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,gender=?,rollNumber=?,courseId=?,photo=? WHERE Id=?");
            $st->bind_param("ssssisi", $fn,$ln,$gender,$roll,$cid,$fname,$id);
        } else {
            $st = $conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,gender=?,rollNumber=?,courseId=? WHERE Id=?");
            $st->bind_param("ssssii", $fn,$ln,$gender,$roll,$cid,$id);
        }
    } else {
        $st = $conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,gender=?,rollNumber=?,courseId=? WHERE Id=?");
        $st->bind_param("ssssii", $fn,$ln,$gender,$roll,$cid,$id);
    }
    $st->execute();
    if ($courseIds) {
        $conn->query("DELETE FROM tblstudent_course WHERE studentId=$id");
        $sc = $conn->prepare("INSERT IGNORE INTO tblstudent_course (studentId,courseId,assignedByRole,assignedById) VALUES (?,?,'HOD',?)");
        foreach ($courseIds as $courseId) { $sc->bind_param("iii",$id,$courseId,$hodId); $sc->execute(); }
    }
    header("Location: viewStudents.php"); exit;
}

// Build WHERE
$wheres = ["s.deptId=$deptId"];
if ($filterCourse)  $wheres[] = "(s.courseId=$filterCourse OR sc.courseId=$filterCourse)";
if ($filterSubject) $wheres[] = "s.Id IN (SELECT studentId FROM tblstudent_subject WHERE subjectId=$filterSubject)";
if ($rollSearch)    $wheres[] = "s.rollNumber LIKE '".mysqli_real_escape_string($conn,'%'.$rollSearch.'%')."'";
$where = "WHERE ".implode(" AND ", $wheres);
$attWhere = "WHERE 1=1";
if ($filterCourse) $attWhere .= " AND courseId=$filterCourse";
if ($filterSubject) $attWhere .= " AND subjectId=$filterSubject";

$students = $conn->query("
    SELECT s.*, c.courseName, c.courseCode, d.deptName,
           COALESCE(GROUP_CONCAT(DISTINCT CONCAT(c2.courseCode,' - ',c2.courseName) ORDER BY c2.courseName SEPARATOR ', '), CONCAT(c.courseCode,' - ',c.courseName)) as courseList,
           GROUP_CONCAT(DISTINCT COALESCE(sc.courseId, s.courseId) SEPARATOR ',') as courseIds,
           COALESCE(a.totalDays,0) as totalDays,
           COALESCE(a.presentDays,0) as presentDays,
           CASE WHEN a.totalDays>0 THEN ROUND(a.presentDays/a.totalDays*100,1) ELSE NULL END as pct
    FROM tblstudent s
    JOIN tblcourse c ON s.courseId=c.Id
    LEFT JOIN tblstudent_course sc ON s.Id=sc.studentId
    LEFT JOIN tblcourse c2 ON sc.courseId=c2.Id
    LEFT JOIN tbldepartment d ON s.deptId=d.Id
    LEFT JOIN (
        SELECT studentId, COUNT(*) as totalDays, COALESCE(SUM(status),0) as presentDays
        FROM tblattendance
        $attWhere
        GROUP BY studentId
    ) a ON s.Id=a.studentId
    $where
    GROUP BY s.Id ORDER BY s.firstName
");

// Filter dropdowns
$hodCourses  = $conn->query("SELECT c.Id,c.courseName,c.courseCode FROM tblhod_course hc JOIN tblcourse c ON hc.courseId=c.Id WHERE hc.hodId=$hodId ORDER BY c.courseName");
$hodSubjects = $conn->query("SELECT sb.Id,sb.subjectName,sb.subjectCode FROM tblsubject sb WHERE sb.deptId=$deptId ORDER BY sb.subjectName");
$deptCourses = $conn->query("SELECT DISTINCT c.Id,c.courseName,c.courseCode FROM tblcourse c JOIN tblhod_course hc ON c.Id=hc.courseId WHERE hc.hodId=$hodId ORDER BY c.courseName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>View Students - PIMT HOD</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
                <h1 class="page-title"><span>View</span> Students</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Students</span></nav>
            </div>

            <!-- Advanced Filters -->
            <div class="cams-card">
                <div class="card-body" style="padding:14px 20px">
                    <form method="GET" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Course</label>
                            <select name="course" class="form-input" style="min-width:200px">
                                <option value="0">-- All Courses --</option>
                                <?php $hodCourses->data_seek(0); while($c=$hodCourses->fetch_assoc()): ?>
                                <option value="<?=$c['Id']?>" <?=$filterCourse==$c['Id']?'selected':''?>>[<?=$c['courseCode']?>] <?=htmlspecialchars($c['courseName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Subject</label>
                            <select name="subject" class="form-input" style="min-width:180px">
                                <option value="0">-- All Subjects --</option>
                                <?php $hodSubjects->data_seek(0); while($sb=$hodSubjects->fetch_assoc()): ?>
                                <option value="<?=$sb['Id']?>" <?=$filterSubject==$sb['Id']?'selected':''?>>[<?=$sb['subjectCode']?>] <?=htmlspecialchars($sb['subjectName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Roll No Search</label>
                            <input type="text" name="roll" class="form-input" value="<?=htmlspecialchars($rollSearch)?>" placeholder="e.g. CSE2024001" style="min-width:160px">
                        </div>
                        <div style="display:flex;gap:8px;margin-top:22px">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                            <a href="viewStudents.php" class="btn btn-sm" style="background:var(--glass);border:1px solid var(--glass-border)">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Roll Search Single Record -->
            <?php if ($rollSearch && $students && $students->num_rows == 1):
                $sr = $students->fetch_assoc(); $students->data_seek(0);
                $subjAtt = $conn->query("
                    SELECT sb.subjectName, sb.subjectCode,
                           COUNT(a.Id) as totalDays, SUM(a.status) as presentDays,
                           CASE WHEN COUNT(a.Id)>0 THEN ROUND(SUM(a.status)/COUNT(a.Id)*100,1) ELSE NULL END as pct
                    FROM tblsubject sb
                    LEFT JOIN tblattendance a ON a.subjectId=sb.Id AND a.studentId={$sr['Id']}
                    WHERE sb.courseId={$sr['courseId']}
                    GROUP BY sb.Id ORDER BY sb.subjectName
                ");
            ?>
            <div class="cams-card" style="border-color:rgba(79,99,210,0.4)">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-id-card"></i> Complete Record: <?=htmlspecialchars($sr['firstName'].' '.$sr['lastName'])?></div>
                    <a href="studentAnalytics.php?id=<?=$sr['Id']?>" class="btn btn-sm btn-primary"><i class="fas fa-chart-pie"></i> Analytics</a>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:14px">
                        <div style="background:var(--glass);padding:12px;border-radius:10px"><div style="font-size:10px;color:var(--text-muted);text-transform:uppercase">Roll No</div><div style="font-weight:700"><?=$sr['rollNumber']?></div></div>
                        <div style="background:var(--glass);padding:12px;border-radius:10px"><div style="font-size:10px;color:var(--text-muted);text-transform:uppercase">Course</div><div style="font-weight:700"><?=htmlspecialchars($sr['courseName'])?></div></div>
                        <div style="background:var(--glass);padding:12px;border-radius:10px"><div style="font-size:10px;color:var(--text-muted);text-transform:uppercase">Overall Att.</div><div style="font-weight:700;color:<?=($sr['pct']!==null&&$sr['pct']<75?'var(--red-alert)':'var(--success)')?>"><?=$sr['pct']!==null?$sr['pct'].'%':'No data'?></div></div>
                    </div>
                    <h4 style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">Subject-wise Attendance</h4>
                    <div class="table-wrapper">
                        <table class="cams-table">
                            <thead><tr><th>#</th><th>Subject</th><th>Total</th><th>Present</th><th>Absent</th><th>Att. %</th></tr></thead>
                            <tbody>
                            <?php if($subjAtt && $subjAtt->num_rows>0): $i=1; while($r=$subjAtt->fetch_assoc()): ?>
                            <tr>
                                <td><?=$i++?></td>
                                <td><?=htmlspecialchars($r['subjectName'])?> <small class="badge badge-indigo"><?=$r['subjectCode']?></small></td>
                                <td><?=$r['totalDays']?></td><td><?=$r['presentDays']??0?></td>
                                <td><?=$r['totalDays']-($r['presentDays']??0)?></td>
                                <td><?=$r['pct']!==null?'<span class="att-pct '.($r['pct']<75?'danger':'safe').'">'.$r['pct'].'%</span>':'<span class="badge badge-warning">No data</span>'?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No subject data</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Students Table -->
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-user-graduate"></i> Students (<?=$students?$students->num_rows:0?>)</div>
                    <a href="manageStudents.php" class="btn btn-sm btn-success"><i class="fas fa-user-plus"></i> Add Student</a>
                </div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>#</th><th>Photo</th><th>Name</th><th>Gender</th><th>Roll No</th><th>Course</th><th>Total Days</th><th>Present</th><th>Att. %</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if($students && $students->num_rows>0): $i=1; $students->data_seek(0); while($s=$students->fetch_assoc()):
                            $pct=$s['pct']; $isLow=($pct!==null&&$pct<75); $gColor=$s['gender']==='Female'?'#f472b6':($s['gender']==='Other'?'var(--gold)':'#60a5fa'); ?>
                        <tr class="<?=$isLow?'att-row-danger':''?>">
                            <td><?=$i++?></td>
                            <td><?php if(!empty($s['photo'])): ?><img src="../uploads/students/<?=htmlspecialchars($s['photo'])?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover"><?php else: ?><div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--indigo),var(--teal));display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:13px"><?=strtoupper(substr($s['firstName'],0,1).substr($s['lastName'],0,1))?></div><?php endif; ?></td>
                            <td><strong><?=htmlspecialchars($s['firstName'].' '.$s['lastName'])?></strong></td>
                            <td><span style="font-size:12px;font-weight:600;color:<?=$gColor?>"><?=htmlspecialchars($s['gender']??'N/A')?></span></td>
                            <td><span class="badge badge-indigo"><?=$s['rollNumber']?></span></td>
                            <td><?=htmlspecialchars($s['courseList'])?></td>
                            <td><?=$s['totalDays']?></td>
                            <td><?=$s['presentDays']??0?></td>
                            <td><?=$pct!==null?'<span class="att-pct '.($isLow?'danger':'safe').'">'.$pct.'%</span>'.($isLow?'<span style="font-size:10px;color:#ef233c;margin-left:4px"><i class="fas fa-exclamation-triangle"></i></span>':''):'<span class="badge badge-warning">No data</span>'?></td>
                            <td>
                                <a href="studentAnalytics.php?id=<?=$s['Id']?>" class="btn btn-sm" style="background:linear-gradient(135deg,#7c3aed,#9f67fa);color:#fff" title="Analytics"><i class="fas fa-chart-pie"></i></a>
                                <a href="#" class="btn btn-sm btn-primary" onclick="openEditS(<?=$s['Id']?>,  '<?=addslashes($s['firstName'])?>','<?=addslashes($s['lastName'])?>','<?=addslashes($s['gender']??'Male')?>','<?=addslashes($s['rollNumber'])?>','<?=addslashes($s['courseIds']?:$s['courseId'])?>')"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?=$s['Id']?>&course=<?=$filterCourse?>&subject=<?=$filterSubject?>" class="btn btn-sm btn-danger" onclick="return PIMTAlert.confirmLink(this, 'Remove?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="10"><div class="empty-state"><div class="empty-icon"><i class="fas fa-user-graduate"></i></div><div class="empty-title">No students found</div></div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>

<!-- Edit Student Modal -->
<div id="editSModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#111f38;border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:32px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;position:relative;">
        <button onclick="closeEditS()" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text-muted);font-size:20px;cursor:pointer;"><i class="fas fa-times"></i></button>
        <h3 style="font-family:'Outfit',sans-serif;font-size:20px;font-weight:700;margin-bottom:20px;"><i class="fas fa-user-edit" style="color:var(--indigo-bright);margin-right:8px;"></i> Edit Student</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="editStudent" value="1">
            <input type="hidden" name="editId" id="editSId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div><label class="form-label">First Name</label><input type="text" name="firstName" id="editSFN" class="form-input" required></div>
                <div><label class="form-label">Last Name</label><input type="text" name="lastName" id="editSLN" class="form-input" required></div>
                <div><label class="form-label">Roll Number</label><input type="text" name="rollNumber" id="editSRoll" class="form-input" required></div>
                <div><label class="form-label">Gender</label><select name="gender" id="editSGender" class="form-input"><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div>
                <div style="grid-column:1/-1"><label class="form-label">Course(s)</label><select name="courseIds[]" id="editSCourse" class="form-input" multiple size="5">
                    <?php $deptCourses->data_seek(0); while($c=$deptCourses->fetch_assoc()): ?>
                    <option value="<?=$c['Id']?>">[<?=$c['courseCode']?>] <?=htmlspecialchars($c['courseName'])?></option>
                    <?php endwhile; ?>
                </select></div>
                <div style="grid-column:1/-1"><label class="form-label">New Photo (optional)</label><input type="file" name="editPhoto" class="form-input" accept="image/*"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:20px;width:100%"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>
<script>
function openEditS(id,fn,ln,gender,roll,courseIds){
    document.getElementById('editSId').value=id; document.getElementById('editSFN').value=fn; document.getElementById('editSLN').value=ln;
    document.getElementById('editSGender').value=gender; document.getElementById('editSRoll').value=roll;
    const selected=String(courseIds||'').split(',');
    Array.from(document.getElementById('editSCourse').options).forEach(o=>o.selected=selected.includes(o.value));
    document.getElementById('editSModal').style.display='flex';
}
function closeEditS(){document.getElementById('editSModal').style.display='none';}
document.getElementById('editSModal').addEventListener('click',function(e){if(e.target===this)closeEditS();});
</script>
</body>
</html>
