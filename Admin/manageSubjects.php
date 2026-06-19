<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Director') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
include '../Includes/db_helpers.php';

$directorId = (int)$_SESSION['userId'];
$msg = ''; $msgType = '';

if (isset($_POST['addSubject']) || isset($_POST['editSubject'])) {
    $id = isset($_POST['editId']) ? (int)$_POST['editId'] : 0;
    $name = trim($_POST['subjectName']);
    $code = strtoupper(trim($_POST['subjectCode']));
    $courseId = (int)$_POST['courseId'];
    $deptId = (int)$_POST['deptId'];
    $semester = trim($_POST['semester']);
    $duration = trim($_POST['duration']);
    $desc = trim($_POST['description']);

    $dup = $conn->prepare("SELECT Id FROM tblsubject WHERE subjectCode=? AND courseId=? AND Id<>? LIMIT 1");
    $dup->bind_param("sii", $code, $courseId, $id);
    $dup->execute();

    if ($dup->get_result()->num_rows > 0) {
        $msg = "Subject code already exists for this course."; $msgType = 'danger';
    } elseif (isset($_POST['editSubject'])) {
        try {
            $st = $conn->prepare("UPDATE tblsubject SET subjectName=?, subjectCode=?, courseId=?, deptId=?, semester=?, duration=?, description=? WHERE Id=?");
            $st->bind_param("ssiisssi", $name, $code, $courseId, $deptId, $semester, $duration, $desc, $id);
            $st->execute();
            $msg = "Subject updated."; $msgType = 'success';
        } catch (mysqli_sql_exception $e) {
            $msg = $e->getCode() == 1062 ? "Subject code already exists for this course." : "Unable to update subject.";
            $msgType = 'danger';
        }
    } else {
        try {
            $st = $conn->prepare("INSERT INTO tblsubject (subjectName, subjectCode, courseId, deptId, semester, duration, description) VALUES (?,?,?,?,?,?,?)");
            $st->bind_param("ssiisss", $name, $code, $courseId, $deptId, $semester, $duration, $desc);
            $st->execute();
            $msg = "Subject added."; $msgType = 'success';
        } catch (mysqli_sql_exception $e) {
            $msg = $e->getCode() == 1062 ? "Subject code already exists for this course." : "Unable to add subject.";
            $msgType = 'danger';
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM tblhod_subject WHERE subjectId=$id");
    $conn->query("DELETE FROM tblteacher_subject WHERE subjectId=$id");
    $conn->query("DELETE FROM tblstudent_subject WHERE subjectId=$id");
    $conn->query("DELETE FROM tblsubject WHERE Id=$id");
    resequenceCollegeAttendanceIds($conn);
    $msg = "Subject removed."; $msgType = 'warning';
}

if (isset($_GET['assign']) && isset($_GET['hod'])) {
    $subjectId = (int)$_GET['assign'];
    $hodId = (int)$_GET['hod'];
    $st = $conn->prepare("INSERT IGNORE INTO tblhod_subject (hodId, subjectId, assignedByDirectorId) VALUES (?,?,?)");
    $st->bind_param("iii", $hodId, $subjectId, $directorId);
    $st->execute();
    $msg = $st->affected_rows ? "Subject assigned to HOD." : "Subject was already assigned to that HOD.";
    $msgType = $st->affected_rows ? 'success' : 'warning';
}

$courses = $conn->query("SELECT Id, courseName, courseCode FROM tblcourse ORDER BY courseName");
$depts = $conn->query("SELECT Id, deptName, deptCode FROM tbldepartment ORDER BY deptName");
$hods = $conn->query("SELECT h.Id, h.firstName, h.lastName, h.deptId, d.deptName FROM tblhod h JOIN tbldepartment d ON h.deptId=d.Id ORDER BY d.deptName, h.firstName");
$subjects = $conn->query("
    SELECT s.*, c.courseName, c.courseCode, d.deptName, d.deptCode,
           GROUP_CONCAT(DISTINCT CONCAT(h.firstName, ' ', h.lastName) ORDER BY h.firstName SEPARATOR ', ') as hodNames
    FROM tblsubject s
    JOIN tblcourse c ON s.courseId=c.Id
    JOIN tbldepartment d ON s.deptId=d.Id
    LEFT JOIN tblhod_subject hs ON s.Id=hs.subjectId
    LEFT JOIN tblhod h ON hs.hodId=h.Id
    GROUP BY s.Id
    ORDER BY d.deptName, c.courseName, s.subjectName
");
$hodOptions = [];
while ($h = $hods->fetch_assoc()) $hodOptions[] = $h;
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Manage Subjects - PIMT Director</title>
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <script src="../js/pimt-alerts.js"></script>
    <script src="../js/pimt-actions.js"></script>
</head><body>
<div id="wrapper">
<?php include 'Includes/sidebar.php'; ?>
<div id="content-wrapper">
<?php include 'Includes/topbar.php'; ?>
<div id="page-content" class="fade-in">
    <div class="page-header">
        <h1 class="page-title"><span>Manage</span> Subjects</h1>
        <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Subjects</span></nav>
    </div>
    <?php if($msg): ?><div class="alert alert-<?=$msgType?>"><i class="fas fa-info-circle"></i> <?=htmlspecialchars($msg)?></div><?php endif; ?>

    <div class="cams-card">
        <div class="card-header"><div class="card-title"><i class="fas fa-book"></i> Add Subject</div></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Subject Name *</label><input name="subjectName" class="form-input" required></div>
                    <div class="form-group"><label class="form-label">Subject Code *</label><input name="subjectCode" class="form-input" required></div>
                    <div class="form-group"><label class="form-label">Course *</label><select name="courseId" class="form-input" required><?php while($c=$courses->fetch_assoc()): ?><option value="<?=$c['Id']?>">[<?=htmlspecialchars($c['courseCode'])?>] <?=htmlspecialchars($c['courseName'])?></option><?php endwhile; ?></select></div>
                    <div class="form-group"><label class="form-label">Department *</label><select name="deptId" class="form-input" required><?php while($d=$depts->fetch_assoc()): ?><option value="<?=$d['Id']?>"><?=htmlspecialchars($d['deptName'])?> (<?=htmlspecialchars($d['deptCode'])?>)</option><?php endwhile; ?></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Semester</label><input name="semester" class="form-input" placeholder="e.g. Semester 1"></div>
                    <div class="form-group"><label class="form-label">Duration</label><input name="duration" class="form-input" placeholder="e.g. 6 Months"></div>
                    <div class="form-group" style="grid-column:span 2"><label class="form-label">Description</label><input name="description" class="form-input"></div>
                </div>
                <button name="addSubject" class="btn btn-primary"><i class="fas fa-plus"></i> Add Subject</button>
            </form>
        </div>
    </div>

    <div class="cams-card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-list"></i> Subjects (<?=$subjects->num_rows?>)</div>
            <div class="table-tools"><div class="search-box"><i class="fas fa-search"></i><input class="form-input" data-table-search="#subjectsTable" placeholder="Search subject, course, department..."></div></div>
        </div>
        <div class="table-wrapper scroll-y">
            <table class="cams-table" id="subjectsTable">
                <thead><tr><th>#</th><th>Subject</th><th>Course</th><th>Department</th><th>Semester</th><th>HOD</th><th>Assign</th><th>Action</th></tr></thead>
                <tbody>
                <?php if($subjects->num_rows): $i=1; while($s=$subjects->fetch_assoc()): ?>
                <tr>
                    <td><?=$i++?></td>
                    <td><strong><?=htmlspecialchars($s['subjectName'])?></strong><br><span class="badge badge-indigo"><?=htmlspecialchars($s['subjectCode'])?></span></td>
                    <td><?=htmlspecialchars($s['courseName'])?> <span class="badge badge-info"><?=htmlspecialchars($s['courseCode'])?></span></td>
                    <td><?=htmlspecialchars($s['deptName'])?></td>
                    <td><?=htmlspecialchars($s['semester'] ?: '-')?><br><small style="color:var(--text-muted)"><?=htmlspecialchars($s['duration'] ?: '')?></small></td>
                    <td><?=htmlspecialchars($s['hodNames'] ?: 'Not assigned')?></td>
                    <td>
                        <form method="GET" style="display:flex;gap:6px">
                            <input type="hidden" name="assign" value="<?=$s['Id']?>">
                            <select name="hod" class="form-input" style="min-width:160px;padding:6px 10px">
                                <?php foreach($hodOptions as $h): if((int)$h['deptId'] !== (int)$s['deptId']) continue; ?>
                                <option value="<?=$h['Id']?>"><?=htmlspecialchars($h['firstName'].' '.$h['lastName'])?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-primary"><i class="fas fa-link"></i></button>
                        </form>
                    </td>
                    <td>
                        <a href="#" class="btn btn-sm btn-primary" onclick="openEditSubject(<?=$s['Id']?>,'<?=addslashes($s['subjectName'])?>','<?=addslashes($s['subjectCode'])?>',<?=$s['courseId']?>,<?=$s['deptId']?>,'<?=addslashes($s['semester'])?>','<?=addslashes($s['duration'])?>','<?=addslashes($s['description'])?>')"><i class="fas fa-edit"></i></a>
                        <a href="?delete=<?=$s['Id']?>" class="btn btn-sm btn-danger" onclick="return PIMTAlert.confirmLink(this, 'Delete this subject?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="8"><div class="empty-state"><div class="empty-icon"><i class="fas fa-book"></i></div><div class="empty-title">No subjects yet</div></div></td></tr>
                <?php endif; ?>
                <tr class="search-empty-row"><td colspan="8"><div class="empty-state"><div class="empty-title">No matching subjects</div></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'Includes/footer.php'; ?>
</div></div>

<div id="editSubjectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#111f38;border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:28px;width:100%;max-width:620px">
        <button onclick="closeEditSubject()" style="float:right;background:none;border:0;color:var(--text-muted);font-size:18px"><i class="fas fa-times"></i></button>
        <h3 style="margin-bottom:18px">Edit Subject</h3>
        <form method="POST">
            <input type="hidden" name="editSubject" value="1"><input type="hidden" name="editId" id="editSubjectId">
            <div class="form-row">
                <div class="form-group"><label class="form-label">Subject Name</label><input name="subjectName" id="editSubjectName" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Subject Code</label><input name="subjectCode" id="editSubjectCode" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Course</label><select name="courseId" id="editSubjectCourse" class="form-input"><?php $courses=$conn->query("SELECT Id,courseName,courseCode FROM tblcourse ORDER BY courseName"); while($c=$courses->fetch_assoc()): ?><option value="<?=$c['Id']?>">[<?=htmlspecialchars($c['courseCode'])?>] <?=htmlspecialchars($c['courseName'])?></option><?php endwhile; ?></select></div>
                <div class="form-group"><label class="form-label">Department</label><select name="deptId" id="editSubjectDept" class="form-input"><?php $depts=$conn->query("SELECT Id,deptName,deptCode FROM tbldepartment ORDER BY deptName"); while($d=$depts->fetch_assoc()): ?><option value="<?=$d['Id']?>"><?=htmlspecialchars($d['deptName'])?> (<?=htmlspecialchars($d['deptCode'])?>)</option><?php endwhile; ?></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Semester</label><input name="semester" id="editSubjectSemester" class="form-input"></div>
                <div class="form-group"><label class="form-label">Duration</label><input name="duration" id="editSubjectDuration" class="form-input"></div>
                <div class="form-group" style="grid-column:1/-1"><label class="form-label">Description</label><input name="description" id="editSubjectDescription" class="form-input"></div>
            </div>
            <button class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>
<script src="../js/cams-search.js"></script>
<script>
function openEditSubject(id,name,code,course,dept,semester,duration,description){
    document.getElementById('editSubjectId').value=id;
    document.getElementById('editSubjectName').value=name;
    document.getElementById('editSubjectCode').value=code;
    document.getElementById('editSubjectCourse').value=course;
    document.getElementById('editSubjectDept').value=dept;
    document.getElementById('editSubjectSemester').value=semester;
    document.getElementById('editSubjectDuration').value=duration;
    document.getElementById('editSubjectDescription').value=description;
    document.getElementById('editSubjectModal').style.display='flex';
}
function closeEditSubject(){document.getElementById('editSubjectModal').style.display='none';}
</script>
</body></html>
