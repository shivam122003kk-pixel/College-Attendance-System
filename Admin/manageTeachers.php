<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Director') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
include '../Includes/db_helpers.php';

$msg = ''; $msgType = '';

// Add teacher
if (isset($_POST['addTeacher'])) {
    $fn    = trim($_POST['firstName']);
    $ln    = trim($_POST['lastName']);
    $email = trim($_POST['emailAddress']);
    $phone = trim($_POST['phoneNo']);
    $pass  = trim($_POST['password']);
    $deptId= (int)$_POST['deptId'];
    $photo = null;

    if(!empty($_FILES['photo']['name'])){
        $ext=strtolower(pathinfo($_FILES['photo']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,['jpg','jpeg','png'])){
            $fname='teacher_'.time().'.'.$ext;
            move_uploaded_file($_FILES['photo']['tmp_name'],'../uploads/teachers/'.$fname);
            $photo=$fname;
        }
    }

    $check = $conn->prepare("SELECT Id FROM tblteacher WHERE emailAddress = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $msg = "A teacher with this email already exists!"; $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO tblteacher (firstName,lastName,emailAddress,password,phoneNo,deptId,photo) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssis", $fn, $ln, $email, $pass, $phone, $deptId, $photo);
        $stmt->execute();
        $msg = "Teacher added successfully!"; $msgType = 'success';
    }
}

// Edit teacher
if (isset($_POST['editTeacher'])) {
    $id    = (int)$_POST['editId'];
    $fn    = trim($_POST['firstName']);
    $ln    = trim($_POST['lastName']);
    $email = trim($_POST['emailAddress']);
    $phone = trim($_POST['phoneNo']);
    $deptId= (int)$_POST['deptId'];

    // Handle photo update
    if (!empty($_FILES['editPhoto']['name'])) {
        $ext = strtolower(pathinfo($_FILES['editPhoto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
            $fname = 'teacher_'.time().'.'.$ext;
            move_uploaded_file($_FILES['editPhoto']['tmp_name'], '../uploads/teachers/'.$fname);
            $stmt = $conn->prepare("UPDATE tblteacher SET firstName=?,lastName=?,emailAddress=?,phoneNo=?,deptId=?,photo=? WHERE Id=?");
            $stmt->bind_param("ssssisi", $fn, $ln, $email, $phone, $deptId, $fname, $id);
        } else {
            $stmt = $conn->prepare("UPDATE tblteacher SET firstName=?,lastName=?,emailAddress=?,phoneNo=?,deptId=? WHERE Id=?");
            $stmt->bind_param("ssssii", $fn, $ln, $email, $phone, $deptId, $id);
        }
    } else {
        $stmt = $conn->prepare("UPDATE tblteacher SET firstName=?,lastName=?,emailAddress=?,phoneNo=?,deptId=? WHERE Id=?");
        $stmt->bind_param("ssssii", $fn, $ln, $email, $phone, $deptId, $id);
    }
    $stmt->execute();
    $msg = "Teacher updated successfully!"; $msgType = 'success';
}

// Delete teacher
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM tblteacher WHERE Id = $id");
    $conn->query("DELETE FROM tblteacher_course WHERE teacherId = $id");
    resequenceCollegeAttendanceIds($conn);
    $msg = "Teacher deleted!"; $msgType = 'warning';
}

// Fetch departments
$departments = $conn->query("SELECT * FROM tbldepartment ORDER BY deptName");

// Fetch all teachers
$teachers = $conn->query("SELECT t.*, d.deptName, COUNT(tc.Id) as courseCount FROM tblteacher t LEFT JOIN tblteacher_course tc ON t.Id = tc.teacherId LEFT JOIN tbldepartment d ON t.deptId = d.Id GROUP BY t.Id ORDER BY t.dateCreated DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Manage Teachers - PIMT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <style>
    .photo-upload-box{display:flex;align-items:center;gap:14px;padding:14px;background:rgba(255,255,255,.03);border:2px dashed rgba(255,255,255,.1);border-radius:12px;cursor:pointer;transition:all .25s}
    .photo-upload-box:hover{border-color:var(--teal);background:rgba(0,180,216,.06)}
    .photo-preview{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--text-muted);background:var(--glass);flex-shrink:0;overflow:hidden}
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
                <h1 class="page-title"><span>Manage</span> Teachers</h1>
                <nav class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <i class="fas fa-chevron-right"></i><span>Teachers</span>
                </nav>
            </div>

            <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':($msgType==='warning'?'exclamation-triangle':'times-circle') ?>"></i> <?= $msg ?></div>
            <?php endif; ?>

            <!-- Add Teacher Form -->
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-user-plus"></i> Add New Teacher</div>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="firstName" class="form-input" placeholder="e.g. Rahul" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="lastName" class="form-input" placeholder="e.g. Sharma" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="emailAddress" class="form-input" placeholder="teacher@college.edu" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Phone Number *</label>
                                <input type="text" name="phoneNo" class="form-input" placeholder="10-digit phone" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Department *</label>
                                <select name="deptId" class="form-input" required>
                                    <option value="">-- Select Dept --</option>
                                    <?php while($d = $departments->fetch_assoc()): ?>
                                    <option value="<?= $d['Id'] ?>"><?= htmlspecialchars($d['deptName']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password *</label>
                                <input type="text" name="password" class="form-input" placeholder="Set login password" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Photo (optional)</label>
                                <div class="photo-upload-box" onclick="document.getElementById('teacherPhoto').click()">
                                    <div class="photo-preview" id="teacherPreview"><i class="fas fa-camera"></i></div>
                                    <div><div style="font-size:13px;font-weight:600;color:var(--text-light)">Upload Photo</div><div style="font-size:11px;color:var(--text-muted)">JPG/PNG max 2MB</div></div>
                                </div>
                                <input type="file" id="teacherPhoto" name="photo" accept="image/*" style="display:none" onchange="previewImg(this,'teacherPreview')">
                            </div>
                        </div>
                        <button type="submit" name="addTeacher" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Teacher
                        </button>
                    </form>
                </div>
            </div>

            <!-- Teachers Table -->
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-list"></i> All Teachers (<?= $teachers->num_rows ?>)</div>
                </div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead>
                            <tr><th>#</th><th>Teacher</th><th>Department</th><th>Email</th><th>Phone</th><th>Courses</th><th>Joined</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($teachers->num_rows > 0):
                            $i = 1; while ($t = $teachers->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <?php if(!empty($t['photo'])): ?>
                                    <img src="../uploads/teachers/<?= htmlspecialchars($t['photo']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0">
                                    <?php else: ?>
                                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#5c6bc0,#00b4d8);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:white;flex-shrink:0">
                                        <?= strtoupper(substr($t['firstName'],0,1).substr($t['lastName'],0,1)) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight:600"><?= htmlspecialchars($t['firstName'].' '.$t['lastName']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($t['deptName']): ?>
                                    <span class="badge badge-info"><?= htmlspecialchars($t['deptName']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['emailAddress']) ?></td>
                            <td><?= htmlspecialchars($t['phoneNo']) ?></td>
                            <td><span class="badge badge-indigo"><?= $t['courseCount'] ?> course(s)</span></td>
                            <td><?= date('d M Y', strtotime($t['dateCreated'])) ?></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-primary"
                                   onclick="openEditTeacher(<?= $t['Id'] ?>, '<?= addslashes($t['firstName']) ?>', '<?= addslashes($t['lastName']) ?>', '<?= addslashes($t['emailAddress']) ?>', '<?= addslashes($t['phoneNo']) ?>', <?= (int)$t['deptId'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?= $t['Id'] ?>" class="btn btn-sm btn-danger"
                                   onclick="return PIMTAlert.confirmLink(this, 'Delete this teacher and all their assignments?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                                <div class="empty-title">No teachers yet</div>
                                <div class="empty-text">Add your first teacher using the form above.</div>
                            </div>
                        </td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<!-- Edit Teacher Modal -->
<div id="editTeacherModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;display:none;align-items:center;justify-content:center;">
    <div style="background:#111f38;border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:32px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;">
        <button onclick="closeEditTeacher()" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text-muted);font-size:20px;cursor:pointer;"><i class="fas fa-times"></i></button>
        <h3 style="font-family:'Outfit',sans-serif;font-size:20px;font-weight:700;margin-bottom:20px;"><i class="fas fa-user-edit" style="color:var(--teal);margin-right:8px;"></i> Edit Teacher</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="editTeacher" value="1">
            <input type="hidden" name="editId" id="editTeacherId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div><label class="form-label">First Name</label><input type="text" name="firstName" id="editFN" class="form-input" required></div>
                <div><label class="form-label">Last Name</label><input type="text" name="lastName" id="editLN" class="form-input" required></div>
                <div><label class="form-label">Email Address</label><input type="email" name="emailAddress" id="editEmail" class="form-input" required></div>
                <div><label class="form-label">Phone Number</label><input type="text" name="phoneNo" id="editPhone" class="form-input"></div>
                <div>
                    <label class="form-label">Department</label>
                    <select name="deptId" id="editDeptId" class="form-input">
                        <?php $departments->data_seek(0); while($d = $departments->fetch_assoc()): ?>
                        <option value="<?= $d['Id'] ?>"><?= htmlspecialchars($d['deptName']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div><label class="form-label">New Photo (optional)</label><input type="file" name="editPhoto" class="form-input" accept="image/*"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:20px;width:100%;"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>

<script>
function previewImg(input,previewId){const prev=document.getElementById(previewId);if(input.files&&input.files[0]){const r=new FileReader();r.onload=e=>{prev.innerHTML='<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%">';};r.readAsDataURL(input.files[0]);}}


function openEditTeacher(id, fn, ln, email, phone, deptId) {
    document.getElementById('editTeacherId').value = id;
    document.getElementById('editFN').value = fn;
    document.getElementById('editLN').value = ln;
    document.getElementById('editEmail').value = email;
    document.getElementById('editPhone').value = phone;
    document.getElementById('editDeptId').value = deptId;
    const modal = document.getElementById('editTeacherModal');
    modal.style.display = 'flex';
}
function closeEditTeacher() {
    document.getElementById('editTeacherModal').style.display = 'none';
}
document.getElementById('editTeacherModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditTeacher();
});
</script>
</body>
</html>
