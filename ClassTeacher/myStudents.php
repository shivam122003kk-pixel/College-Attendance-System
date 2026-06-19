<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
$teacherId = $_SESSION['userId'];
$msg=''; $msgType='';

// Edit student (course-scoped to teacher's courses)
if (isset($_POST['editStudent'])) {
    $id     = (int)$_POST['editId'];
    $fn     = trim($_POST['firstName']); $ln = trim($_POST['lastName']);
    $gender = trim($_POST['gender']); $roll = trim($_POST['rollNumber']);
    // Verify student belongs to one of this teacher's courses
    $chk = $conn->query("SELECT s.Id FROM tblstudent s LEFT JOIN tblstudent_course sc ON s.Id=sc.studentId JOIN tblteacher_course tc ON COALESCE(sc.courseId,s.courseId)=tc.courseId WHERE s.Id=$id AND tc.teacherId=$teacherId LIMIT 1");
    if ($chk->num_rows > 0) {
        if (!empty($_FILES['editPhoto']['name'])) {
            $ext = strtolower(pathinfo($_FILES['editPhoto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png'])) {
                $fname = 'student_'.time().'.'.$ext;
                move_uploaded_file($_FILES['editPhoto']['tmp_name'], '../uploads/students/'.$fname);
                $st = $conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,gender=?,rollNumber=?,photo=? WHERE Id=?");
                $st->bind_param("sssssi", $fn, $ln, $gender, $roll, $fname, $id);
            } else {
                $st = $conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,gender=?,rollNumber=? WHERE Id=?");
                $st->bind_param("ssssi", $fn, $ln, $gender, $roll, $id);
            }
        } else {
            $st = $conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,gender=?,rollNumber=? WHERE Id=?");
            $st->bind_param("ssssi", $fn, $ln, $gender, $roll, $id);
        }
        $st->execute(); $msg="Student updated successfully!"; $msgType='success';
    }
}

// Fetch all students in this teacher's courses
$students = $conn->query("
    SELECT s.*, c.courseName, c.courseCode,
           COALESCE(GROUP_CONCAT(DISTINCT c2.courseCode ORDER BY c2.courseName SEPARATOR ', '), c.courseCode) as courseCodes
    FROM tblstudent s
    JOIN tblcourse c ON s.courseId = c.Id
    LEFT JOIN tblstudent_course sc ON s.Id=sc.studentId
    LEFT JOIN tblcourse c2 ON sc.courseId=c2.Id
    JOIN tblteacher_course tc ON COALESCE(sc.courseId,s.courseId) = tc.courseId
    WHERE tc.teacherId = $teacherId
    GROUP BY s.Id
    ORDER BY c.courseCode, s.firstName
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>My Students - PIMT Teacher</title>
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
                <h1 class="page-title"><span>My</span> Students</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Students</span></nav>
            </div>

            <?php if($msg): ?><div class="alert alert-<?=$msgType?>"><i class="fas fa-check-circle"></i> <?=$msg?></div><?php endif; ?>

            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-user-graduate"></i> Students (<?= $students->num_rows ?>)</div>
                    <div style="display:flex;gap:8px"><a href="studentAnalytics.php" class="btn btn-sm" style="background:linear-gradient(135deg,#7c3aed,#9f67fa);color:#fff"><i class="fas fa-chart-pie"></i> Analytics</a><a href="addStudents.php" class="btn btn-sm btn-success"><i class="fas fa-user-plus"></i> Add Student</a></div>
                </div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>#</th><th>Photo</th><th>Name</th><th>Gender</th><th>Roll No</th><th>Course</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if($students->num_rows > 0): $i=1; while($s = $students->fetch_assoc()): ?>
                        <tr>
                            <td><?=$i++?></td>
                            <td>
                                <?php if(!empty($s['photo'])): ?>
                                <img src="../uploads/students/<?=htmlspecialchars($s['photo'])?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
                                <?php else: ?>
                                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--indigo),var(--teal));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff"><?=strtoupper(substr($s['firstName'],0,1).substr($s['lastName'],0,1))?></div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?=htmlspecialchars($s['firstName'].' '.$s['lastName'])?></strong></td>
                            <td><span style="font-size:12px;font-weight:600;color:<?=$s['gender']==='Female'?'#f472b6':($s['gender']==='Other'?'var(--gold)':'#60a5fa')?>"><?=htmlspecialchars($s['gender'])?></span></td>
                            <td><span class="badge badge-indigo"><?=$s['rollNumber']?></span></td>
                            <td><span class="badge badge-info"><?=htmlspecialchars($s['courseCodes'])?></span></td>
                            <td>
                                <a href="studentAnalytics.php?id=<?=$s['Id']?>" class="btn btn-sm" style="background:linear-gradient(135deg,#7c3aed,#9f67fa);color:#fff" title="Analytics"><i class="fas fa-chart-pie"></i></a>
                                <a href="#" class="btn btn-sm btn-primary"
                                   onclick="openEdit(<?=$s['Id']?>, '<?=addslashes($s['firstName'])?>', '<?=addslashes($s['lastName'])?>', '<?=addslashes($s['gender'])?>', '<?=addslashes($s['rollNumber'])?>')">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><i class="fas fa-user-graduate"></i></div><div class="empty-title">No students found</div></div></td></tr>
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
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#111f38;border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:32px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;position:relative;">
        <button onclick="closeEdit()" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text-muted);font-size:20px;cursor:pointer;"><i class="fas fa-times"></i></button>
        <h3 style="font-family:'Outfit',sans-serif;font-size:20px;font-weight:700;margin-bottom:20px;"><i class="fas fa-user-edit" style="color:var(--teal);margin-right:8px;"></i> Edit Student Info</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="editStudent" value="1">
            <input type="hidden" name="editId" id="editId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div><label class="form-label">First Name</label><input type="text" name="firstName" id="eFN" class="form-input" required></div>
                <div><label class="form-label">Last Name</label><input type="text" name="lastName" id="eLN" class="form-input" required></div>
                <div><label class="form-label">Roll Number</label><input type="text" name="rollNumber" id="eRoll" class="form-input" required></div>
                <div>
                    <label class="form-label">Gender</label>
                    <select name="gender" id="eGender" class="form-input">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div style="grid-column:1/-1;"><label class="form-label">New Photo (optional)</label><input type="file" name="editPhoto" class="form-input" accept="image/*"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:20px;width:100%;"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>
<script>
function openEdit(id,fn,ln,gender,roll){
    document.getElementById('editId').value=id;
    document.getElementById('eFN').value=fn;
    document.getElementById('eLN').value=ln;
    document.getElementById('eGender').value=gender;
    document.getElementById('eRoll').value=roll;
    document.getElementById('editModal').style.display='flex';
}
function closeEdit(){document.getElementById('editModal').style.display='none';}
document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)closeEdit();});
</script>
</body>
</html>
