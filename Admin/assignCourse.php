<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Director') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
include '../Includes/db_helpers.php';

$msg = ''; $msgType = '';
$directorId = (int)$_SESSION['userId'];

if (isset($_POST['assign'])) {
    $hodId = (int)$_POST['hodId'];
    $courseIds = array_values(array_filter(array_map('intval', $_POST['courseIds'] ?? [])));

    $hod = $conn->query("SELECT h.Id,h.deptId,d.deptName FROM tblhod h JOIN tbldepartment d ON h.deptId=d.Id WHERE h.Id=$hodId")->fetch_assoc();
    if (!$hod) {
        $msg = "Please select a valid HOD."; $msgType = 'danger';
    } elseif (!$courseIds) {
        $msg = "Please select at least one course."; $msgType = 'warning';
    } else {
        $stmt = $conn->prepare("INSERT IGNORE INTO tblhod_course (hodId, courseId, assignedByDirectorId) VALUES (?,?,?)");
        $added = 0;
        foreach ($courseIds as $courseId) {
            $stmt->bind_param("iii", $hodId, $courseId, $directorId);
            $stmt->execute();
            $added += $stmt->affected_rows > 0 ? 1 : 0;
        }
        $msg = $added ? "Course assignment updated for ".$hod['deptName']." HOD." : "Selected course(s) were already assigned.";
        $msgType = $added ? 'success' : 'warning';
    }
}

if (isset($_POST['assignSubjects'])) {
    $hodId = (int)$_POST['subjectHodId'];
    $subjectIds = array_values(array_filter(array_map('intval', $_POST['subjectIds'] ?? [])));
    $hod = $conn->query("SELECT h.Id,h.deptId,d.deptName FROM tblhod h JOIN tbldepartment d ON h.deptId=d.Id WHERE h.Id=$hodId")->fetch_assoc();
    if (!$hod) {
        $msg = "Please select a valid HOD for subject assignment."; $msgType = 'danger';
    } elseif (!$subjectIds) {
        $msg = "Please select at least one subject."; $msgType = 'warning';
    } else {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO tblhod_subject (hodId, subjectId, assignedByDirectorId)
            SELECT ?, s.Id, ? FROM tblsubject s
            WHERE s.Id=? AND s.deptId=?
            LIMIT 1
        ");
        $added = 0;
        foreach ($subjectIds as $subjectId) {
            $stmt->bind_param("iiii", $hodId, $directorId, $subjectId, $hod['deptId']);
            $stmt->execute();
            $added += $stmt->affected_rows > 0 ? 1 : 0;
        }
        $msg = $added ? "Subject assignment updated for ".$hod['deptName']." HOD." : "Selected subject(s) were already assigned or outside this HOD department.";
        $msgType = $added ? 'success' : 'warning';
    }
}

if (isset($_GET['remove'])) {
    $id = (int)$_GET['remove'];
    $conn->query("DELETE FROM tblhod_course WHERE Id=$id");
    resequenceCollegeAttendanceIds($conn);
    $msg = "Assignment removed."; $msgType = 'warning';
}

if (isset($_GET['removeSubject'])) {
    $id = (int)$_GET['removeSubject'];
    $conn->query("DELETE FROM tblhod_subject WHERE Id=$id");
    resequenceCollegeAttendanceIds($conn);
    $msg = "Subject assignment removed."; $msgType = 'warning';
}

$hods = $conn->query("
    SELECT h.*, d.deptName, d.deptCode
    FROM tblhod h
    JOIN tbldepartment d ON h.deptId=d.Id
    ORDER BY d.deptName, h.firstName
");
$courses = $conn->query("SELECT * FROM tblcourse ORDER BY courseName");
$subjects = $conn->query("
    SELECT s.Id, s.subjectName, s.subjectCode, s.semester, c.courseName, c.courseCode, d.deptName, s.deptId
    FROM tblsubject s
    JOIN tblcourse c ON s.courseId=c.Id
    JOIN tbldepartment d ON s.deptId=d.Id
    ORDER BY d.deptName, c.courseName, s.subjectName
");
$assignments = $conn->query("
    SELECT hc.Id, hc.dateAssigned, h.firstName, h.lastName, d.deptName, d.deptCode, c.courseName, c.courseCode
    FROM tblhod_course hc
    JOIN tblhod h ON hc.hodId=h.Id
    JOIN tbldepartment d ON h.deptId=d.Id
    JOIN tblcourse c ON hc.courseId=c.Id
    ORDER BY hc.dateAssigned DESC, d.deptName, c.courseName
");
$subjectAssignments = $conn->query("
    SELECT hs.Id, hs.dateAssigned, h.firstName, h.lastName, d.deptName, d.deptCode,
           s.subjectName, s.subjectCode, s.semester, c.courseName, c.courseCode
    FROM tblhod_subject hs
    JOIN tblhod h ON hs.hodId=h.Id
    JOIN tbldepartment d ON h.deptId=d.Id
    JOIN tblsubject s ON hs.subjectId=s.Id
    JOIN tblcourse c ON s.courseId=c.Id
    ORDER BY hs.dateAssigned DESC, d.deptName, s.subjectName
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Assign Courses - PIMT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
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
                <h1 class="page-title"><span>Assign</span> Courses to HODs</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Assign Courses</span></nav>
            </div>

            <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-link"></i> Assign Department Courses to HOD</div></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Select HOD *</label>
                                <select name="hodId" class="form-input" required>
                                    <option value="">-- Choose HOD --</option>
                                    <?php while($h=$hods->fetch_assoc()): ?>
                                    <option value="<?= $h['Id'] ?>"><?= htmlspecialchars($h['firstName'].' '.$h['lastName'].' - '.$h['deptName'].' ('.$h['deptCode'].')') ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Select Course(s) *</label>
                                <select name="courseIds[]" class="form-input" multiple size="6" required>
                                    <?php while($c=$courses->fetch_assoc()): ?>
                                    <option value="<?= $c['Id'] ?>">[<?= htmlspecialchars($c['courseCode']) ?>] <?= htmlspecialchars($c['courseName']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="assign" class="btn btn-primary"><i class="fas fa-link"></i> Assign to HOD</button>
                    </form>
                </div>
            </div>

            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-book"></i> Assign Subjects to HOD</div></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Select HOD *</label>
                                <select name="subjectHodId" class="form-input" required>
                                    <option value="">-- Choose HOD --</option>
                                    <?php $hods->data_seek(0); while($h=$hods->fetch_assoc()): ?>
                                    <option value="<?= $h['Id'] ?>"><?= htmlspecialchars($h['firstName'].' '.$h['lastName'].' - '.$h['deptName'].' ('.$h['deptCode'].')') ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Search and Select Subject(s) *</label>
                                <div class="search-box" style="margin-bottom:8px"><i class="fas fa-search"></i><input type="text" class="form-input" id="subjectOptionSearch" placeholder="Search subject, course, department..."></div>
                                <select name="subjectIds[]" id="subjectSelect" class="form-input" multiple size="8" required>
                                    <?php while($s=$subjects->fetch_assoc()): ?>
                                    <option value="<?= $s['Id'] ?>" data-search="<?= htmlspecialchars(strtolower($s['subjectName'].' '.$s['subjectCode'].' '.$s['courseName'].' '.$s['courseCode'].' '.$s['deptName'])) ?>">
                                        [<?= htmlspecialchars($s['subjectCode']) ?>] <?= htmlspecialchars($s['subjectName']) ?> - <?= htmlspecialchars($s['courseCode']) ?> / <?= htmlspecialchars($s['deptName']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="assignSubjects" class="btn btn-primary"><i class="fas fa-link"></i> Assign Subjects</button>
                    </form>
                </div>
            </div>

            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-list"></i> HOD Course Assignments (<?= $assignments->num_rows ?>)</div>
                    <div class="table-tools"><div class="search-box"><i class="fas fa-search"></i><input class="form-input" data-table-search="#courseAssignmentsTable" placeholder="Search courses or HODs..."></div></div>
                </div>
                <div class="table-wrapper scroll-y">
                    <table class="cams-table" id="courseAssignmentsTable">
                        <thead><tr><th>#</th><th>HOD</th><th>Department</th><th>Course</th><th>Code</th><th>Assigned On</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if($assignments->num_rows>0): $i=1; while($a=$assignments->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($a['firstName'].' '.$a['lastName']) ?></td>
                            <td><?= htmlspecialchars($a['deptName']) ?> <span class="badge badge-indigo"><?= htmlspecialchars($a['deptCode']) ?></span></td>
                            <td><?= htmlspecialchars($a['courseName']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($a['courseCode']) ?></span></td>
                            <td><?= date('d M Y', strtotime($a['dateAssigned'])) ?></td>
                            <td><a href="?remove=<?= $a['Id'] ?>" class="btn btn-sm btn-danger" onclick="return PIMTAlert.confirmLink(this, 'Remove this HOD course assignment?')"><i class="fas fa-unlink"></i> Remove</a></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><i class="fas fa-link"></i></div><div class="empty-title">No HOD course assignments yet</div></div></td></tr>
                        <?php endif; ?>
                        <tr class="search-empty-row"><td colspan="7"><div class="empty-state"><div class="empty-title">No matching course assignments</div></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-list"></i> HOD Subject Assignments (<?= $subjectAssignments->num_rows ?>)</div>
                    <div class="table-tools"><div class="search-box"><i class="fas fa-search"></i><input class="form-input" data-table-search="#subjectAssignmentsTable" placeholder="Search subjects, courses, HODs..."></div></div>
                </div>
                <div class="table-wrapper scroll-y">
                    <table class="cams-table" id="subjectAssignmentsTable">
                        <thead><tr><th>#</th><th>HOD</th><th>Department</th><th>Subject</th><th>Course</th><th>Semester</th><th>Assigned On</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if($subjectAssignments->num_rows>0): $i=1; while($a=$subjectAssignments->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($a['firstName'].' '.$a['lastName']) ?></td>
                            <td><?= htmlspecialchars($a['deptName']) ?> <span class="badge badge-indigo"><?= htmlspecialchars($a['deptCode']) ?></span></td>
                            <td><?= htmlspecialchars($a['subjectName']) ?> <span class="badge badge-info"><?= htmlspecialchars($a['subjectCode']) ?></span></td>
                            <td><?= htmlspecialchars($a['courseName']) ?> <span class="badge badge-indigo"><?= htmlspecialchars($a['courseCode']) ?></span></td>
                            <td><?= htmlspecialchars($a['semester'] ?: '-') ?></td>
                            <td><?= date('d M Y', strtotime($a['dateAssigned'])) ?></td>
                            <td><a href="?removeSubject=<?= $a['Id'] ?>" class="btn btn-sm btn-danger" onclick="return PIMTAlert.confirmLink(this, 'Remove this HOD subject assignment?')"><i class="fas fa-unlink"></i> Remove</a></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="8"><div class="empty-state"><div class="empty-icon"><i class="fas fa-book"></i></div><div class="empty-title">No HOD subject assignments yet</div></div></td></tr>
                        <?php endif; ?>
                        <tr class="search-empty-row"><td colspan="8"><div class="empty-state"><div class="empty-title">No matching subject assignments</div></div></td></tr>
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
</body>
</html>
