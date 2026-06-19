<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Student') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
$msg=''; $msgType=''; $id=(int)$_SESSION['userId'];

if (isset($_POST['updateProfile'])) {
    $fn=$_POST['firstName']; $ln=$_POST['lastName']; $phone=$_POST['phoneNo']; $gender=$_POST['gender'];
    $uploadError = '';
    if (!empty($_FILES['photo']['name'])) {
        $ext=strtolower(pathinfo($_FILES['photo']['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png'])) {
            $uploadDir='../uploads/students';
            if (!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
            $fname='student_'.time().'_'.rand(100,999).'.'.$ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'],$uploadDir.'/'.$fname)) {
                $st=$conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,phoneNo=?,gender=?,photo=? WHERE Id=?");
                $st->bind_param("sssssi",$fn,$ln,$phone,$gender,$fname,$id);
            } else {
                $uploadError = ' Photo upload failed. Check folder permission for uploads/students.';
                $st=$conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,phoneNo=?,gender=? WHERE Id=?"); $st->bind_param("ssssi",$fn,$ln,$phone,$gender,$id);
            }
        } else { $uploadError = ' Only JPG, JPEG, and PNG photos are allowed.'; $st=$conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,phoneNo=?,gender=? WHERE Id=?"); $st->bind_param("ssssi",$fn,$ln,$phone,$gender,$id); }
    } else { $st=$conn->prepare("UPDATE tblstudent SET firstName=?,lastName=?,phoneNo=?,gender=? WHERE Id=?"); $st->bind_param("ssssi",$fn,$ln,$phone,$gender,$id); }
    $st->execute(); $_SESSION['firstName']=$fn; $_SESSION['lastName']=$ln;
    $msg=$uploadError ? "Profile details saved.".$uploadError : "Profile updated successfully!";
    $msgType=$uploadError ? 'warning' : 'success';
}
if (isset($_POST['changePass'])) {
    $row=$conn->query("SELECT password FROM tblstudent WHERE Id=$id")->fetch_assoc();
    $cur=trim($_POST['currentPass']); $new=trim($_POST['newPass']); $cnf=trim($_POST['confirmPass']);
    if ($row['password']!==$cur) { $msg="Current password wrong!"; $msgType='danger'; }
    elseif ($new!==$cnf) { $msg="Passwords don't match!"; $msgType='danger'; }
    else { $conn->query("UPDATE tblstudent SET password='$new' WHERE Id=$id"); $msg="Password changed!"; $msgType='success'; }
}
$student=$conn->query("SELECT s.*,c.courseName,d.deptName FROM tblstudent s LEFT JOIN tblcourse c ON s.courseId=c.Id LEFT JOIN tbldepartment d ON s.deptId=d.Id WHERE s.Id=$id")->fetch_assoc();
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>My Profile - PIMT Student</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
                <h1 class="page-title"><span>My</span> Profile</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Profile</span></nav>
            </div>
            <?php if($msg): ?><div class="alert alert-<?=$msgType?>"><i class="fas fa-info-circle"></i> <?=$msg?></div><?php endif; ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                <div class="cams-card" style="grid-column:1/-1;">
                    <div class="card-header"><div class="card-title"><i class="fas fa-user-circle"></i> Personal Information</div></div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div style="display:flex;align-items:center;gap:24px;margin-bottom:24px;flex-wrap:wrap;">
                                <div style="position:relative;flex-shrink:0;">
                                    <?php if(!empty($student['photo'])): ?>
                                    <img src="../uploads/students/<?=htmlspecialchars($student['photo'])?>" id="avatarPreview" style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid var(--gold);">
                                    <?php else: ?>
                                    <div id="avatarPreview" style="width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,#ffd166,#f4a261);display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:800;color:#0d1b2a;border:3px solid var(--gold);"><?=strtoupper(substr($student['firstName'],0,1).substr($student['lastName'],0,1))?></div>
                                    <?php endif; ?>
                                    <label for="photoInput" style="position:absolute;bottom:0;right:0;width:32px;height:32px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--navy-light);"><i class="fas fa-camera" style="font-size:12px;color:#0d1b2a;"></i></label>
                                    <input type="file" name="photo" id="photoInput" accept="image/*" style="display:none;" onchange="previewPhoto(this)">
                                </div>
                                <div>
                                    <div style="font-family:'Outfit',sans-serif;font-size:22px;font-weight:800;color:#fff;"><?=htmlspecialchars($student['firstName'].' '.$student['lastName'])?></div>
                                    <div style="color:var(--gold);font-weight:600;font-size:13px;margin-top:4px;"><i class="fas fa-user-graduate" style="margin-right:6px;"></i>Student &middot; <?=htmlspecialchars($student['courseName'])?></div>
                                    <div style="color:var(--text-muted);font-size:12px;margin-top:4px;">Roll No: <?=htmlspecialchars($student['rollNumber'])?></div>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div><label class="form-label">First Name</label><input type="text" name="firstName" class="form-input" value="<?=htmlspecialchars($student['firstName'])?>" required></div>
                                <div><label class="form-label">Last Name</label><input type="text" name="lastName" class="form-input" value="<?=htmlspecialchars($student['lastName'])?>" required></div>
                                <div><label class="form-label">Phone Number</label><input type="text" name="phoneNo" class="form-input" value="<?=htmlspecialchars($student['phoneNo']??'')?>"></div>
                                <div><label class="form-label">Gender</label>
                                    <select name="gender" class="form-input">
                                        <option value="Male" <?=$student['gender']=='Male'?'selected':''?>>Male</option>
                                        <option value="Female" <?=$student['gender']=='Female'?'selected':''?>>Female</option>
                                        <option value="Other" <?=$student['gender']=='Other'?'selected':''?>>Other</option>
                                    </select>
                                </div>
                                <div><label class="form-label">Course (read only)</label><input type="text" class="form-input" value="<?=htmlspecialchars($student['courseName'])?>" readonly style="opacity:0.5;"></div>
                                <div><label class="form-label">Department (read only)</label><input type="text" class="form-input" value="<?=htmlspecialchars($student['deptName'])?>" readonly style="opacity:0.5;"></div>
                            </div>
                            <button type="submit" name="updateProfile" class="btn btn-primary" style="margin-top:20px;"><i class="fas fa-save"></i> Save Profile</button>
                        </form>
                    </div>
                </div>
                <div class="cams-card">
                    <div class="card-header"><div class="card-title"><i class="fas fa-lock"></i> Change Password</div></div>
                    <div class="card-body">
                        <form method="POST">
                            <div style="display:flex;flex-direction:column;gap:14px;">
                                <div><label class="form-label">Current Password</label><input type="password" name="currentPass" class="form-input" required></div>
                                <div><label class="form-label">New Password</label><input type="password" name="newPass" class="form-input" required></div>
                                <div><label class="form-label">Confirm Password</label><input type="password" name="confirmPass" class="form-input" required></div>
                            </div>
                            <button type="submit" name="changePass" class="btn btn-warning" style="margin-top:20px;"><i class="fas fa-key"></i> Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<script>function previewPhoto(input){if(input.files&&input.files[0]){const r=new FileReader();r.onload=e=>{const p=document.getElementById('avatarPreview');p.outerHTML='<img id="avatarPreview" src="'+e.target.result+'" style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid var(--gold);">';};r.readAsDataURL(input.files[0]);}}</script>
</body></html>
