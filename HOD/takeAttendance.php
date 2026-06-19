<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'HOD') { header("Location: ../index.php"); exit; }
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';

$hodId = (int)$_SESSION['userId'];
$deptId = (int)$_SESSION['deptId'];
$msg = ''; $msgType = '';
$today = date('Y-m-d');

$selectedDate = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : $today;
if ($selectedDate > $today) $selectedDate = $today;

$myCourses = $conn->query("
    SELECT c.Id,c.courseName,c.courseCode
    FROM tblhod_course hc
    JOIN tblcourse c ON hc.courseId=c.Id
    WHERE hc.hodId=$hodId
    ORDER BY c.courseName
");

$selectedCourseId = isset($_GET['courseId']) ? (int)$_GET['courseId'] : 0;
if (!$selectedCourseId && $myCourses && $myCourses->num_rows > 0) {
    $myCourses->data_seek(0);
    $first = $myCourses->fetch_assoc();
    $selectedCourseId = (int)$first['Id'];
    $myCourses->data_seek(0);
}

$mySubjects = $selectedCourseId ? $conn->query("
    SELECT s.Id,s.subjectName,s.subjectCode,s.semester
    FROM tblhod_subject hs
    JOIN tblsubject s ON hs.subjectId=s.Id
    WHERE hs.hodId=$hodId AND s.deptId=$deptId AND s.courseId=$selectedCourseId
    ORDER BY s.subjectName
") : false;

$selectedSubjectId = isset($_GET['subjectId']) ? (int)$_GET['subjectId'] : 0;
if (!$selectedSubjectId && $mySubjects && $mySubjects->num_rows > 0) {
    $mySubjects->data_seek(0);
    $firstSubject = $mySubjects->fetch_assoc();
    $selectedSubjectId = (int)$firstSubject['Id'];
    $mySubjects->data_seek(0);
}

$alreadyTaken = false;
if ($selectedCourseId && $selectedSubjectId) {
    $chk = $conn->query("SELECT Id FROM tblattendance WHERE courseId=$selectedCourseId AND subjectId=$selectedSubjectId AND dateTaken='$selectedDate' LIMIT 1");
    $alreadyTaken = ($chk && $chk->num_rows > 0);
}

if (isset($_POST['saveAttendance'])) {
    $cid = (int)$_POST['courseId'];
    $subjectId = (int)$_POST['subjectId'];
    $postDate = $_POST['attendanceDate'];
    if ($postDate > $today) $postDate = $today;
    $studentIds = $_POST['studentIds'] ?? [];
    $checked = $_POST['check'] ?? [];

    $v = $conn->prepare("
        SELECT hs.Id
        FROM tblhod_course hc
        JOIN tblhod_subject hs ON hs.hodId=hc.hodId
        JOIN tblsubject s ON s.Id=hs.subjectId AND s.courseId=hc.courseId AND s.deptId=?
        WHERE hc.hodId=? AND hc.courseId=? AND hs.subjectId=?
        LIMIT 1
    ");
    $v->bind_param("iiii", $deptId, $hodId, $cid, $subjectId);
    $v->execute();
    if ($v->get_result()->num_rows > 0) {
        foreach ($studentIds as $sid) {
            $sid = (int)$sid;
            $status = in_array((string)$sid, $checked) ? 1 : 0;
            $studentOk = $conn->query("
                SELECT s.Id
                FROM tblstudent s
                LEFT JOIN tblstudent_course sc ON sc.studentId=s.Id AND sc.courseId=$cid
                JOIN tblstudent_subject ss ON ss.studentId=s.Id AND ss.subjectId=$subjectId
                WHERE s.Id=$sid AND (s.courseId=$cid OR sc.Id IS NOT NULL)
                LIMIT 1
            ");
            if ($studentOk && $studentOk->num_rows > 0) {
                $conn->query("INSERT INTO tblattendance (studentId,courseId,subjectId,status,dateTaken,takenByTeacherId)
                              VALUES ($sid,$cid,$subjectId,$status,'$postDate',$hodId)
                              ON DUPLICATE KEY UPDATE status=$status,takenByTeacherId=$hodId");
            }
        }
        $msg = ($alreadyTaken ? "Attendance updated for " : "Attendance saved for ") . date('d M Y', strtotime($postDate)) . "!";
        $msgType = 'success';
        $alreadyTaken = true;
        $selectedDate = $postDate;
    } else {
        $msg = "You can take attendance only for your assigned HOD subject."; $msgType = 'danger';
    }
}

$students = [];
if ($selectedCourseId && $selectedSubjectId) {
    $res = $conn->query("
        SELECT DISTINCT s.*
        FROM tblstudent s
        LEFT JOIN tblstudent_course sc ON sc.studentId=s.Id AND sc.courseId=$selectedCourseId
        JOIN tblstudent_subject ss ON ss.studentId=s.Id AND ss.subjectId=$selectedSubjectId
        WHERE s.courseId=$selectedCourseId OR sc.Id IS NOT NULL
        ORDER BY s.firstName,s.lastName
    ");
    while ($res && $r = $res->fetch_assoc()) $students[] = $r;
}

$existingAtt = [];
if ($selectedCourseId && $selectedSubjectId) {
    $r = $conn->query("SELECT studentId,status FROM tblattendance WHERE courseId=$selectedCourseId AND subjectId=$selectedSubjectId AND dateTaken='$selectedDate'");
    while ($r && $row = $r->fetch_assoc()) $existingAtt[$row['studentId']] = $row['status'];
}

$isToday = ($selectedDate === $today);
$displayDate = date('D, d M Y', strtotime($selectedDate));
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Take Student Attendance - PIMT HOD</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <style>
    .date-picker-bar{display:flex;align-items:center;gap:14px;padding:18px 24px;background:var(--glass);border:1px solid var(--glass-border);border-radius:14px;margin-bottom:20px;flex-wrap:wrap}
    .date-display{font-family:'Outfit',sans-serif;font-size:18px;font-weight:700;color:var(--text-light)}
    .date-badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px}
    .date-badge.today{background:rgba(0,180,216,.15);color:var(--teal);border:1px solid rgba(0,180,216,.3)}
    .date-badge.past{background:rgba(252,163,17,.12);color:var(--warning);border:1px solid rgba(252,163,17,.25)}
    .flatpickr-input{padding:10px 40px 10px 14px!important;background:rgba(255,255,255,.06)!important;border:1.5px solid rgba(255,255,255,.12)!important;border-radius:10px!important;color:var(--text-light)!important;font-family:'Inter',sans-serif!important;font-size:14px!important;cursor:pointer!important;min-width:170px;outline:none}
    .fp-cal-icon{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--indigo-bright);font-size:14px;pointer-events:none}
    .nav-day-btn{width:34px;height:34px;border-radius:8px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:var(--text-muted);cursor:pointer;display:flex;align-items:center;justify-content:center}
    .nav-day-btn:disabled{opacity:.3;cursor:not-allowed}
    .att-student-row{display:flex;align-items:center;gap:14px;padding:14px 16px;border:1px solid var(--glass-border);border-radius:12px;margin-bottom:10px;background:var(--glass);cursor:pointer;user-select:none}
    .att-student-row:hover{border-color:var(--indigo);background:var(--indigo-light)}
    .att-student-row.present{border-color:rgba(6,214,160,.4);background:rgba(6,214,160,.06)}
    .att-checkbox{display:none}
    .att-toggle{width:26px;height:26px;border-radius:7px;border:2px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:12px}
    .att-student-row.present .att-toggle{background:var(--success);border-color:var(--success);color:white}
    .att-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--indigo),var(--teal));display:flex;align-items:center;justify-content:center;font-weight:700;color:white;overflow:hidden}
    .att-avatar img{width:100%;height:100%;object-fit:cover}
    .select-all-bar{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:rgba(255,255,255,.03);border:1px solid var(--glass-border);border-radius:10px;margin-bottom:14px}
    .readonly-notice{display:flex;align-items:center;gap:10px;padding:12px 16px;background:rgba(252,163,17,.08);border:1px solid rgba(252,163,17,.2);border-radius:10px;font-size:13px;color:var(--warning);margin-bottom:16px}
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
                <h1 class="page-title">Student <span>Attendance</span></h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Take Attendance</span></nav>
            </div>
            <?php if($msg): ?><div class="alert alert-<?=$msgType?>"><i class="fas fa-info-circle"></i> <?=htmlspecialchars($msg)?></div><?php endif; ?>

            <?php if(!$myCourses || $myCourses->num_rows===0): ?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No courses assigned to your HOD account.</div>
            <?php else: ?>
            <div class="cams-card">
                <div class="card-body" style="padding:18px 24px">
                    <form method="GET" id="filterForm" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                        <div style="display:flex;flex-direction:column;gap:5px">
                            <label style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted)">Course</label>
                            <select name="courseId" class="form-input" style="min-width:260px" onchange="this.form.submit()">
                                <?php $myCourses->data_seek(0); while($c=$myCourses->fetch_assoc()): ?>
                                <option value="<?=$c['Id']?>" <?=$selectedCourseId==$c['Id']?'selected':''?>>[<?=htmlspecialchars($c['courseCode'])?>] <?=htmlspecialchars($c['courseName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:5px">
                            <label style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted)">Subject</label>
                            <select name="subjectId" class="form-input" style="min-width:240px" onchange="this.form.submit()" required>
                                <?php if($mySubjects && $mySubjects->num_rows>0): $mySubjects->data_seek(0); while($sub=$mySubjects->fetch_assoc()): ?>
                                <option value="<?=$sub['Id']?>" <?=$selectedSubjectId==$sub['Id']?'selected':''?>>[<?=htmlspecialchars($sub['subjectCode'])?>] <?=htmlspecialchars($sub['subjectName'])?> <?=htmlspecialchars($sub['semester'] ?: '')?></option>
                                <?php endwhile; else: ?>
                                <option value="0">No subjects available</option>
                                <?php endif; ?>
                            </select>
                        </div>
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
                <div><div class="date-display"><?=$displayDate?></div><div style="font-size:12px;color:var(--text-muted);margin-top:2px">HOD subject attendance session</div></div>
                <span class="date-badge <?=$isToday?'today':'past'?>"><?=$isToday?'Today':'Past Date'?></span>
                <?php if($alreadyTaken): ?><span class="badge badge-present" style="margin-left:auto"><i class="fas fa-check-circle"></i> Already Taken</span>
                <?php else: ?><span class="badge badge-warning" style="margin-left:auto"><i class="fas fa-clock"></i> Pending</span><?php endif; ?>
            </div>

            <?php if(!$selectedSubjectId): ?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Select or create a subject first.</div>
            <?php elseif(count($students)>0): ?>
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-users"></i> Students (<?=count($students)?>)</div></div>
                <div class="card-body">
                <?php if(false): ?>
                    <div class="readonly-notice"><i class="fas fa-info-circle fa-lg"></i> Attendance already recorded for this subject/date.</div>
                    <?php foreach($students as $s): $isPresent=isset($existingAtt[$s['Id']]) && $existingAtt[$s['Id']]==1; ?>
                    <div class="att-student-row <?=$isPresent?'present':''?>" style="cursor:default">
                        <div class="att-toggle" style="<?=$isPresent?'background:var(--success);border-color:var(--success);color:white':'background:rgba(239,35,60,.12);border-color:rgba(239,35,60,.3);color:var(--red-alert)'?>"><i class="fas fa-<?=$isPresent?'check':'times'?>"></i></div>
                        <div class="att-avatar"><?php if(!empty($s['photo'])): ?><img src="../uploads/students/<?=htmlspecialchars($s['photo'])?>"><?php else: ?><?=strtoupper(substr($s['firstName'],0,1).substr($s['lastName'],0,1))?><?php endif; ?></div>
                        <div style="flex:1"><div style="font-weight:600"><?=htmlspecialchars($s['firstName'].' '.$s['lastName'])?></div><div style="font-size:12px;color:var(--text-muted)"><?=htmlspecialchars($s['rollNumber'])?></div></div>
                        <span class="badge <?=$isPresent?'badge-present':'badge-absent'?>"><i class="fas fa-<?=$isPresent?'check':'times'?>"></i> <?=$isPresent?'Present':'Absent'?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="courseId" value="<?=$selectedCourseId?>">
                        <input type="hidden" name="subjectId" value="<?=$selectedSubjectId?>">
                        <input type="hidden" name="attendanceDate" value="<?=$selectedDate?>">
                        <div class="select-all-bar">
                            <span style="font-size:13px;font-weight:600;color:var(--text-muted)">Click students to mark as Present</span>
                            <div style="display:flex;gap:8px">
                                <button type="button" class="btn btn-success btn-sm" onclick="selectAll()"><i class="fas fa-check-double"></i> All Present</button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="clearAll()"><i class="fas fa-times"></i> All Absent</button>
                            </div>
                        </div>
                        <?php foreach($students as $s): $isPresent=isset($existingAtt[$s['Id']]) && $existingAtt[$s['Id']]==1; ?>
                        <div class="att-student-row <?=$isPresent?'present':''?>" id="row_<?=$s['Id']?>" onclick="toggleStudent(<?=$s['Id']?>)">
                            <input type="hidden" name="studentIds[]" value="<?=$s['Id']?>">
                            <input type="checkbox" class="att-checkbox" id="chk_<?=$s['Id']?>" name="check[]" value="<?=$s['Id']?>" <?=$isPresent?'checked':''?>>
                            <div class="att-toggle" id="tog_<?=$s['Id']?>"><?=$isPresent?'<i class="fas fa-check"></i>':''?></div>
                            <div class="att-avatar"><?php if(!empty($s['photo'])): ?><img src="../uploads/students/<?=htmlspecialchars($s['photo'])?>"><?php else: ?><?=strtoupper(substr($s['firstName'],0,1).substr($s['lastName'],0,1))?><?php endif; ?></div>
                            <div style="flex:1"><div style="font-weight:600"><?=htmlspecialchars($s['firstName'].' '.$s['lastName'])?></div><div style="font-size:12px;color:var(--text-muted)"><?=htmlspecialchars($s['rollNumber'])?></div></div>
                            <div id="status_<?=$s['Id']?>" style="font-size:12px;color:<?=$isPresent?'var(--success)':'var(--text-muted)'?>"><?=$isPresent?'Present':'Absent'?></div>
                        </div>
                        <?php endforeach; ?>
                        <div style="margin-top:20px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
                            <button type="submit" name="saveAttendance" class="btn btn-primary"><i class="fas fa-save"></i> <?=$alreadyTaken?'Update':'Save'?> Attendance</button>
                            <span id="presentCount" style="font-size:13px;color:var(--text-muted)"><?=array_sum(array_map(fn($s)=>isset($existingAtt[$s['Id']]) && $existingAtt[$s['Id']]==1 ? 1 : 0, $students))?> / <?=count($students)?> Present</span>
                        </div>
                    </form>
                <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="cams-card"><div class="card-body"><div class="empty-state">
                <div class="empty-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="empty-title">No students assigned to this subject</div>
                <div class="empty-text"><a href="manageStudents.php?courseId=<?=$selectedCourseId?>" style="color:var(--teal)">Add course students to this subject</a> first.</div>
            </div></div></div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
const today='<?=$today?>'; let totalStudents=<?=count($students)?>; let presentCount=document.querySelectorAll('.att-checkbox:checked').length;
const fpInstance=flatpickr('#datePicker',{dateFormat:'Y-m-d',defaultDate:'<?=$selectedDate?>',maxDate:'today',disableMobile:true,onChange:()=>document.getElementById('filterForm').submit()});
function toggleStudent(id){const chk=document.getElementById('chk_'+id),row=document.getElementById('row_'+id),tog=document.getElementById('tog_'+id),status=document.getElementById('status_'+id);if(!chk)return;chk.checked=!chk.checked;if(chk.checked){row.classList.add('present');tog.innerHTML='<i class="fas fa-check"></i>';if(status){status.textContent='Present';status.style.color='var(--success)';}presentCount++;}else{row.classList.remove('present');tog.innerHTML='';if(status){status.textContent='Absent';status.style.color='var(--text-muted)';}presentCount--;}updateCount();}
function selectAll(){presentCount=0;document.querySelectorAll('.att-checkbox').forEach(chk=>{const id=chk.id.replace('chk_','');chk.checked=true;presentCount++;document.getElementById('row_'+id).classList.add('present');document.getElementById('tog_'+id).innerHTML='<i class="fas fa-check"></i>';const s=document.getElementById('status_'+id);if(s){s.textContent='Present';s.style.color='var(--success)';}});updateCount();}
function clearAll(){presentCount=0;document.querySelectorAll('.att-checkbox').forEach(chk=>{const id=chk.id.replace('chk_','');chk.checked=false;document.getElementById('row_'+id).classList.remove('present');document.getElementById('tog_'+id).innerHTML='';const s=document.getElementById('status_'+id);if(s){s.textContent='Absent';s.style.color='var(--text-muted)';}});updateCount();}
function updateCount(){const el=document.getElementById('presentCount');if(el)el.textContent=presentCount+' / '+totalStudents+' Present';}
document.getElementById('prevDay')?.addEventListener('click',()=>{const c=fpInstance.selectedDates[0]||new Date();c.setDate(c.getDate()-1);fpInstance.setDate(c);document.getElementById('filterForm').submit();});
document.getElementById('nextDay')?.addEventListener('click',()=>{const c=fpInstance.selectedDates[0]||new Date();const n=new Date(c);n.setDate(c.getDate()+1);if(n<=new Date()){fpInstance.setDate(n);document.getElementById('filterForm').submit();}});
document.getElementById('todayBtn')?.addEventListener('click',()=>{fpInstance.setDate(new Date());document.getElementById('filterForm').submit();});
</script>
</body></html>
