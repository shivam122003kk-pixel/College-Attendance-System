<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Director') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
$msg=''; $msgType='';

// Update profile
if (isset($_POST['updateProfile'])) {
    $fn    = trim($_POST['firstName']);
    $ln    = trim($_POST['lastName']);
    $phone = trim($_POST['phoneNo']);
    $id    = (int)$_SESSION['userId'];

    if (!empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
            if (!is_dir('../uploads/directors')) mkdir('../uploads/directors', 0755, true);
            $fname = 'director_'.time().'.'.$ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], '../uploads/directors/'.$fname);
            $st = $conn->prepare("UPDATE tbldirector SET firstName=?,lastName=?,phoneNo=?,photo=? WHERE Id=?");
            $st->bind_param("ssssi", $fn, $ln, $phone, $fname, $id);
        } else {
            $st = $conn->prepare("UPDATE tbldirector SET firstName=?,lastName=?,phoneNo=? WHERE Id=?");
            $st->bind_param("sssi", $fn, $ln, $phone, $id);
        }
    } else {
        $st = $conn->prepare("UPDATE tbldirector SET firstName=?,lastName=?,phoneNo=? WHERE Id=?");
        $st->bind_param("sssi", $fn, $ln, $phone, $id);
    }
    $st->execute();
    $_SESSION['firstName'] = $fn;
    $_SESSION['lastName']  = $ln;
    header("Location: profile.php?updated=1"); exit;
}

if (isset($_GET['updated'])) {
    $msg = "Profile updated successfully!"; $msgType = 'success';
}

// Change password
if (isset($_POST['changePass'])) {
    $id      = (int)$_SESSION['userId'];
    $current = trim($_POST['currentPass']);
    $new     = trim($_POST['newPass']);
    $confirm = trim($_POST['confirmPass']);
    $row = $conn->query("SELECT password FROM tbldirector WHERE Id=$id")->fetch_assoc();
    if ($row['password'] !== $current) {
        $msg = "Current password is incorrect!"; $msgType = 'danger';
    } elseif ($new !== $confirm) {
        $msg = "New passwords do not match!"; $msgType = 'danger';
    } elseif (strlen($new) < 6) {
        $msg = "Password must be at least 6 characters!"; $msgType = 'danger';
    } else {
        $conn->query("UPDATE tbldirector SET password='$new' WHERE Id=$id");
        $msg = "Password changed successfully!"; $msgType = 'success';
    }
}

$dir = $conn->query("SELECT * FROM tbldirector WHERE Id=".(int)$_SESSION['userId'])->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>My Profile - PIMT Director</title>
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
                <h1 class="page-title"><span>My</span> Profile</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Profile</span></nav>
            </div>

            <?php if($msg): ?><div class="alert alert-<?=$msgType?>"><i class="fas fa-info-circle"></i> <?=$msg?></div><?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;flex-wrap:wrap;">
                <!-- Profile Card -->
                <div class="cams-card" style="grid-column:1/-1;">
                    <div class="card-header"><div class="card-title"><i class="fas fa-user-circle"></i> Profile Information</div></div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div style="display:flex;align-items:center;gap:24px;margin-bottom:24px;flex-wrap:wrap;">
                                <!-- Avatar -->
                                <div style="position:relative;flex-shrink:0;">
                                    <?php if(!empty($dir['photo'])): ?>
                                    <img src="../uploads/directors/<?=htmlspecialchars($dir['photo'])?>" id="avatarPreview" style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid var(--indigo-bright);">
                                    <?php else: ?>
                                    <div id="avatarPreview" style="width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,var(--indigo),var(--teal));display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:800;color:#fff;border:3px solid var(--indigo-bright);">
                                        <?=strtoupper(substr($dir['firstName'],0,1).substr($dir['lastName'],0,1))?>
                                    </div>
                                    <?php endif; ?>
                                    <label for="photoInput" style="position:absolute;bottom:0;right:0;width:32px;height:32px;background:var(--indigo);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--navy-light);">
                                        <i class="fas fa-camera" style="font-size:12px;color:#fff;"></i>
                                    </label>
                                    <input type="file" name="photo" id="photoInput" accept="image/*" style="display:none;" onchange="previewPhoto(this)">
                                </div>
                                <div>
                                    <div style="font-family:'Outfit',sans-serif;font-size:22px;font-weight:800;color:#fff;"><?=htmlspecialchars($dir['firstName'].' '.$dir['lastName'])?></div>
                                    <div style="color:var(--gold);font-weight:600;font-size:13px;margin-top:4px;"><i class="fas fa-crown" style="margin-right:6px;"></i>Director &middot; Full Access</div>
                                    <div style="color:var(--text-muted);font-size:12px;margin-top:4px;"><?=htmlspecialchars($dir['emailAddress'])?></div>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div><label class="form-label">First Name</label><input type="text" name="firstName" class="form-input" value="<?=htmlspecialchars($dir['firstName'])?>" required></div>
                                <div><label class="form-label">Last Name</label><input type="text" name="lastName" class="form-input" value="<?=htmlspecialchars($dir['lastName'])?>" required></div>
                                <div><label class="form-label">Email (read only)</label><input type="email" class="form-input" value="<?=htmlspecialchars($dir['emailAddress'])?>" readonly style="opacity:0.5;cursor:not-allowed;"></div>
                                <div><label class="form-label">Phone Number</label><input type="text" name="phoneNo" class="form-input" value="<?=htmlspecialchars($dir['phoneNo'] ?? '')?>"></div>
                            </div>
                            <button type="submit" name="updateProfile" class="btn btn-primary" style="margin-top:20px;"><i class="fas fa-save"></i> Save Changes</button>
                        </form>
                    </div>
                </div>

                <!-- Stats -->
                <?php
                $totalStudents = $conn->query("SELECT COUNT(*) as c FROM tblstudent")->fetch_assoc()['c'];
                $totalTeachers = $conn->query("SELECT COUNT(*) as c FROM tblteacher")->fetch_assoc()['c'];
                $totalCourses  = $conn->query("SELECT COUNT(*) as c FROM tblcourse")->fetch_assoc()['c'];
                $totalHODs     = $conn->query("SELECT COUNT(*) as c FROM tblhod")->fetch_assoc()['c'];
                ?>
                <div class="cams-card">
                    <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar"></i> System Overview</div></div>
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div style="background:var(--indigo-light);border-radius:12px;padding:16px;text-align:center;">
                                <div style="font-size:28px;font-weight:800;color:var(--indigo-bright);"><?=$totalStudents?></div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Total Students</div>
                            </div>
                            <div style="background:rgba(0,180,216,0.1);border-radius:12px;padding:16px;text-align:center;">
                                <div style="font-size:28px;font-weight:800;color:var(--teal);"><?=$totalTeachers?></div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Total Teachers</div>
                            </div>
                            <div style="background:var(--success-light);border-radius:12px;padding:16px;text-align:center;">
                                <div style="font-size:28px;font-weight:800;color:var(--success);"><?=$totalCourses?></div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Total Courses</div>
                            </div>
                            <div style="background:rgba(255,209,102,0.1);border-radius:12px;padding:16px;text-align:center;">
                                <div style="font-size:28px;font-weight:800;color:var(--gold);"><?=$totalHODs?></div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Total HODs</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="cams-card">
                    <div class="card-header"><div class="card-title"><i class="fas fa-lock"></i> Change Password</div></div>
                    <div class="card-body">
                        <form method="POST">
                            <div style="display:flex;flex-direction:column;gap:14px;">
                                <div><label class="form-label">Current Password</label><input type="password" name="currentPass" class="form-input" required></div>
                                <div><label class="form-label">New Password</label><input type="password" name="newPass" class="form-input" required></div>
                                <div><label class="form-label">Confirm New Password</label><input type="password" name="confirmPass" class="form-input" required></div>
                            </div>
                            <button type="submit" name="changePass" class="btn btn-warning" style="margin-top:20px;"><i class="fas fa-key"></i> Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<script>
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => {
            const prev = document.getElementById('avatarPreview');
            prev.outerHTML = '<img id="avatarPreview" src="' + e.target.result + '" style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid var(--indigo-bright);">';
        };
        r.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
