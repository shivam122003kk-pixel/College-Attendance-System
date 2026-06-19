<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'HOD') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
$deptId = (int)$_SESSION['deptId'];
$msg=''; $msgType='';

if(isset($_POST['editDept'])){
    $name=trim($_POST['deptName']); $code=strtoupper(trim($_POST['deptCode']));
    $st=$conn->prepare("UPDATE tbldepartment SET deptName=?, deptCode=? WHERE Id=?");
    $st->bind_param("ssi",$name,$code,$deptId); $st->execute();
    $msg="Department updated."; $msgType='success';
}
$dept=$conn->query("SELECT * FROM tbldepartment WHERE Id=$deptId")->fetch_assoc();
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Department - PIMT HOD</title>
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
        <h1 class="page-title"><span>Manage</span> Department</h1>
        <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Department</span></nav>
    </div>
    <?php if($msg): ?><div class="alert alert-<?=$msgType?>"><i class="fas fa-info-circle"></i> <?=htmlspecialchars($msg)?></div><?php endif; ?>
    <div class="cams-card">
        <div class="card-header"><div class="card-title"><i class="fas fa-building"></i> Department Details</div></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Department Name</label><input name="deptName" class="form-input" value="<?=htmlspecialchars($dept['deptName'] ?? '')?>" required></div>
                    <div class="form-group"><label class="form-label">Department Code</label><input name="deptCode" class="form-input" value="<?=htmlspecialchars($dept['deptCode'] ?? '')?>" required></div>
                </div>
                <button name="editDept" class="btn btn-primary"><i class="fas fa-save"></i> Save Department</button>
            </form>
        </div>
    </div>
</div>
<?php include 'Includes/footer.php'; ?>
</div></div>
</body></html>
