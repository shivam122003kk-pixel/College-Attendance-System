<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'HOD') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
include '../Includes/db_helpers.php';
$deptId = (int)$_SESSION['deptId'];
$hodId = (int)$_SESSION['userId'];
$msg=''; $msgType='';

// Courses assigned by Director to this HOD.
$deptCourses = $conn->query("SELECT DISTINCT c.Id,c.courseName,c.courseCode FROM tblcourse c JOIN tblhod_course hc ON c.Id=hc.courseId WHERE hc.hodId=$hodId ORDER BY c.courseName");

// Add student
if (isset($_POST['addStudent'])) {
    $fn=$_POST['firstName']; $ln=$_POST['lastName']; $gender=$_POST['gender'];
    $roll=strtoupper(trim($_POST['rollNumber'])); $pass=$_POST['password']; $courseIds=array_values(array_filter(array_map('intval', $_POST['courseIds'] ?? []))); $cid=(int)($courseIds[0] ?? 0); $sdeptId=(int)$_POST['deptId'];
    $photo=null;
    if (!empty($_FILES['photo']['name'])) {
        $ext=strtolower(pathinfo($_FILES['photo']['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png'])) {
            $fname='student_'.time().'.'.$ext;
            move_uploaded_file($_FILES['photo']['tmp_name'],'../uploads/students/'.$fname);
            $photo=$fname;
        }
    }
    $ck=$conn->prepare("SELECT Id FROM tblstudent WHERE rollNumber=?"); $ck->bind_param("s",$roll); $ck->execute();
    if (!$courseIds) { $msg="Select at least one course."; $msgType='warning'; }
    elseif ($ck->get_result()->num_rows>0) { $msg="Roll number already exists!"; $msgType='danger'; }
    else {
        $st=$conn->prepare("INSERT INTO tblstudent (firstName,lastName,gender,rollNumber,password,courseId,deptId,photo) VALUES(?,?,?,?,?,?,?,?)");
        $st->bind_param("sssssiis",$fn,$ln,$gender,$roll,$pass,$cid,$sdeptId,$photo);
        $st->execute();
        $studentId = $conn->insert_id;
        $sc = $conn->prepare("INSERT IGNORE INTO tblstudent_course (studentId, courseId, assignedByRole, assignedById) SELECT ?, ?, 'HOD', ? FROM tblhod_course WHERE hodId=? AND courseId=? LIMIT 1");
        foreach ($courseIds as $courseId) {
            $sc->bind_param("iiiii", $studentId, $courseId, $hodId, $hodId, $courseId);
            $sc->execute();
        }
        $msg="Student added! Login: $roll / $pass"; $msgType='success';
    }
}

if (isset($_POST['assignExistingStudents'])) {
    $assignCourseId = (int)($_POST['assignCourseId'] ?? 0);
    $subjectIds = array_values(array_filter(array_map('intval', $_POST['assignSubjectIds'] ?? [])));
    $studentIds = array_values(array_filter(array_map('intval', $_POST['assignStudentIds'] ?? [])));
    $semester = trim($_POST['assignSemester'] ?? '');
    $duration = trim($_POST['assignDuration'] ?? '');

    if (!$assignCourseId) { $msg = "Select a course first."; $msgType = 'warning'; }
    elseif (!$subjectIds) { $msg = "Select at least one subject."; $msgType = 'warning'; }
    elseif (!$studentIds) { $msg = "Select at least one student."; $msgType = 'warning'; }
    else {
        $courseOk = $conn->query("SELECT Id FROM tblhod_course WHERE hodId=$hodId AND courseId=$assignCourseId")->num_rows > 0;
        if (!$courseOk) {
            $msg = "You can assign students only inside courses assigned to you."; $msgType = 'danger';
        } else {
            $added = 0;
            $ss = $conn->prepare("
                INSERT IGNORE INTO tblstudent_subject (studentId,subjectId,semester,duration,assignedByRole,assignedById)
                SELECT st.Id, sub.Id, COALESCE(NULLIF(?,''), sub.semester), COALESCE(NULLIF(?,''), sub.duration), 'HOD', ?
                FROM tblstudent st
                JOIN tblhod_course hc ON hc.hodId=? AND hc.courseId=?
                JOIN tblhod_subject hs ON hs.hodId=hc.hodId AND hs.subjectId=?
                JOIN tblsubject sub ON sub.Id=hs.subjectId AND sub.courseId=hc.courseId AND sub.deptId=?
                LEFT JOIN tblstudent_course sc ON sc.studentId=st.Id AND sc.courseId=hc.courseId
                WHERE st.Id=? AND (st.courseId=hc.courseId OR sc.Id IS NOT NULL)
                LIMIT 1
            ");
            foreach ($subjectIds as $subjectId) {
                foreach ($studentIds as $studentId) {
                    $ss->bind_param("ssiiiiii", $semester, $duration, $hodId, $hodId, $assignCourseId, $subjectId, $deptId, $studentId);
                    $ss->execute();
                    $added += $ss->affected_rows > 0 ? 1 : 0;
                }
            }
            $msg = $added ? "$added subject assignment(s) added for existing course students." : "Selected students were already assigned, or subject/course did not match.";
            $msgType = $added ? 'success' : 'warning';
        }
    }
}

if (isset($_GET['delete'])) {
    $id=(int)$_GET['delete'];
    $conn->query("DELETE FROM tblstudent_course WHERE studentId=$id");
    $conn->query("DELETE FROM tblstudent WHERE Id=$id");
    $conn->query("DELETE FROM tblattendance WHERE studentId=$id");
    resequenceCollegeAttendanceIds($conn);
    $msg="Student removed."; $msgType='warning';
}

// Edit student (dept-scoped)
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
            $st->bind_param("ssssisi", $fn, $ln, $gender, $roll, $cid, $fname, $id);
        } else {
            $st = $conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,gender=?,rollNumber=?,courseId=? WHERE Id=?");
            $st->bind_param("ssssii", $fn, $ln, $gender, $roll, $cid, $id);
        }
    } else {
        $st = $conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,gender=?,rollNumber=?,courseId=? WHERE Id=?");
        $st->bind_param("ssssii", $fn, $ln, $gender, $roll, $cid, $id);
    }
    $st->execute();
    if ($courseIds) {
        $conn->query("DELETE FROM tblstudent_course WHERE studentId=$id");
        $sc = $conn->prepare("INSERT IGNORE INTO tblstudent_course (studentId, courseId, assignedByRole, assignedById) SELECT ?, ?, 'HOD', ? FROM tblhod_course WHERE hodId=? AND courseId=? LIMIT 1");
        foreach ($courseIds as $courseId) {
            $sc->bind_param("iiiii", $id, $courseId, $hodId, $hodId, $courseId);
            $sc->execute();
        }
    }
    $msg="Student updated!"; $msgType='success';
}

$courseRows = [];
if ($deptCourses) { while ($c = $deptCourses->fetch_assoc()) { $courseRows[] = $c; } $deptCourses->data_seek(0); }
$assignCourseId = isset($_GET['courseId']) ? (int)$_GET['courseId'] : (int)($courseRows[0]['Id'] ?? 0);
$assignSubjects = $assignCourseId ? $conn->query("
    SELECT s.Id,s.subjectName,s.subjectCode,s.semester,s.duration
    FROM tblhod_subject hs
    JOIN tblsubject s ON hs.subjectId=s.Id
    WHERE hs.hodId=$hodId AND s.deptId=$deptId AND s.courseId=$assignCourseId
    ORDER BY s.subjectName
") : false;
$courseStudents = $assignCourseId ? $conn->query("
    SELECT s.Id,s.firstName,s.lastName,s.rollNumber,s.gender,d.deptName,
           GROUP_CONCAT(DISTINCT CONCAT(sub.subjectCode, ' - ', sub.subjectName) ORDER BY sub.subjectName SEPARATOR ', ') as assignedSubjects
    FROM tblstudent s
    LEFT JOIN tblstudent_course sc ON sc.studentId=s.Id AND sc.courseId=$assignCourseId
    LEFT JOIN tblstudent_subject ss ON ss.studentId=s.Id
    LEFT JOIN tblsubject sub ON sub.Id=ss.subjectId AND sub.courseId=$assignCourseId
    LEFT JOIN tbldepartment d ON s.deptId=d.Id
    WHERE s.courseId=$assignCourseId OR sc.Id IS NOT NULL
    GROUP BY s.Id ORDER BY s.firstName,s.lastName
") : false;

$students=$conn->query("
    SELECT s.*,c.courseName,c.courseCode, d.deptName,
           COALESCE(GROUP_CONCAT(DISTINCT CONCAT(c2.courseCode, ' - ', c2.courseName) ORDER BY c2.courseName SEPARATOR ', '), CONCAT(c.courseCode, ' - ', c.courseName)) as courseList,
           GROUP_CONCAT(DISTINCT COALESCE(sc.courseId, s.courseId) ORDER BY COALESCE(sc.courseId, s.courseId) SEPARATOR ',') as courseIds
    FROM tblstudent s
    JOIN tblcourse c ON s.courseId=c.Id
    LEFT JOIN tbldepartment d ON s.deptId=d.Id
    LEFT JOIN tblstudent_course sc ON s.Id=sc.studentId
    LEFT JOIN tblcourse c2 ON sc.courseId=c2.Id
    WHERE s.deptId=$deptId OR (s.deptId IS NULL AND COALESCE(sc.courseId, s.courseId) IN (SELECT courseId FROM tblhod_course WHERE hodId=$hodId))
    GROUP BY s.Id ORDER BY s.dateCreated DESC");
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Manage Students - PIMT HOD</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <style>
    .photo-upload-box{display:flex;align-items:center;gap:16px;padding:14px;background:rgba(255,255,255,.03);border:2px dashed rgba(255,255,255,.1);border-radius:12px;cursor:pointer;transition:all .25s}
    .photo-upload-box:hover{border-color:var(--indigo);background:var(--indigo-light)}
    .photo-preview{width:56px;height:56px;border-radius:50%;object-fit:cover;background:var(--glass);display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--text-muted);flex-shrink:0;overflow:hidden}
    .gender-select{display:flex;gap:8px;flex-wrap:wrap}
    .gender-opt{flex:1;min-width:80px;padding:9px 8px;border-radius:10px;border:1.5px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);color:var(--text-muted);font-size:13px;font-weight:600;text-align:center;cursor:pointer;transition:all .2s}
    .gender-opt:hover,.gender-opt.sel{border-color:var(--indigo);background:var(--indigo-light);color:var(--text-light)}
    .gender-opt.male.sel{border-color:#60a5fa;background:rgba(96,165,250,.12);color:#60a5fa}
    .gender-opt.female.sel{border-color:#f472b6;background:rgba(244,114,182,.12);color:#f472b6}
    .gender-opt.other.sel{border-color:var(--gold);background:rgba(255,200,68,.1);color:var(--gold)}
    .mini-note{font-size:12px;color:var(--text-muted);line-height:1.5}
    .check-cell{width:42px;text-align:center}
    .student-check{width:17px;height:17px;accent-color:#4f63d2}
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
                <h1 class="page-title"><span>Manage</span> Students</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Students</span></nav>
            </div>
            <?php if($msg): ?><div class="alert alert-<?=$msgType?>"><i class="fas fa-info-circle"></i> <?=$msg?></div><?php endif; ?>

            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px">
                <a href="viewStudents.php" class="btn btn-primary" style="background:linear-gradient(135deg,#4f63d2,#7c83fd)"><i class="fas fa-list"></i> View All Students (Filters)</a>
                <a href="studentAnalytics.php" class="btn btn-primary" style="background:linear-gradient(135deg,#7c3aed,#9f67fa)"><i class="fas fa-chart-pie"></i> Student Analytics</a>
            </div>

            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-user-plus"></i> Enroll New Student</div></div>
                <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">First Name *</label><input type="text" name="firstName" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">Last Name *</label><input type="text" name="lastName" class="form-input" required></div>
                        <div class="form-group">
                            <label class="form-label">Gender *</label>
                            <div class="gender-select" id="genderSel">
                                <div class="gender-opt male sel" data-val="Male" onclick="selectGender(this)"><i class="fas fa-mars"></i> Male</div>
                                <div class="gender-opt female" data-val="Female" onclick="selectGender(this)"><i class="fas fa-venus"></i> Female</div>
                                <div class="gender-opt other" data-val="Other" onclick="selectGender(this)"><i class="fas fa-genderless"></i> Other</div>
                            </div>
                            <input type="hidden" name="gender" id="genderInput" value="Male">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Course(s) *</label>
                            <select name="courseIds[]" class="form-input" multiple size="5" required>
                                <?php $deptCourses->data_seek(0); while($c=$deptCourses->fetch_assoc()): ?>
                                <option value="<?=$c['Id']?>">[<?=$c['courseCode']?>] <?=htmlspecialchars($c['courseName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department *</label>
                            <select name="deptId" class="form-input" required>
                                <option value="">-- Select Dept --</option>
                                <?php $allDepts=$conn->query("SELECT * FROM tbldepartment ORDER BY deptName"); while($d=$allDepts->fetch_assoc()): ?>
                                <option value="<?=$d['Id']?>" <?=($d['Id']==$deptId)?'selected':''?>><?=htmlspecialchars($d['deptName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Roll Number *</label><input type="text" name="rollNumber" class="form-input" placeholder="e.g. CSE2024006" required></div>
                        <div class="form-group"><label class="form-label">Password *</label><input type="text" name="password" class="form-input" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Student Photo (optional)</label>
                            <div class="photo-upload-box" onclick="document.getElementById('sPhoto').click()">
                                <div class="photo-preview" id="sPhotoPreview"><i class="fas fa-camera"></i></div>
                                <div><div style="font-size:13px;font-weight:600;color:var(--text-light)">Upload Photo</div><div style="font-size:11px;color:var(--text-muted)">JPG/PNG max 2MB</div></div>
                            </div>
                            <input type="file" id="sPhoto" name="photo" accept="image/*" style="display:none" onchange="previewImg(this,'sPhotoPreview')">
                        </div>
                    </div>
                    <button type="submit" name="addStudent" class="btn btn-primary"><i class="fas fa-user-plus"></i> Enroll Student</button>
                </form>
                </div>
            </div>

            <div class="cams-card">
                <div class="card-header">
                    <div>
                        <div class="card-title"><i class="fas fa-user-check"></i> Add Existing Course Students to Subject</div>
                        <div class="mini-note">Use HOD-enrolled course students as prefilled data and attach them to your subject.</div>
                    </div>
                    <form method="GET" style="display:flex;gap:8px;align-items:center">
                        <select name="courseId" class="form-input" style="max-width:260px;padding:7px 12px;font-size:13px" onchange="this.form.submit()">
                            <?php foreach($courseRows as $c): ?>
                            <option value="<?=$c['Id']?>" <?=$assignCourseId==$c['Id']?'selected':''?>>[<?=htmlspecialchars($c['courseCode'])?>] <?=htmlspecialchars($c['courseName'])?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
                    </form>
                </div>
                <div class="card-body">
                    <?php if(!$assignCourseId): ?>
                    <div class="empty-state"><div class="empty-title">Select a course to load students</div></div>
                    <?php elseif(!$assignSubjects || $assignSubjects->num_rows===0): ?>
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No HOD subjects found for this course.</div>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="assignCourseId" value="<?=$assignCourseId?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Subject(s) *</label>
                                <select name="assignSubjectIds[]" class="form-input" multiple size="5" required>
                                    <?php while($sub=$assignSubjects->fetch_assoc()): ?>
                                    <option value="<?=$sub['Id']?>">[<?=htmlspecialchars($sub['subjectCode'])?>] <?=htmlspecialchars($sub['subjectName'])?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group"><label class="form-label">Semester</label><input type="text" name="assignSemester" class="form-input" placeholder="Use subject default if blank"></div>
                            <div class="form-group"><label class="form-label">Duration</label><input type="text" name="assignDuration" class="form-input" placeholder="Use subject default if blank"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin:12px 0">
                            <div class="mini-note">Select students from the course list below.</div>
                            <button type="button" class="btn btn-sm" style="background:var(--glass);border:1px solid var(--glass-border)" onclick="toggleAssignChecks(this)"><i class="fas fa-check-square"></i> Select All</button>
                        </div>
                        <div class="table-wrapper scroll-y">
                            <table class="cams-table">
                                <thead><tr><th class="check-cell"></th><th>Student</th><th>Roll No</th><th>Gender</th><th>Department</th><th>Assigned Subjects</th></tr></thead>
                                <tbody>
                                <?php if($courseStudents && $courseStudents->num_rows>0): while($s=$courseStudents->fetch_assoc()):
                                    $gColor = $s['gender']==='Female'?'#f472b6':($s['gender']==='Other'?'var(--gold)':'#60a5fa');
                                ?>
                                <tr>
                                    <td class="check-cell"><input type="checkbox" class="student-check assign-student-check" name="assignStudentIds[]" value="<?=$s['Id']?>"></td>
                                    <td><strong><?=htmlspecialchars($s['firstName'].' '.$s['lastName'])?></strong></td>
                                    <td><span class="badge badge-indigo"><?=htmlspecialchars($s['rollNumber'])?></span></td>
                                    <td><span style="font-size:12px;font-weight:600;color:<?=$gColor?>"><?=htmlspecialchars($s['gender'])?></span></td>
                                    <td><?=htmlspecialchars($s['deptName'] ?? 'Unassigned')?></td>
                                    <td><?=htmlspecialchars($s['assignedSubjects'] ?: 'Not assigned yet')?></td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="6"><div class="empty-state"><div class="empty-title">No students found in this course</div></div></td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" name="assignExistingStudents" class="btn btn-primary" style="margin-top:14px"><i class="fas fa-link"></i> Add Selected Students to Subject</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-list"></i> Students (<?=$students->num_rows?>)</div></div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>#</th><th>Photo</th><th>Name</th><th>Gender</th><th>Roll No</th><th>Course</th><th>Department</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if($students->num_rows>0): $i=1; while($s=$students->fetch_assoc()):
                            $gColor = $s['gender']==='Female'?'#f472b6':($s['gender']==='Other'?'var(--gold)':'#60a5fa');
                        ?>
                        <tr>
                            <td><?=$i++?></td>
                            <td>
                                <?php if(!empty($s['photo'])): ?>
                                <img src="../uploads/students/<?=htmlspecialchars($s['photo'])?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
                                <?php else: ?>
                                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--indigo),var(--teal));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff">
                                    <?=strtoupper(substr($s['firstName'],0,1).substr($s['lastName'],0,1))?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?=htmlspecialchars($s['firstName'].' '.$s['lastName'])?></strong></td>
                            <td><span class="badge" style="background:rgba(0,0,0,.2);color:<?=$gColor?>;border:1px solid <?=$gColor?>40"><?=$s['gender']?></span></td>
                            <td><span class="badge badge-indigo"><?=$s['rollNumber']?></span></td>
                            <td><?=htmlspecialchars($s['courseList'])?></td>
                            <td><?=htmlspecialchars($s['deptName'] ?? 'Unassigned')?></td>
                            <td>
                                <a href="studentAnalytics.php?id=<?=$s['Id']?>" class="btn btn-sm" style="background:linear-gradient(135deg,#7c3aed,#9f67fa);color:#fff" title="Analytics"><i class="fas fa-chart-pie"></i></a>
                                <a href="#" class="btn btn-sm btn-primary"
                                   onclick="openEditS(<?=$s['Id']?>, '<?=addslashes($s['firstName'])?>', '<?=addslashes($s['lastName'])?>', '<?=addslashes($s['gender'])?>', '<?=addslashes($s['rollNumber'])?>', '<?=addslashes($s['courseIds'] ?: $s['courseId'])?>')">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?=$s['Id']?>" class="btn btn-sm btn-danger" onclick="return PIMTAlert.confirmLink(this, 'Remove?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><i class="fas fa-user-graduate"></i></div><div class="empty-title">No students yet</div></div></td></tr>
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
                <div>
                    <label class="form-label">Gender</label>
                    <select name="gender" id="editSGender" class="form-input">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div style="grid-column:1/-1;">
                    <label class="form-label">Course(s)</label>
                    <select name="courseIds[]" id="editSCourse" class="form-input" multiple size="5">
                        <?php $deptCourses->data_seek(0); while($c=$deptCourses->fetch_assoc()): ?>
                        <option value="<?=$c['Id']?>">[<?=$c['courseCode']?>] <?=htmlspecialchars($c['courseName'])?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div style="grid-column:1/-1;"><label class="form-label">New Photo (optional)</label><input type="file" name="editPhoto" class="form-input" accept="image/*"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:20px;width:100%;"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>
<script>
function selectGender(el) {
    document.querySelectorAll('.gender-opt').forEach(x=>x.classList.remove('sel'));
    el.classList.add('sel');
    document.getElementById('genderInput').value = el.dataset.val;
}
function previewImg(input,previewId) {
    const prev=document.getElementById(previewId);
    if(input.files&&input.files[0]){const r=new FileReader();r.onload=e=>{prev.innerHTML='<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%">';};r.readAsDataURL(input.files[0]);}
}
// Sidebar toggle handled by topbar.php
function openEditS(id,fn,ln,gender,roll,courseIds){
    document.getElementById('editSId').value=id;
    document.getElementById('editSFN').value=fn;
    document.getElementById('editSLN').value=ln;
    document.getElementById('editSGender').value=gender;
    document.getElementById('editSRoll').value=roll;
    const selected = String(courseIds || '').split(',');
    Array.from(document.getElementById('editSCourse').options).forEach(opt => opt.selected = selected.includes(opt.value));
    document.getElementById('editSModal').style.display='flex';
}
function closeEditS(){document.getElementById('editSModal').style.display='none';}
document.getElementById('editSModal').addEventListener('click',function(e){if(e.target===this)closeEditS();});
function toggleAssignChecks(btn){
    const checks=Array.from(document.querySelectorAll('.assign-student-check'));
    const shouldCheck=checks.some(ch=>!ch.checked);
    checks.forEach(ch=>ch.checked=shouldCheck);
    btn.innerHTML=shouldCheck?'<i class="fas fa-square"></i> Clear All':'<i class="fas fa-check-square"></i> Select All';
}
</script>
</body></html>
