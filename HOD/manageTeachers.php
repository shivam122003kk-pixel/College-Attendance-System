<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'HOD') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
include '../Includes/db_helpers.php';
$deptId = (int)$_SESSION['deptId'];
$msg=''; $msgType='';

// Add teacher
if (isset($_POST['addTeacher'])) {
    $fn=$_POST['firstName']; $ln=$_POST['lastName']; $email=$_POST['emailAddress'];
    $phone=$_POST['phoneNo']; $pass=$_POST['password'];
    $photo=null;
    if (!empty($_FILES['photo']['name'])) {
        $ext=strtolower(pathinfo($_FILES['photo']['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png'])) {
            $fname='teacher_'.time().'.'.$ext;
            move_uploaded_file($_FILES['photo']['tmp_name'],'../uploads/teachers/'.$fname);
            $photo=$fname;
        }
    }
    $ck=$conn->prepare("SELECT Id FROM tblteacher WHERE emailAddress=?"); $ck->bind_param("s",$email); $ck->execute();
    if ($ck->get_result()->num_rows>0) { $msg="Email already exists!"; $msgType='danger'; }
    else {
        $st=$conn->prepare("INSERT INTO tblteacher (firstName,lastName,emailAddress,password,phoneNo,deptId,photo) VALUES(?,?,?,?,?,?,?)");
        $st->bind_param("sssssis",$fn,$ln,$email,$pass,$phone,$deptId,$photo);
        $st->execute(); $msg="Teacher added!"; $msgType='success';
    }
}

if (isset($_GET['delete'])) { $id=(int)$_GET['delete']; $conn->query("DELETE FROM tblteacher WHERE Id=$id AND deptId=$deptId"); resequenceCollegeAttendanceIds($conn); $msg="Teacher removed."; $msgType='warning'; }

// Edit teacher (dept-scoped)
if (isset($_POST['editTeacher'])) {
    $id    = (int)$_POST['editId'];
    $fn    = trim($_POST['firstName']); $ln = trim($_POST['lastName']);
    $email = trim($_POST['emailAddress']); $phone = trim($_POST['phoneNo']);
    // Verify teacher belongs to this HOD's dept
    $chk = $conn->query("SELECT Id FROM tblteacher WHERE Id=$id AND deptId=$deptId");
    if ($chk->num_rows > 0) {
        if (!empty($_FILES['editPhoto']['name'])) {
            $ext = strtolower(pathinfo($_FILES['editPhoto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png'])) {
                $fname = 'teacher_'.time().'.'.$ext;
                move_uploaded_file($_FILES['editPhoto']['tmp_name'], '../uploads/teachers/'.$fname);
                $st = $conn->prepare("UPDATE tblteacher SET firstName=?,lastName=?,emailAddress=?,phoneNo=?,photo=? WHERE Id=? AND deptId=?");
                $st->bind_param("sssssii", $fn, $ln, $email, $phone, $fname, $id, $deptId);
            } else {
                $st = $conn->prepare("UPDATE tblteacher SET firstName=?,lastName=?,emailAddress=?,phoneNo=? WHERE Id=? AND deptId=?");
                $st->bind_param("ssssii", $fn, $ln, $email, $phone, $id, $deptId);
            }
        } else {
            $st = $conn->prepare("UPDATE tblteacher SET firstName=?,lastName=?,emailAddress=?,phoneNo=? WHERE Id=? AND deptId=?");
            $st->bind_param("ssssii", $fn, $ln, $email, $phone, $id, $deptId);
        }
        $st->execute(); $msg="Teacher updated!"; $msgType='success';
    }
}

$teachers=$conn->query("SELECT t.*,d.deptName FROM tblteacher t LEFT JOIN tbldepartment d ON t.deptId=d.Id WHERE t.deptId=$deptId ORDER BY t.dateCreated DESC");
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Manage Teachers - PIMT HOD</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <style>
    .photo-upload-box{display:flex;align-items:center;gap:16px;padding:16px;background:rgba(255,255,255,.03);border:2px dashed rgba(255,255,255,.1);border-radius:12px;cursor:pointer;transition:all .25s}
    .photo-upload-box:hover{border-color:var(--indigo);background:var(--indigo-light)}
    .photo-preview{width:64px;height:64px;border-radius:50%;object-fit:cover;background:var(--glass);display:flex;align-items:center;justify-content:center;font-size:26px;color:var(--text-muted);flex-shrink:0;overflow:hidden}
    .photo-preview img{width:100%;height:100%;object-fit:cover}
    .teacher-avatar{width:42px;height:42px;border-radius:50%;object-fit:cover;background:linear-gradient(135deg,var(--indigo),var(--teal));display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:14px;flex-shrink:0}
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
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Teachers</span></nav>
            </div>
            <?php if($msg): ?><div class="alert alert-<?=$msgType?>"><i class="fas fa-check-circle"></i> <?=$msg?></div><?php endif; ?>

            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-user-plus"></i> Add New Teacher</div></div>
                <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">First Name *</label><input type="text" name="firstName" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">Last Name *</label><input type="text" name="lastName" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">Email *</label><input type="email" name="emailAddress" class="form-input" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Phone *</label><input type="text" name="phoneNo" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">Password *</label><input type="text" name="password" class="form-input" required></div>
                        <div class="form-group">
                            <label class="form-label">Photo (optional)</label>
                            <div class="photo-upload-box" onclick="document.getElementById('tPhoto').click()">
                                <div class="photo-preview" id="tPhotoPreview"><i class="fas fa-camera"></i></div>
                                <div><div style="font-size:13px;font-weight:600;color:var(--text-light)">Upload Photo</div><div style="font-size:11px;color:var(--text-muted)">JPG/PNG, max 2MB</div></div>
                            </div>
                            <input type="file" id="tPhoto" name="photo" accept="image/*" style="display:none" onchange="previewImg(this,'tPhotoPreview')">
                        </div>
                    </div>
                    <button type="submit" name="addTeacher" class="btn btn-primary"><i class="fas fa-plus"></i> Add Teacher</button>
                </form>
                </div>
            </div>

            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-list"></i> Department Teachers (<?=$teachers->num_rows?>)</div></div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>#</th><th>Photo</th><th>Name</th><th>Email</th><th>Phone</th><th>Added</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if($teachers->num_rows>0): $i=1; while($t=$teachers->fetch_assoc()): ?>
                        <tr>
                            <td><?=$i++?></td>
                            <td>
                                <?php if($t['photo']): ?>
                                <img src="../uploads/teachers/<?=htmlspecialchars($t['photo'])?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
                                <?php else: ?>
                                <div class="teacher-avatar"><?=strtoupper(substr($t['firstName'],0,1).substr($t['lastName'],0,1))?></div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?=htmlspecialchars($t['firstName'].' '.$t['lastName'])?></strong></td>
                            <td><?=htmlspecialchars($t['emailAddress'])?></td>
                            <td><?=htmlspecialchars($t['phoneNo'])?></td>
                            <td><?=date('d M Y',strtotime($t['dateCreated']))?></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-primary"
                                   onclick="openEditT(<?=$t['Id']?>, '<?=addslashes($t['firstName'])?>', '<?=addslashes($t['lastName'])?>', '<?=addslashes($t['emailAddress'])?>', '<?=addslashes($t['phoneNo'])?>')">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?=$t['Id']?>" class="btn btn-sm btn-danger" onclick="return PIMTAlert.confirmLink(this, 'Remove?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><i class="fas fa-chalkboard-teacher"></i></div><div class="empty-title">No teachers yet</div></div></td></tr>
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
<div id="editTModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#111f38;border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:32px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;position:relative;">
        <button onclick="closeEditT()" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text-muted);font-size:20px;cursor:pointer;"><i class="fas fa-times"></i></button>
        <h3 style="font-family:'Outfit',sans-serif;font-size:20px;font-weight:700;margin-bottom:20px;"><i class="fas fa-user-edit" style="color:var(--teal);margin-right:8px;"></i> Edit Teacher</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="editTeacher" value="1">
            <input type="hidden" name="editId" id="editTId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div><label class="form-label">First Name</label><input type="text" name="firstName" id="editTFN" class="form-input" required></div>
                <div><label class="form-label">Last Name</label><input type="text" name="lastName" id="editTLN" class="form-input" required></div>
                <div><label class="form-label">Email</label><input type="email" name="emailAddress" id="editTEmail" class="form-input" required></div>
                <div><label class="form-label">Phone</label><input type="text" name="phoneNo" id="editTPhone" class="form-input"></div>
                <div style="grid-column:1/-1;"><label class="form-label">New Photo (optional)</label><input type="file" name="editPhoto" class="form-input" accept="image/*"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:20px;width:100%;"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>
<script>
function previewImg(input, previewId) {
    const prev = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => { prev.innerHTML = '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%">'; };
        r.readAsDataURL(input.files[0]);
    }
}
// Sidebar toggle handled by topbar.php
function openEditT(id,fn,ln,email,phone){
    document.getElementById('editTId').value=id;
    document.getElementById('editTFN').value=fn;
    document.getElementById('editTLN').value=ln;
    document.getElementById('editTEmail').value=email;
    document.getElementById('editTPhone').value=phone;
    document.getElementById('editTModal').style.display='flex';
}
function closeEditT(){document.getElementById('editTModal').style.display='none';}
document.getElementById('editTModal').addEventListener('click',function(e){if(e.target===this)closeEditT();});
</script>
</body></html>
