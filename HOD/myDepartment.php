<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'HOD') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
$deptId = (int)$_SESSION['deptId'];
$hodId  = (int)$_SESSION['userId'];
$msg=''; $msgType='';

// Filter options
$filterCourse  = isset($_GET['course'])  ? (int)$_GET['course']  : 0;
$filterSubject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

// Department info
$dept = $conn->query("SELECT * FROM tbldepartment WHERE Id=$deptId")->fetch_assoc();

// Stats
$totalTeachers = $conn->query("SELECT COUNT(*) c FROM tblteacher WHERE deptId=$deptId")->fetch_assoc()['c'];
$totalStudents = $conn->query("SELECT COUNT(*) c FROM tblstudent WHERE deptId=$deptId")->fetch_assoc()['c'];

// Courses in this department assigned to HOD
$coursesQuery = $conn->query("
    SELECT c.Id, c.courseName, c.courseCode,
           COUNT(DISTINCT sb.Id) as subjectCount,
           COUNT(DISTINCT s.Id)  as studentCount,
           COUNT(DISTINCT tc.teacherId) as teacherCount
    FROM tblhod_course hc
    JOIN tblcourse c ON hc.courseId=c.Id
    LEFT JOIN tblsubject sb ON sb.courseId=c.Id AND sb.deptId=$deptId
    LEFT JOIN tblstudent s  ON s.courseId=c.Id AND s.deptId=$deptId
    LEFT JOIN tblteacher_course tc ON tc.courseId=c.Id
    LEFT JOIN tblteacher t ON tc.teacherId=t.Id AND t.deptId=$deptId
    WHERE hc.hodId=$hodId
    " . ($filterCourse ? "AND c.Id=$filterCourse" : "") . "
    GROUP BY c.Id ORDER BY c.courseName
");

$totalCourses  = $conn->query("SELECT COUNT(DISTINCT courseId) c FROM tblhod_course WHERE hodId=$hodId")->fetch_assoc()['c'];
$totalSubjects = $conn->query("SELECT COUNT(*) c FROM tblsubject WHERE deptId=$deptId" . ($filterCourse?" AND courseId=$filterCourse":""))->fetch_assoc()['c'];

// Subjects for filter dropdown
$subjectsForFilter = $conn->query("SELECT sb.Id, sb.subjectName, sb.subjectCode FROM tblsubject sb WHERE sb.deptId=$deptId ORDER BY sb.subjectName");
// Courses for filter dropdown
$coursesForFilter = $conn->query("SELECT c.Id, c.courseName, c.courseCode FROM tblhod_course hc JOIN tblcourse c ON hc.courseId=c.Id WHERE hc.hodId=$hodId ORDER BY c.courseName");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>My Department - PIMT HOD</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <style>
        .dept-stat-strip { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:24px; }
        .dept-stat-item { background:var(--glass); border:1px solid var(--glass-border); border-radius:12px; padding:16px; text-align:center; }
        .dept-stat-val { font-family:'Outfit',sans-serif; font-size:28px; font-weight:800; }
        .dept-stat-lbl { font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-top:4px; }
        .course-card-dept { background:var(--glass); border:1px solid var(--glass-border); border-radius:14px; overflow:hidden; }
        .course-card-dept-header { background:linear-gradient(135deg,rgba(79,99,210,0.2),rgba(0,180,216,0.1)); padding:14px 18px; }
        .course-card-dept-body { padding:14px 18px; }
        .mini-stat-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; }
        .mini-stat-box { background:rgba(255,255,255,.04); border-radius:8px; padding:10px; text-align:center; }
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
                <div>
                    <h1 class="page-title">My <span>Department</span></h1>
                    <p style="font-size:13px;color:var(--text-muted);margin-top:4px">
                        <i class="fas fa-building" style="color:var(--teal);margin-right:5px"></i>
                        <?= htmlspecialchars($dept['deptName'] ?? 'Department') ?>
                        &nbsp;&middot;&nbsp; <?= htmlspecialchars($dept['deptCode'] ?? '') ?>
                    </p>
                </div>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>My Department</span></nav>
            </div>

            <!-- Department Stats -->
            <div class="dept-stat-strip">
                <div class="dept-stat-item"><div class="dept-stat-val" style="color:var(--gold)"><?=$totalCourses?></div><div class="dept-stat-lbl">Total Courses</div></div>
                <div class="dept-stat-item"><div class="dept-stat-val" style="color:#fb923c"><?=$totalSubjects?></div><div class="dept-stat-lbl">Total Subjects</div></div>
                <div class="dept-stat-item"><div class="dept-stat-val" style="color:#7c83fd"><?=$totalTeachers?></div><div class="dept-stat-lbl">Teachers</div></div>
                <div class="dept-stat-item"><div class="dept-stat-val" style="color:var(--success)"><?=$totalStudents?></div><div class="dept-stat-lbl">Students</div></div>
            </div>

            <!-- Filter -->
            <div class="cams-card">
                <div class="card-body" style="padding:14px 20px">
                    <form method="GET" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Filter by Course</label>
                            <select name="course" class="form-input" style="min-width:200px">
                                <option value="0">-- All Courses --</option>
                                <?php $coursesForFilter->data_seek(0); while($c=$coursesForFilter->fetch_assoc()): ?>
                                <option value="<?=$c['Id']?>" <?=$filterCourse==$c['Id']?'selected':''?>>[<?=$c['courseCode']?>] <?=htmlspecialchars($c['courseName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="margin:0 0 4px">Filter by Subject</label>
                            <select name="subject" class="form-input" style="min-width:200px">
                                <option value="0">-- All Subjects --</option>
                                <?php while($sb=$subjectsForFilter->fetch_assoc()): ?>
                                <option value="<?=$sb['Id']?>" <?=$filterSubject==$sb['Id']?'selected':''?>>[<?=$sb['subjectCode']?>] <?=htmlspecialchars($sb['subjectName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div style="display:flex;gap:8px;margin-top:22px">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                            <a href="myDepartment.php" class="btn btn-sm" style="background:var(--glass);border:1px solid var(--glass-border)">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Course Cards -->
            <?php if($coursesQuery && $coursesQuery->num_rows > 0): ?>
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-book-open"></i> Courses in My Department (<?=$coursesQuery->num_rows?>)</div></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
                        <?php $coursesQuery->data_seek(0); while($c=$coursesQuery->fetch_assoc()): ?>
                        <div class="course-card-dept">
                            <div class="course-card-dept-header">
                                <div style="display:flex;justify-content:space-between;align-items:center">
                                    <div>
                                        <div style="font-weight:700;font-size:15px"><?=htmlspecialchars($c['courseName'])?></div>
                                    </div>
                                    <span class="badge badge-indigo"><?=$c['courseCode']?></span>
                                </div>
                            </div>
                            <div class="course-card-dept-body">
                                <div class="mini-stat-grid">
                                    <div class="mini-stat-box">
                                        <div style="font-weight:700;font-size:20px;color:#fb923c"><?=$c['subjectCount']?></div>
                                        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase">Subjects</div>
                                    </div>
                                    <div class="mini-stat-box">
                                        <div style="font-weight:700;font-size:20px;color:var(--success)"><?=$c['studentCount']?></div>
                                        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase">Students</div>
                                    </div>
                                    <div class="mini-stat-box">
                                        <div style="font-weight:700;font-size:20px;color:#7c83fd"><?=$c['teacherCount']?></div>
                                        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase">Teachers</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="cams-card"><div class="card-body"><div class="empty-state"><div class="empty-icon"><i class="fas fa-building"></i></div><div class="empty-title">No courses assigned yet</div></div></div></div>
            <?php endif; ?>

            <!-- Subjects List -->
            <?php
            $subjWhere = "WHERE sb.deptId=$deptId";
            if ($filterCourse) $subjWhere .= " AND sb.courseId=$filterCourse";
            if ($filterSubject) $subjWhere .= " AND sb.Id=$filterSubject";
            $subjects = $conn->query("
                SELECT sb.*, c.courseName, c.courseCode
                FROM tblsubject sb
                JOIN tblcourse c ON sb.courseId=c.Id
                $subjWhere
                ORDER BY c.courseName, sb.subjectName
            ");
            ?>
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-book"></i> Subjects (<?=$subjects?$subjects->num_rows:0?>)</div></div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>#</th><th>Subject</th><th>Code</th><th>Course</th><th>Semester</th></tr></thead>
                        <tbody>
                        <?php if($subjects && $subjects->num_rows > 0): $i=1; while($sb=$subjects->fetch_assoc()): ?>
                        <tr>
                            <td><?=$i++?></td>
                            <td><strong><?=htmlspecialchars($sb['subjectName'])?></strong></td>
                            <td><span class="badge badge-indigo"><?=$sb['subjectCode']?></span></td>
                            <td><?=htmlspecialchars($sb['courseName'])?> <small class="badge badge-info"><?=$sb['courseCode']?></small></td>
                            <td><?=htmlspecialchars($sb['semester']??'-')?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5"><div class="empty-state"><div class="empty-icon"><i class="fas fa-book"></i></div><div class="empty-title">No subjects found</div></div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
</body>
</html>
