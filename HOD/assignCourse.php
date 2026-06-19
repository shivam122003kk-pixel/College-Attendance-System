<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'HOD') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';

$hodId = (int)$_SESSION['userId'];
$deptId = (int)$_SESSION['deptId'];
$msg = ''; $msgType = '';

if (isset($_POST['addSubject'])) {
    $name = trim($_POST['subjectName']);
    $code = strtoupper(trim($_POST['subjectCode']));
    $courseId = (int)$_POST['subjectCourseId'];
    $semester = trim($_POST['semester']);
    $duration = trim($_POST['duration']);
    $desc = trim($_POST['description']);
    $courseOk = $conn->query("SELECT Id FROM tblhod_course WHERE hodId=$hodId AND courseId=$courseId")->num_rows > 0;
    if (!$courseOk) {
        $msg = "You can add subjects only inside courses assigned to you."; $msgType = 'danger';
    } else {
        $dup = $conn->prepare("SELECT Id FROM tblsubject WHERE subjectCode=? AND courseId=? LIMIT 1");
        $dup->bind_param("si", $code, $courseId);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            $msg = "Subject code already exists for this course."; $msgType = 'danger';
        } else {
            try {
                $st = $conn->prepare("INSERT INTO tblsubject (subjectName, subjectCode, courseId, deptId, semester, duration, description) VALUES (?,?,?,?,?,?,?)");
                $st->bind_param("ssiisss", $name, $code, $courseId, $deptId, $semester, $duration, $desc);
                $st->execute();
                $subjectId = $conn->insert_id;
                $hs = $conn->prepare("INSERT IGNORE INTO tblhod_subject (hodId, subjectId) VALUES (?,?)");
                $hs->bind_param("ii", $hodId, $subjectId);
                $hs->execute();
                $msg = "Subject added and attached to your HOD panel."; $msgType = 'success';
            } catch (mysqli_sql_exception $e) {
                $msg = $e->getCode() == 1062 ? "Subject code already exists for this course." : "Unable to add subject.";
                $msgType = 'danger';
            }
        }
    }
}

if (isset($_POST['assign'])) {
    $teacherId = (int)$_POST['teacherId'];
    $courseIds = array_values(array_filter(array_map('intval', $_POST['courseIds'] ?? [])));
    $subjectIds = array_values(array_filter(array_map('intval', $_POST['subjectIds'] ?? [])));

    $teacherOk = $conn->query("SELECT Id FROM tblteacher WHERE Id=$teacherId AND deptId=$deptId")->num_rows > 0;
    if (!$teacherOk) {
        $msg = "Please select a teacher from your department."; $msgType = 'danger';
    } elseif (!$courseIds && !$subjectIds) {
        $msg = "Please select at least one course or subject."; $msgType = 'warning';
    } else {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO tblteacher_course (teacherId, courseId)
            SELECT ?, ? FROM tblhod_course WHERE hodId=? AND courseId=? LIMIT 1
        ");
        $added = 0;
        foreach ($courseIds as $courseId) {
            $stmt->bind_param("iiii", $teacherId, $courseId, $hodId, $courseId);
            $stmt->execute();
            $added += $stmt->affected_rows > 0 ? 1 : 0;
        }
        $subjectStmt = $conn->prepare("
            INSERT IGNORE INTO tblteacher_subject (teacherId, subjectId, assignedByHodId)
            SELECT ?, ?, ? FROM tblhod_subject hs
            JOIN tblsubject s ON hs.subjectId=s.Id
            WHERE hs.hodId=? AND hs.subjectId=? AND s.deptId=?
            LIMIT 1
        ");
        foreach ($subjectIds as $subjectId) {
            $subjectStmt->bind_param("iiiiii", $teacherId, $subjectId, $hodId, $hodId, $subjectId, $deptId);
            $subjectStmt->execute();
            $added += $subjectStmt->affected_rows > 0 ? 1 : 0;
        }
        $msg = $added ? "Course and subject assignment updated for teacher." : "Selected item(s) were already assigned or not assigned to your HOD account.";
        $msgType = $added ? 'success' : 'warning';
    }
}

if (isset($_GET['remove'])) {
    $id = (int)$_GET['remove'];
    $conn->query("DELETE tc FROM tblteacher_course tc JOIN tblteacher t ON tc.teacherId=t.Id WHERE tc.Id=$id AND t.deptId=$deptId");
    $msg = "Assignment removed."; $msgType = 'warning';
}

if (isset($_GET['removeSubject'])) {
    $id = (int)$_GET['removeSubject'];
    $conn->query("DELETE ts FROM tblteacher_subject ts JOIN tblteacher t ON ts.teacherId=t.Id WHERE ts.Id=$id AND t.deptId=$deptId");
    $msg = "Subject assignment removed."; $msgType = 'warning';
}

$teachers = $conn->query("SELECT * FROM tblteacher WHERE deptId=$deptId ORDER BY firstName,lastName");
$courses = $conn->query("
    SELECT c.Id,c.courseName,c.courseCode
    FROM tblhod_course hc
    JOIN tblcourse c ON hc.courseId=c.Id
    WHERE hc.hodId=$hodId
    ORDER BY c.courseName
");
$subjects = $conn->query("
    SELECT s.Id, s.subjectName, s.subjectCode, s.semester, c.courseName, c.courseCode
    FROM tblhod_subject hs
    JOIN tblsubject s ON hs.subjectId=s.Id
    JOIN tblcourse c ON s.courseId=c.Id
    WHERE hs.hodId=$hodId AND s.deptId=$deptId
    ORDER BY c.courseName, s.subjectName
");
$assignments = $conn->query("
    SELECT tc.Id, tc.dateAssigned, t.firstName, t.lastName, c.courseName, c.courseCode
    FROM tblteacher_course tc
    JOIN tblteacher t ON tc.teacherId=t.Id
    JOIN tblcourse c ON tc.courseId=c.Id
    WHERE t.deptId=$deptId
      AND tc.courseId IN (SELECT courseId FROM tblhod_course WHERE hodId=$hodId)
    ORDER BY tc.dateAssigned DESC, t.firstName, c.courseName
");
$subjectAssignments = $conn->query("
    SELECT ts.Id, ts.dateAssigned, t.firstName, t.lastName,
           s.subjectName, s.subjectCode, s.semester, c.courseName, c.courseCode
    FROM tblteacher_subject ts
    JOIN tblteacher t ON ts.teacherId=t.Id
    JOIN tblsubject s ON ts.subjectId=s.Id
    JOIN tblcourse c ON s.courseId=c.Id
    WHERE t.deptId=$deptId
      AND ts.subjectId IN (SELECT subjectId FROM tblhod_subject WHERE hodId=$hodId)
    ORDER BY ts.dateAssigned DESC, t.firstName, s.subjectName
");
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Assign Courses - PIMT HOD</title>
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
                <h1 class="page-title"><span>Assign</span> Courses and Subjects</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Assign Courses</span></nav>
            </div>
            <?php if($msg): ?><div class="alert alert-<?=$msgType?>"><i class="fas fa-info-circle"></i> <?=htmlspecialchars($msg)?></div><?php endif; ?>

            <?php if($courses->num_rows===0): ?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No courses are assigned to your HOD account yet. Ask the Director to assign department courses first.</div>
            <?php else: ?>
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-plus-circle"></i> Add Subject for Your Department</div></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group"><label class="form-label">Subject Name *</label><input name="subjectName" class="form-input" required></div>
                            <div class="form-group"><label class="form-label">Subject Code *</label><input name="subjectCode" class="form-input" required></div>
                            <div class="form-group">
                                <label class="form-label">Course *</label>
                                <select name="subjectCourseId" class="form-input" required>
                                    <?php $courses->data_seek(0); while($c=$courses->fetch_assoc()): ?>
                                    <option value="<?=$c['Id']?>">[<?=htmlspecialchars($c['courseCode'])?>] <?=htmlspecialchars($c['courseName'])?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group"><label class="form-label">Semester</label><input name="semester" class="form-input" placeholder="e.g. Semester 1"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label class="form-label">Duration</label><input name="duration" class="form-input" placeholder="e.g. 6 Months"></div>
                            <div class="form-group" style="grid-column:span 2"><label class="form-label">Description</label><input name="description" class="form-input"></div>
                        </div>
                        <button name="addSubject" class="btn btn-success"><i class="fas fa-plus"></i> Add Subject</button>
                    </form>
                </div>
            </div>
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-link"></i> Assign Course and Subject to Teacher</div></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Teacher *</label>
                                <select name="teacherId" class="form-input" required>
                                    <option value="">-- Choose Teacher --</option>
                                    <?php while($t=$teachers->fetch_assoc()): ?>
                                    <option value="<?=$t['Id']?>"><?=htmlspecialchars($t['firstName'].' '.$t['lastName'].' ('.$t['emailAddress'].')')?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Course(s) *</label>
                                <select name="courseIds[]" class="form-input" multiple size="6" required>
                                    <?php $courses->data_seek(0); while($c=$courses->fetch_assoc()): ?>
                                    <option value="<?=$c['Id']?>">[<?=htmlspecialchars($c['courseCode'])?>] <?=htmlspecialchars($c['courseName'])?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Subject(s)</label>
                                <div class="search-box" style="margin-bottom:8px"><i class="fas fa-search"></i><input type="text" class="form-input" id="subjectOptionSearch" placeholder="Search assigned subjects..."></div>
                                <select name="subjectIds[]" id="subjectSelect" class="form-input" multiple size="6">
                                    <?php if($subjects): while($s=$subjects->fetch_assoc()): ?>
                                    <option value="<?=$s['Id']?>" data-search="<?=htmlspecialchars(strtolower($s['subjectName'].' '.$s['subjectCode'].' '.$s['courseName'].' '.$s['courseCode'].' '.$s['semester']))?>">
                                        [<?=htmlspecialchars($s['subjectCode'])?>] <?=htmlspecialchars($s['subjectName'])?> - <?=htmlspecialchars($s['courseCode'])?> <?=htmlspecialchars($s['semester'] ?: '')?>
                                    </option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="assign" class="btn btn-primary"><i class="fas fa-link"></i> Assign to Teacher</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-list"></i> Teacher Course Assignments (<?=$assignments->num_rows?>)</div>
                    <div class="table-tools"><div class="search-box"><i class="fas fa-search"></i><input class="form-input" data-table-search="#teacherCourseTable" placeholder="Search teacher or course..."></div></div>
                </div>
                <div class="table-wrapper scroll-y">
                    <table class="cams-table" id="teacherCourseTable">
                        <thead><tr><th>#</th><th>Teacher</th><th>Course</th><th>Code</th><th>Assigned On</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if($assignments->num_rows>0): $i=1; while($a=$assignments->fetch_assoc()): ?>
                        <tr>
                            <td><?=$i++?></td>
                            <td><?=htmlspecialchars($a['firstName'].' '.$a['lastName'])?></td>
                            <td><?=htmlspecialchars($a['courseName'])?></td>
                            <td><span class="badge badge-info"><?=htmlspecialchars($a['courseCode'])?></span></td>
                            <td><?=date('d M Y', strtotime($a['dateAssigned']))?></td>
                            <td><a href="?remove=<?=$a['Id']?>" class="btn btn-sm btn-danger" onclick="return PIMTAlert.confirmLink(this, 'Remove this assignment?')"><i class="fas fa-unlink"></i> Remove</a></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><i class="fas fa-link"></i></div><div class="empty-title">No teacher assignments yet</div></div></td></tr>
                        <?php endif; ?>
                        <tr class="search-empty-row"><td colspan="6"><div class="empty-state"><div class="empty-title">No matching assignments</div></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-book"></i> Teacher Subject Assignments (<?=$subjectAssignments->num_rows?>)</div>
                    <div class="table-tools"><div class="search-box"><i class="fas fa-search"></i><input class="form-input" data-table-search="#teacherSubjectTable" placeholder="Search teacher, course, subject..."></div></div>
                </div>
                <div class="table-wrapper scroll-y">
                    <table class="cams-table" id="teacherSubjectTable">
                        <thead><tr><th>#</th><th>Teacher</th><th>Subject</th><th>Course</th><th>Semester</th><th>Assigned On</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if($subjectAssignments->num_rows>0): $i=1; while($a=$subjectAssignments->fetch_assoc()): ?>
                        <tr>
                            <td><?=$i++?></td>
                            <td><?=htmlspecialchars($a['firstName'].' '.$a['lastName'])?></td>
                            <td><?=htmlspecialchars($a['subjectName'])?> <span class="badge badge-info"><?=htmlspecialchars($a['subjectCode'])?></span></td>
                            <td><?=htmlspecialchars($a['courseName'])?> <span class="badge badge-indigo"><?=htmlspecialchars($a['courseCode'])?></span></td>
                            <td><?=htmlspecialchars($a['semester'] ?: '-')?></td>
                            <td><?=date('d M Y', strtotime($a['dateAssigned']))?></td>
                            <td><a href="?removeSubject=<?=$a['Id']?>" class="btn btn-sm btn-danger" onclick="return PIMTAlert.confirmLink(this, 'Remove this subject assignment?')"><i class="fas fa-unlink"></i> Remove</a></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><i class="fas fa-book"></i></div><div class="empty-title">No subject assignments yet</div></div></td></tr>
                        <?php endif; ?>
                        <tr class="search-empty-row"><td colspan="7"><div class="empty-state"><div class="empty-title">No matching subject assignments</div></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<script src="../js/cams-search.js"></script>
<script>
const subjectSearch = document.getElementById('subjectOptionSearch');
if (subjectSearch) {
    subjectSearch.addEventListener('input', function() {
        const term = this.value.trim().toLowerCase();
        document.querySelectorAll('#subjectSelect option').forEach(opt => {
            opt.hidden = term && !opt.dataset.search.includes(term);
        });
    });
}
</script>
</body></html>
