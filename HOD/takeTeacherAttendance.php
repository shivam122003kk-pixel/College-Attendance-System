<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'HOD') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
$hodId = (int)$_SESSION['userId'];
$deptId = (int)$_SESSION['deptId'];

$msg = ''; $msgType = '';
$today = date('Y-m-d');

// Selected date - default to today, clamp to max today
$selectedDate = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : $today;
if ($selectedDate > $today) $selectedDate = $today; // never allow future

// Check if attendance already taken for selected date
$alreadyTaken = false;
$chk = $conn->query("SELECT Id FROM tblteacher_attendance WHERE deptId=$deptId AND dateTaken='$selectedDate' LIMIT 1");
$alreadyTaken = ($chk && $chk->num_rows > 0);

// Save attendance
if (isset($_POST['saveAttendance'])) {
    $postDate   = $_POST['attendanceDate'];
    if ($postDate > $today) $postDate = $today; // safety clamp
    $teacherIds = $_POST['teacherIds'] ?? [];
    $checked    = $_POST['check'] ?? [];

    foreach ($teacherIds as $tid) {
        $tid    = (int)$tid;
        $status = in_array((string)$tid, $checked) ? 1 : 0;
        $conn->query("INSERT INTO tblteacher_attendance (teacherId,deptId,status,dateTaken,takenByHodId)
                      VALUES ($tid,$deptId,$status,'$postDate',$hodId)
                      ON DUPLICATE KEY UPDATE status=$status");
    }
    $msg = ($alreadyTaken ? "Attendance updated for " : "Attendance saved for ") . date('d M Y', strtotime($postDate)) . "!";
    $msgType = 'success';
    $alreadyTaken = true;
    $selectedDate = $postDate;
}

// Fetch teachers for this department
$teachers = [];
$res = $conn->query("SELECT * FROM tblteacher WHERE deptId=$deptId ORDER BY firstName");
while ($r = $res->fetch_assoc()) $teachers[] = $r;

// Get existing attendance for selected date
$existingAtt = [];
$r = $conn->query("SELECT teacherId,status FROM tblteacher_attendance WHERE deptId=$deptId AND dateTaken='$selectedDate'");
while ($row = $r->fetch_assoc()) $existingAtt[$row['teacherId']] = $row['status'];

$isToday     = ($selectedDate === $today);
$isPast      = ($selectedDate < $today);
$displayDate = date('D, d M Y', strtotime($selectedDate));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Teacher Attendance - PIMT HOD</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <style>
        .date-picker-bar { display:flex; align-items:center; gap:14px; padding:18px 24px; background:var(--glass); border:1px solid var(--glass-border); border-radius:14px; margin-bottom:20px; flex-wrap:wrap; }
        .date-display { font-family:'Outfit',sans-serif; font-size:18px; font-weight:700; color:var(--text-light); }
        .date-badge { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; }
        .date-badge.today { background:rgba(0,180,216,0.15); color:var(--teal); border:1px solid rgba(0,180,216,0.3); }
        .date-badge.past  { background:rgba(252,163,17,0.12); color:var(--warning); border:1px solid rgba(252,163,17,0.25); }
        .flatpickr-input { padding:10px 40px 10px 14px !important; background:rgba(255,255,255,0.06) !important; border:1.5px solid rgba(255,255,255,0.12) !important; border-radius:10px !important; color:var(--text-light) !important; font-family:'Inter',sans-serif !important; font-size:14px !important; cursor:pointer !important; min-width:170px; outline:none; }
        .flatpickr-input:focus { border-color:var(--indigo-bright) !important; box-shadow:0 0 0 3px rgba(92,107,192,0.2) !important; }
        .flatpickr-calendar { background:#131e35 !important; border:1px solid rgba(255,255,255,.1) !important; border-radius:16px !important; }
        .fp-cal-icon { position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--indigo-bright);font-size:14px;pointer-events:none; }
        .nav-day-btn { width:34px; height:34px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.04); color:var(--text-muted); cursor:pointer; display:flex; align-items:center; justify-content:center; }
        .nav-day-btn:hover:not(:disabled) { background:var(--indigo-light); border-color:var(--indigo); color:var(--indigo-bright); }
        .nav-day-btn:disabled { opacity:0.3; cursor:not-allowed; }
        .att-teacher-row { display:flex; align-items:center; gap:14px; padding:14px 16px; border:1px solid var(--glass-border); border-radius:12px; margin-bottom:10px; background:var(--glass); cursor:pointer; user-select:none; }
        .att-teacher-row:hover { border-color:var(--indigo); background:var(--indigo-light); }
        .att-teacher-row.present { border-color:rgba(6,214,160,0.4); background:rgba(6,214,160,0.06); }
        .att-checkbox { display:none; }
        .att-toggle { width:26px; height:26px; border-radius:7px; border:2px solid rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-size:12px; }
        .att-teacher-row.present .att-toggle { background:var(--success); border-color:var(--success); color:white; }
        .att-avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,var(--indigo),var(--teal)); display:flex; align-items:center; justify-content:center; font-weight:700; color:white; overflow:hidden;}
        .att-avatar img { width:100%; height:100%; object-fit:cover; }
        .select-all-bar { display:flex; align-items:center; justify-content:space-between; padding:10px 16px; background:rgba(255,255,255,0.03); border:1px solid var(--glass-border); border-radius:10px; margin-bottom:14px; }
        .readonly-notice { display:flex; align-items:center; gap:10px; padding:12px 16px; background:rgba(252,163,17,0.08); border:1px solid rgba(252,163,17,0.2); border-radius:10px; font-size:13px; color:var(--warning); margin-bottom:16px; }
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
                <h1 class="page-title">Teacher <span>Attendance</span></h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Teacher Attendance</span></nav>
            </div>

            <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
            <?php endif; ?>

            <div class="cams-card">
                <div class="card-body" style="padding:18px 24px">
                    <form method="GET" id="filterForm" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                        <div style="margin-top:18px"><button type="button" class="nav-day-btn" id="prevDay"><i class="fas fa-chevron-left"></i></button></div>
                        <div style="display:flex;flex-direction:column;gap:5px">
                            <label style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted)">Date</label>
                            <div style="position:relative">
                                <input type="text" name="date" id="datePicker" value="<?=$selectedDate?>" class="flatpickr-input" readonly="readonly">
                                <i class="fas fa-calendar-alt fp-cal-icon"></i>
                            </div>
                        </div>
                        <div style="margin-top:18px"><button type="button" class="nav-day-btn" id="nextDay" <?=$selectedDate>=$today?'disabled':''?>><i class="fas fa-chevron-right"></i></button></div>
                        <div style="margin-top:18px"><button type="button" class="btn btn-sm btn-primary" id="todayBtn" <?=$isToday?'disabled':''?>><i class="fas fa-calendar-day"></i> Today</button></div>
                    </form>
                </div>
            </div>

            <div class="date-picker-bar">
                <i class="fas fa-calendar-alt" style="color:var(--indigo-bright);font-size:20px"></i>
                <div>
                    <div class="date-display"><?=$displayDate?></div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:2px">Teacher attendance session</div>
                </div>
                <span class="date-badge <?=$isToday?'today':'past'?>"><?=$isToday?'Today':'Past Date'?></span>
                <?php if ($alreadyTaken): ?><span class="badge badge-present" style="margin-left:auto"><i class="fas fa-check-circle"></i> Already Taken</span>
                <?php else: ?><span class="badge badge-warning" style="margin-left:auto"><i class="fas fa-clock"></i> Pending</span><?php endif; ?>
            </div>

            <?php if (count($teachers) > 0): ?>
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-chalkboard-teacher"></i> Teachers (<?=count($teachers)?>)</div></div>
                <div class="card-body">
                    <?php if (false): ?>
                    <div class="readonly-notice"><i class="fas fa-info-circle fa-lg"></i> Attendance already recorded.</div>
                    <div>
                    <?php foreach ($teachers as $t): $isPresent = isset($existingAtt[$t['Id']]) && $existingAtt[$t['Id']] == 1; ?>
                    <div class="att-teacher-row <?=$isPresent?'present':''?>" style="cursor:default">
                        <div class="att-toggle" style="<?=$isPresent?'background:var(--success);border-color:var(--success);color:white':'background:rgba(239,35,60,0.12);border-color:rgba(239,35,60,0.3);color:var(--red-alert)'?>">
                            <i class="fas fa-<?=$isPresent?'check':'times'?>"></i>
                        </div>
                        <div class="att-avatar" style="<?=$isPresent?'':'background:linear-gradient(135deg,#c9184a,#6d0025)'?>">
                            <?php if(!empty($t['photo'])): ?><img src="../uploads/teachers/<?=htmlspecialchars($t['photo'])?>"><?php else: ?><?=strtoupper(substr($t['firstName'],0,1).substr($t['lastName'],0,1))?><?php endif; ?>
                        </div>
                        <div style="flex:1">
                            <div style="font-weight:600"><?=htmlspecialchars($t['firstName'].' '.$t['lastName'])?></div>
                            <div style="font-size:12px;color:var(--text-muted)"><?=htmlspecialchars($t['emailAddress'])?></div>
                        </div>
                        <span class="badge <?=$isPresent?'badge-present':'badge-absent'?>"><i class="fas fa-<?=$isPresent?'check':'times'?>"></i> <?=$isPresent?'Present':'Absent'?></span>
                    </div>
                    <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="attendanceDate" value="<?=$selectedDate?>">
                        <div class="select-all-bar">
                            <span style="font-size:13px;font-weight:600;color:var(--text-muted)">Mark teachers present</span>
                            <div style="display:flex;gap:8px">
                                <button type="button" class="btn btn-success btn-sm" onclick="selectAll()"><i class="fas fa-check-double"></i> All Present</button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="clearAll()"><i class="fas fa-times"></i> All Absent</button>
                            </div>
                        </div>
                        <div id="teacherList">
                        <?php foreach ($teachers as $t): $isPresent = isset($existingAtt[$t['Id']]) && $existingAtt[$t['Id']] == 1; ?>
                        <div class="att-teacher-row <?=$isPresent?'present':''?>" id="row_<?=$t['Id']?>" onclick="toggleTeacher(<?=$t['Id']?>)">
                            <input type="hidden" name="teacherIds[]" value="<?=$t['Id']?>">
                            <input type="checkbox" class="att-checkbox" id="chk_<?=$t['Id']?>" name="check[]" value="<?=$t['Id']?>" <?=$isPresent?'checked':''?>>
                            <div class="att-toggle" id="tog_<?=$t['Id']?>"><?=$isPresent?'<i class="fas fa-check"></i>':''?></div>
                            <div class="att-avatar">
                                <?php if(!empty($t['photo'])): ?><img src="../uploads/teachers/<?=htmlspecialchars($t['photo'])?>"><?php else: ?><?=strtoupper(substr($t['firstName'],0,1).substr($t['lastName'],0,1))?><?php endif; ?>
                            </div>
                            <div style="flex:1">
                                <div style="font-weight:600"><?=htmlspecialchars($t['firstName'].' '.$t['lastName'])?></div>
                                <div style="font-size:12px;color:var(--text-muted)"><?=htmlspecialchars($t['emailAddress'])?></div>
                            </div>
                            <div id="status_<?=$t['Id']?>" style="font-size:12px;color:<?=$isPresent?'var(--success)':'var(--text-muted)'?>"><?=$isPresent?'Present':'Absent'?></div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <div style="margin-top:20px;display:flex;gap:14px;align-items:center;">
                            <button type="submit" name="saveAttendance" class="btn btn-primary"><i class="fas fa-save"></i> <?=$alreadyTaken?'Update':'Save'?> Attendance</button>
                            <span id="presentCount" style="font-size:13px;color:var(--text-muted)"><?=array_sum(array_map(fn($t)=>isset($existingAtt[$t['Id']]) && $existingAtt[$t['Id']]==1 ? 1 : 0, $teachers))?> / <?=count($teachers)?> Present</span>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="cams-card"><div class="card-body"><div class="empty-state">
                <div class="empty-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="empty-title">No teachers found</div>
            </div></div></div>
            <?php endif; ?>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
const today = '<?=$today?>'; let totalTeachers = <?=count($teachers)?>; let presentCount = document.querySelectorAll('.att-checkbox:checked').length;
const fpInstance = flatpickr('#datePicker', { dateFormat: 'Y-m-d', defaultDate: '<?=$selectedDate?>', maxDate: 'today', disableMobile: true, theme: 'dark', onChange: ()=>document.getElementById('filterForm').submit() });

function toggleTeacher(id) {
    const chk = document.getElementById('chk_'+id), row = document.getElementById('row_'+id), tog = document.getElementById('tog_'+id), status = document.getElementById('status_'+id);
    if(!chk) return;
    chk.checked = !chk.checked;
    if(chk.checked){ row.classList.add('present'); tog.innerHTML='<i class="fas fa-check"></i>'; if(status){status.textContent='Present';status.style.color='var(--success)';} presentCount++; }
    else{ row.classList.remove('present'); tog.innerHTML=''; if(status){status.textContent='Absent';status.style.color='var(--text-muted)';} presentCount--; }
    updateCount();
}
function selectAll(){ presentCount=0; document.querySelectorAll('.att-checkbox').forEach(chk=>{ const id=chk.id.replace('chk_',''); chk.checked=true; presentCount++; document.getElementById('row_'+id).classList.add('present'); document.getElementById('tog_'+id).innerHTML='<i class="fas fa-check"></i>'; const s=document.getElementById('status_'+id); if(s){s.textContent='Present';s.style.color='var(--success)';} }); updateCount(); }
function clearAll(){ presentCount=0; document.querySelectorAll('.att-checkbox').forEach(chk=>{ const id=chk.id.replace('chk_',''); chk.checked=false; document.getElementById('row_'+id).classList.remove('present'); document.getElementById('tog_'+id).innerHTML=''; const s=document.getElementById('status_'+id); if(s){s.textContent='Absent';s.style.color='var(--text-muted)';} }); updateCount(); }
function updateCount(){ const el=document.getElementById('presentCount'); if(el) el.textContent = presentCount + ' / ' + totalTeachers + ' Present'; }

document.getElementById('prevDay')?.addEventListener('click', ()=>{ const c=fpInstance.selectedDates[0]||new Date(); c.setDate(c.getDate()-1); fpInstance.setDate(c); document.getElementById('filterForm').submit(); });
document.getElementById('nextDay')?.addEventListener('click', ()=>{ const c=fpInstance.selectedDates[0]||new Date(); const n=new Date(c); n.setDate(c.getDate()+1); if(n<=new Date()){ fpInstance.setDate(n); document.getElementById('filterForm').submit(); } });
document.getElementById('todayBtn')?.addEventListener('click', ()=>{ fpInstance.setDate(new Date()); document.getElementById('filterForm').submit(); });
// Sidebar toggle handled by topbar.php
</script>
</body></html>
