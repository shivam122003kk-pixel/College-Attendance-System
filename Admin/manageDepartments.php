<?php
session_start();
if (!isset($_SESSION['userId'])||$_SESSION['role']!=='Director'){header("Location: ../index.php");exit;}
include '../Includes/dbcon.php';
include '../Includes/db_helpers.php';
$msg=''; $msgType='';

// Add dept
if(isset($_POST['addDept'])){
    $name=trim($_POST['deptName']); $code=strtoupper(trim($_POST['deptCode']));
    $st=$conn->prepare("INSERT INTO tbldepartment (deptName,deptCode) VALUES(?,?)");
    $st->bind_param("ss",$name,$code); $st->execute();
    $msg="Department added!"; $msgType='success';
}
if(isset($_POST['editDept'])){
    $id=(int)$_POST['editId']; $name=trim($_POST['deptName']); $code=strtoupper(trim($_POST['deptCode']));
    $st=$conn->prepare("UPDATE tbldepartment SET deptName=?, deptCode=? WHERE Id=?");
    $st->bind_param("ssi",$name,$code,$id); $st->execute();
    $msg="Department updated!"; $msgType='success';
}
if(isset($_GET['delete'])){$conn->query("DELETE FROM tbldepartment WHERE Id=".(int)$_GET['delete']);resequenceCollegeAttendanceIds($conn);$msg="Deleted.";$msgType='warning';}

$depts=$conn->query("SELECT d.*,COUNT(h.Id) hods,COUNT(t.Id) teachers FROM tbldepartment d LEFT JOIN tblhod h ON d.Id=h.deptId LEFT JOIN tblteacher t ON d.Id=t.deptId GROUP BY d.Id ORDER BY d.deptName");
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Departments - PIMT Director</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
                <h1 class="page-title"><span>Manage</span> Departments</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Departments</span></nav>
            </div>
            <?php if($msg):?><div class="alert alert-<?=$msgType?>"><i class="fas fa-info-circle"></i> <?=$msg?></div><?php endif;?>
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-plus-circle"></i> Add Department</div></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group"><label class="form-label">Department Name *</label><input type="text" name="deptName" class="form-input" placeholder="e.g. Computer Science & Engineering" required></div>
                            <div class="form-group"><label class="form-label">Department Code *</label><input type="text" name="deptCode" class="form-input" placeholder="e.g. CSE" required></div>
                        </div>
                        <button type="submit" name="addDept" class="btn btn-primary"><i class="fas fa-plus"></i> Add Department</button>
                    </form>
                </div>
            </div>
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-building"></i> All Departments (<?=$depts->num_rows?>)</div>
                    <div class="table-tools"><div class="search-box"><i class="fas fa-search"></i><input class="form-input" data-table-search="#departmentsTable" placeholder="Search departments..."></div></div>
                </div>
                <div class="table-wrapper scroll-y">
                    <table class="cams-table" id="departmentsTable">
                        <thead><tr><th>#</th><th>Department Name</th><th>Code</th><th>HODs</th><th>Teachers</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if($depts->num_rows>0):$i=1;while($d=$depts->fetch_assoc()):?>
                        <tr>
                            <td><?=$i++?></td>
                            <td><strong><?=htmlspecialchars($d['deptName'])?></strong></td>
                            <td><span class="badge badge-indigo"><?=$d['deptCode']?></span></td>
                            <td><?=$d['hods']?></td>
                            <td><?=$d['teachers']?></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-primary" onclick="openEditDept(<?=$d['Id']?>,'<?=addslashes($d['deptName'])?>','<?=addslashes($d['deptCode'])?>')"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?=$d['Id']?>" class="btn btn-sm btn-danger" onclick="return PIMTAlert.confirmLink(this, 'Delete?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile;else:?>
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><i class="fas fa-building"></i></div><div class="empty-title">No departments yet</div></div></td></tr>
                        <?php endif;?>
                        <tr class="search-empty-row"><td colspan="6"><div class="empty-state"><div class="empty-title">No matching departments</div></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<div id="editDeptModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#111f38;border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:28px;width:100%;max-width:440px">
        <button onclick="closeEditDept()" style="float:right;background:none;border:0;color:var(--text-muted);font-size:18px"><i class="fas fa-times"></i></button>
        <h3 style="margin-bottom:18px">Edit Department</h3>
        <form method="POST">
            <input type="hidden" name="editDept" value="1"><input type="hidden" name="editId" id="editDeptId">
            <div class="form-group" style="margin-bottom:14px"><label class="form-label">Department Name</label><input name="deptName" id="editDeptName" class="form-input" required></div>
            <div class="form-group" style="margin-bottom:18px"><label class="form-label">Department Code</label><input name="deptCode" id="editDeptCode" class="form-input" required></div>
            <button class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>
<script src="../js/cams-search.js"></script>
<script>
function openEditDept(id,name,code){document.getElementById('editDeptId').value=id;document.getElementById('editDeptName').value=name;document.getElementById('editDeptCode').value=code;document.getElementById('editDeptModal').style.display='flex';}
function closeEditDept(){document.getElementById('editDeptModal').style.display='none';}
</script>

</body></html>
