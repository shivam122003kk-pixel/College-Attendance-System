<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Director') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
include '../Includes/db_helpers.php';

$msg = ''; $msgType = '';

// Add course
if (isset($_POST['addCourse'])) {
    $name = trim($_POST['courseName']);
    $code = strtoupper(trim($_POST['courseCode']));
    $desc = trim($_POST['description']);
    $dur  = trim($_POST['duration']);

    $check = $conn->prepare("SELECT Id FROM tblcourse WHERE courseCode = ?");
    $check->bind_param("s", $code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $msg = "Course code '$code' already exists!"; $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO tblcourse (courseName,courseCode,description,duration) VALUES (?,?,?,?)");
        $stmt->bind_param("ssss", $name, $code, $desc, $dur);
        $stmt->execute();
        $msg = "Course added successfully!"; $msgType = 'success';
    }
}

// Delete course
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM tblcourse WHERE Id = $id");
    $conn->query("DELETE FROM tblteacher_course WHERE courseId = $id");
    resequenceCollegeAttendanceIds($conn);
    $msg = "Course deleted!"; $msgType = 'warning';
}

// Edit course
if (isset($_POST['editCourse'])) {
    $id   = (int)$_POST['editId'];
    $name = trim($_POST['courseName']);
    $code = strtoupper(trim($_POST['courseCode']));
    $desc = trim($_POST['description']);
    $dur  = trim($_POST['duration']);
    $stmt = $conn->prepare("UPDATE tblcourse SET courseName=?,courseCode=?,description=?,duration=? WHERE Id=?");
    $stmt->bind_param("ssssi", $name, $code, $desc, $dur, $id);
    $stmt->execute();
    $msg = "Course updated successfully!"; $msgType = 'success';
}

$courses = $conn->query("
    SELECT c.*, COUNT(tc.Id) as teacherCount, COUNT(s.Id) as studentCount
    FROM tblcourse c
    LEFT JOIN tblteacher_course tc ON c.Id = tc.courseId
    LEFT JOIN tblstudent s ON c.Id = s.courseId
    GROUP BY c.Id
    ORDER BY c.dateCreated ASC
");

$popularCourses = [
    ['Computer Science & Engineering','CSE','4 Years'],
    ['Data Science & Machine Learning','DSML','2 Years'],
    ['Artificial Intelligence','AI','4 Years'],
    ['Business Administration (MBA)','MBA','2 Years'],
    ['Mechanical Engineering','ME','4 Years'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Manage Courses - PIMT</title>
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
                <h1 class="page-title"><span>Manage</span> Courses</h1>
                <nav class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <i class="fas fa-chevron-right"></i><span>Courses</span>
                </nav>
            </div>

            <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>">
                <i class="fas fa-<?= $msgType==='success'?'check-circle':($msgType==='warning'?'exclamation-triangle':'times-circle') ?>"></i> <?= $msg ?>
            </div>
            <?php endif; ?>

            <!-- Quick fill hint -->
            <div class="alert alert-info">
                <i class="fas fa-lightbulb"></i>
                <strong>Quick Tip:</strong> <?= $courses->num_rows ?> courses are available. 18 popular world courses are pre-loaded. Add custom courses below.
            </div>

            <!-- Add Course Form -->
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-plus-circle"></i> Add New Course</div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Course Name *</label>
                                <input type="text" name="courseName" class="form-input" placeholder="e.g. Computer Science & Engineering" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Course Code *</label>
                                <input type="text" name="courseCode" class="form-input" placeholder="e.g. CSE101" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Duration *</label>
                                <input type="text" name="duration" class="form-input" placeholder="e.g. 4 Years" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="grid-column:1/-1">
                                <label class="form-label">Description *</label>
                                <textarea name="description" class="form-input" rows="3" placeholder="Brief description of this course..." required style="resize:vertical"></textarea>
                            </div>
                        </div>
                        <button type="submit" name="addCourse" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Course
                        </button>
                    </form>
                </div>
            </div>

            <!-- Courses List -->
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-book-open"></i> All Courses (<?= $courses->num_rows ?>)</div>
                </div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead>
                            <tr><th>#</th><th>Course Name</th><th>Code</th><th>Duration</th><th>Teachers</th><th>Students</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $courses->data_seek(0);
                        if ($courses->num_rows > 0):
                            $i=1; while($c = $courses->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <div style="font-weight:600;color:var(--text-light)"><?= htmlspecialchars($c['courseName']) ?></div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars(substr($c['description'],0,60)).'...' ?></div>
                            </td>
                            <td><span class="badge badge-indigo"><?= $c['courseCode'] ?></span></td>
                            <td><span class="badge badge-info"><?= $c['duration'] ?></span></td>
                            <td><?= $c['teacherCount'] ?></td>
                            <td><?= $c['studentCount'] ?></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-primary"
                                   onclick="openEditCourse(<?= $c['Id'] ?>, '<?= addslashes($c['courseName']) ?>', '<?= addslashes($c['courseCode']) ?>', '<?= addslashes($c['duration']) ?>', '<?= addslashes($c['description']) ?>')">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?= $c['Id'] ?>" class="btn btn-sm btn-danger"
                                   onclick="return PIMTAlert.confirmLink(this, 'Delete this course? All assignments will be removed.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7"><div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-book-open"></i></div>
                            <div class="empty-title">No courses yet</div>
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
<!-- Edit Course Modal -->
<div id="editCourseModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#111f38;border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:32px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;">
        <button onclick="closeEditCourse()" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text-muted);font-size:20px;cursor:pointer;"><i class="fas fa-times"></i></button>
        <h3 style="font-family:'Outfit',sans-serif;font-size:20px;font-weight:700;margin-bottom:20px;"><i class="fas fa-book-open" style="color:var(--teal);margin-right:8px;"></i> Edit Course</h3>
        <form method="POST">
            <input type="hidden" name="editCourse" value="1">
            <input type="hidden" name="editId" id="editCourseId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div style="grid-column:1/-1;"><label class="form-label">Course Name</label><input type="text" name="courseName" id="editCName" class="form-input" required></div>
                <div><label class="form-label">Course Code</label><input type="text" name="courseCode" id="editCCode" class="form-input" required></div>
                <div><label class="form-label">Duration</label><input type="text" name="duration" id="editCDur" class="form-input" required></div>
                <div style="grid-column:1/-1;"><label class="form-label">Description</label><textarea name="description" id="editCDesc" class="form-input" rows="3" style="resize:vertical;"></textarea></div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:20px;width:100%;"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>
<script>

function openEditCourse(id, name, code, dur, desc) {
    document.getElementById('editCourseId').value = id;
    document.getElementById('editCName').value = name;
    document.getElementById('editCCode').value = code;
    document.getElementById('editCDur').value = dur;
    document.getElementById('editCDesc').value = desc;
    const modal = document.getElementById('editCourseModal');
    modal.style.display = 'flex';
}
function closeEditCourse() {
    document.getElementById('editCourseModal').style.display = 'none';
}
document.getElementById('editCourseModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditCourse();
});
</script>
</body>
</html>
