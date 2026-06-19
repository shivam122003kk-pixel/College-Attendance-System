<?php
session_start();
if(!isset($_SESSION['userId'])||$_SESSION['role']!=='Director'){header("Location: ../index.php");exit;}
include '../Includes/dbcon.php';
include '../Includes/db_helpers.php';
$msg=''; $msgType='';

if(isset($_POST['addHOD'])){
    $fn=$_POST['firstName']; $ln=$_POST['lastName']; $email=$_POST['emailAddress'];
    $phone=$_POST['phoneNo']; $pass=$_POST['password']; $deptId=(int)$_POST['deptId'];
    $photo=null;
    if(!empty($_FILES['photo']['name'])){
        $ext=strtolower(pathinfo($_FILES['photo']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,['jpg','jpeg','png'])){$fname='hod_'.time().'.'.$ext;move_uploaded_file($_FILES['photo']['tmp_name'],'../uploads/teachers/'.$fname);$photo=$fname;}
    }
    $ck=$conn->prepare("SELECT Id FROM tblhod WHERE emailAddress=?");$ck->bind_param("s",$email);$ck->execute();
    if($ck->get_result()->num_rows>0){$msg="Email exists!";$msgType='danger';}
    else{
        $st=$conn->prepare("INSERT INTO tblhod (firstName,lastName,emailAddress,password,phoneNo,deptId,photo) VALUES(?,?,?,?,?,?,?)");
        $st->bind_param("sssssis",$fn,$ln,$email,$pass,$phone,$deptId,$photo);
        $st->execute(); $msg="HOD added! Login: $email / $pass"; $msgType='success';
    }
}
if(isset($_GET['delete'])){$conn->query("DELETE FROM tblhod WHERE Id=".(int)$_GET['delete']);resequenceCollegeAttendanceIds($conn);$msg="HOD removed.";$msgType='warning';}

// Edit HOD
if(isset($_POST['editHOD'])){
    $id    = (int)$_POST['editId'];
    $fn    = trim($_POST['firstName']); $ln = trim($_POST['lastName']);
    $email = trim($_POST['emailAddress']); $phone = trim($_POST['phoneNo']);
    $deptId= (int)$_POST['deptId'];
    if (!empty($_FILES['editPhoto']['name'])) {
        $ext = strtolower(pathinfo($_FILES['editPhoto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
            $fname = 'hod_'.time().'.'.$ext;
            move_uploaded_file($_FILES['editPhoto']['tmp_name'], '../uploads/teachers/'.$fname);
            $st = $conn->prepare("UPDATE tblhod SET firstName=?,lastName=?,emailAddress=?,phoneNo=?,deptId=?,photo=? WHERE Id=?");
            $st->bind_param("ssssisi", $fn, $ln, $email, $phone, $deptId, $fname, $id);
        } else {
            $st = $conn->prepare("UPDATE tblhod SET firstName=?,lastName=?,emailAddress=?,phoneNo=?,deptId=? WHERE Id=?");
            $st->bind_param("ssssii", $fn, $ln, $email, $phone, $deptId, $id);
        }
    } else {
        $st = $conn->prepare("UPDATE tblhod SET firstName=?,lastName=?,emailAddress=?,phoneNo=?,deptId=? WHERE Id=?");
        $st->bind_param("ssssii", $fn, $ln, $email, $phone, $deptId, $id);
    }
    $st->execute(); $msg="HOD updated successfully!"; $msgType='success';
}

$depts=$conn->query("SELECT * FROM tbldepartment ORDER BY deptName");
$hods=$conn->query("SELECT h.*,d.deptName,d.deptCode FROM tblhod h JOIN tbldepartment d ON h.deptId=d.Id ORDER BY h.dateCreated DESC");
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Manage HODs - PIMT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
                <h1 class="page-title"><span>Manage</span> HODs</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>HODs</span></nav>
            </div>
            <?php if($msg):?><div class="alert alert-<?=$msgType?>"><i class="fas fa-info-circle"></i> <?=$msg?></div><?php endif;?>

            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-user-plus"></i> Add Head of Department</div></div>
                <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">First Name *</label><input type="text" name="firstName" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">Last Name *</label><input type="text" name="lastName" class="form-input" required></div>
                        <div class="form-group">
                            <label class="form-label">Department *</label>
                            <select name="deptId" class="form-input" required>
                                <option value="">-- Select Dept --</option>
                                <?php while($d=$depts->fetch_assoc()):?><option value="<?=$d['Id']?>">[<?=$d['deptCode']?>] <?=htmlspecialchars($d['deptName'])?></option><?php endwhile;?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Email *</label><input type="email" name="emailAddress" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">Phone *</label><input type="text" name="phoneNo" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">Password *</label><input type="text" name="password" class="form-input" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Photo (optional)</label>
                            <div class="photo-upload-box" onclick="document.getElementById('hodPhoto').click()">
                                <div class="photo-preview" id="hodPreview"><i class="fas fa-camera"></i></div>
                                <div><div style="font-size:13px;font-weight:600;color:var(--text-light)">Upload Photo</div><div style="font-size:11px;color:var(--text-muted)">JPG/PNG max 2MB</div></div>
                            </div>
                            <input type="file" id="hodPhoto" name="photo" accept="image/*" style="display:none" onchange="previewImg(this,'hodPreview')">
                        </div>
                    </div>
                    <button type="submit" name="addHOD" class="btn btn-primary"><i class="fas fa-plus"></i> Add HOD</button>
                </form>
                </div>
            </div>

            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-list"></i> All HODs (<?=$hods->num_rows?>)</div></div>
                <div class="table-wrapper">
                    <table class="cams-table">
                        <thead><tr><th>#</th><th>Photo</th><th>Name</th><th>Department</th><th>Email</th><th>Phone</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if($hods->num_rows>0):$i=1;while($h=$hods->fetch_assoc()):?>
                        <tr>
                            <td><?=$i++?></td>
                            <td><?php if(!empty($h['photo'])):?><img src="../uploads/teachers/<?=htmlspecialchars($h['photo'])?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover"><?php else:?><div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#00b4d8,#0096b7);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:13px"><?=strtoupper(substr($h['firstName'],0,1).substr($h['lastName'],0,1))?></div><?php endif;?></td>
                            <td><strong><?=htmlspecialchars($h['firstName'].' '.$h['lastName'])?></strong></td>
                            <td><span class="badge badge-info"><?=$h['deptCode']?></span> <?=htmlspecialchars($h['deptName'])?></td>
                            <td><?=htmlspecialchars($h['emailAddress'])?></td>
                            <td><?=htmlspecialchars($h['phoneNo'])?></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-primary"
                                   onclick="openEditHOD(<?=$h['Id']?>, '<?=addslashes($h['firstName'])?>', '<?=addslashes($h['lastName'])?>', '<?=addslashes($h['emailAddress'])?>', '<?=addslashes($h['phoneNo'])?>', <?=(int)$h['deptId']?>)">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?=$h['Id']?>" class="btn btn-sm btn-danger" onclick="return PIMTAlert.confirmLink(this, 'Remove HOD?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile;else:?>
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><i class="fas fa-sitemap"></i></div><div class="empty-title">No HODs yet</div></div></td></tr>
                        <?php endif;?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<!-- Edit HOD Modal -->
<div id="editHODModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#111f38;border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:32px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;">
        <button onclick="closeEditHOD()" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text-muted);font-size:20px;cursor:pointer;"><i class="fas fa-times"></i></button>
        <h3 style="font-family:'Outfit',sans-serif;font-size:20px;font-weight:700;margin-bottom:20px;"><i class="fas fa-sitemap" style="color:var(--teal);margin-right:8px;"></i> Edit HOD</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="editHOD" value="1">
            <input type="hidden" name="editId" id="editHODId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div><label class="form-label">First Name</label><input type="text" name="firstName" id="editHFN" class="form-input" required></div>
                <div><label class="form-label">Last Name</label><input type="text" name="lastName" id="editHLN" class="form-input" required></div>
                <div><label class="form-label">Email</label><input type="email" name="emailAddress" id="editHEmail" class="form-input" required></div>
                <div><label class="form-label">Phone</label><input type="text" name="phoneNo" id="editHPhone" class="form-input"></div>
                <div>
                    <label class="form-label">Department</label>
                    <select name="deptId" id="editHDept" class="form-input">
                        <?php $depts->data_seek(0); while($d=$depts->fetch_assoc()): ?>
                        <option value="<?=$d['Id']?>"><?=htmlspecialchars($d['deptName'])?></option>
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
// Sidebar toggle handled by topbar.php
function openEditHOD(id,fn,ln,email,phone,deptId){
    document.getElementById('editHODId').value=id;
    document.getElementById('editHFN').value=fn;
    document.getElementById('editHLN').value=ln;
    document.getElementById('editHEmail').value=email;
    document.getElementById('editHPhone').value=phone;
    document.getElementById('editHDept').value=deptId;
    document.getElementById('editHODModal').style.display='flex';
}
function closeEditHOD(){document.getElementById('editHODModal').style.display='none';}
document.getElementById('editHODModal').addEventListener('click',function(e){if(e.target===this)closeEditHOD();});
</script>
