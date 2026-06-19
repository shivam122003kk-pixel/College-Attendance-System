<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
$teacherId = (int)$_SESSION['userId'];

$msg = ''; $msgType = '';
$today = date('Y-m-d');

// Selected date - default to today, clamp to max today
$selectedDate = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : $today;
if ($selectedDate > $today) $selectedDate = $today; // never allow future

// Teacher's courses
$myCourses = $conn->query("
    SELECT c.Id, c.courseName, c.courseCode
    FROM tblteacher_course tc JOIN tblcourse c ON tc.courseId = c.Id
    WHERE tc.teacherId = $teacherId ORDER BY c.courseName
");

$selectedCourseId = isset($_GET['courseId']) ? (int)$_GET['courseId'] : 0;
if (!$selectedCourseId && $myCourses->num_rows > 0) {
    $myCourses->data_seek(0);
    $first = $myCourses->fetch_assoc();
    $selectedCourseId = $first['Id'];
    $myCourses->data_seek(0);
}

$mySubjects = $selectedCourseId ? $conn->query("
    SELECT s.Id,s.subjectName,s.subjectCode,s.semester
    FROM tblteacher_subject ts
    JOIN tblsubject s ON ts.subjectId=s.Id
    WHERE ts.teacherId=$teacherId AND s.courseId=$selectedCourseId
    ORDER BY s.subjectName
") : false;
$selectedSubjectId = isset($_GET['subjectId']) ? (int)$_GET['subjectId'] : 0;
if (!$selectedSubjectId && $mySubjects && $mySubjects->num_rows > 0) {
    $mySubjects->data_seek(0);
    $firstSubject = $mySubjects->fetch_assoc();
    $selectedSubjectId = (int)$firstSubject['Id'];
    $mySubjects->data_seek(0);
}

// Check if attendance already taken for selected date + course
$alreadyTaken = false;
if ($selectedCourseId) {
    $chk = $conn->query("SELECT Id FROM tblattendance WHERE courseId=$selectedCourseId AND subjectId=$selectedSubjectId AND takenByTeacherId=$teacherId AND dateTaken='$selectedDate' LIMIT 1");
    $alreadyTaken = ($chk && $chk->num_rows > 0);
}

// Save attendance
if (isset($_POST['saveAttendance'])) {
    $cid        = (int)$_POST['courseId'];
    $subjectId  = (int)($_POST['subjectId'] ?? 0);
    $postDate   = $_POST['attendanceDate'];
    if ($postDate > $today) $postDate = $today; // safety clamp
    $studentIds = $_POST['studentIds'] ?? [];
    $checked    = $_POST['check'] ?? [];

    $v = $conn->prepare("
        SELECT tc.Id FROM tblteacher_course tc
        LEFT JOIN tblteacher_subject ts ON ts.teacherId=tc.teacherId AND ts.subjectId=?
        WHERE tc.teacherId=? AND tc.courseId=? AND (?=0 OR ts.Id IS NOT NULL)
    ");
    $v->bind_param("iiii", $subjectId, $teacherId, $cid, $subjectId);
    $v->execute();
    if ($v->get_result()->num_rows > 0) {
        foreach ($studentIds as $sid) {
            $sid    = (int)$sid;
            $status = in_array((string)$sid, $checked) ? 1 : 0;
            $conn->query("INSERT INTO tblattendance (studentId,courseId,subjectId,status,dateTaken,takenByTeacherId)
                          VALUES ($sid,$cid,$subjectId,$status,'$postDate',$teacherId)
                          ON DUPLICATE KEY UPDATE status=$status");
        }
        $msg = ($alreadyTaken ? "Attendance updated for " : "Attendance saved for ") . date('d M Y', strtotime($postDate)) . "!";
        $msgType = 'success';
        $alreadyTaken = true;
        $selectedDate = $postDate;
    }
}

// Fetch students for selected course
$students = [];
if ($selectedCourseId) {
    $res = $conn->query("
        SELECT DISTINCT s.*
        FROM tblstudent s
        LEFT JOIN tblstudent_course sc ON s.Id=sc.studentId
        LEFT JOIN tblstudent_subject ss ON s.Id=ss.studentId
        WHERE (s.courseId=$selectedCourseId OR sc.courseId=$selectedCourseId)
          AND ($selectedSubjectId=0 OR ss.subjectId=$selectedSubjectId)
        ORDER BY s.firstName
    ");
    while ($r = $res->fetch_assoc()) $students[] = $r;
}

// Get existing attendance for selected date
$existingAtt = [];
if ($selectedCourseId) {
    $r = $conn->query("SELECT studentId,status FROM tblattendance WHERE courseId=$selectedCourseId AND subjectId=$selectedSubjectId AND dateTaken='$selectedDate'");
    while ($row = $r->fetch_assoc()) $existingAtt[$row['studentId']] = $row['status'];
}

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
    <title>Take Attendance - PIMT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <!-- Flatpickr premium calendar -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <style>
        .date-picker-bar {
            display:flex; align-items:center; gap:14px;
            padding:18px 24px;
            background:var(--glass);
            border:1px solid var(--glass-border);
            border-radius:14px;
            margin-bottom:20px;
            flex-wrap:wrap;
        }
        .date-display {
            font-family:'Outfit',sans-serif;
            font-size:18px;
            font-weight:700;
            color:var(--text-light);
        }
        .date-badge {
            padding:4px 12px;
            border-radius:20px;
            font-size:11px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:1px;
        }
        .date-badge.today { background:rgba(0,180,216,0.15); color:var(--teal); border:1px solid rgba(0,180,216,0.3); }
        .date-badge.past  { background:rgba(252,163,17,0.12); color:var(--warning); border:1px solid rgba(252,163,17,0.25); }
        .date-input-wrap { position:relative; }
        /* Flatpickr overrides for premium dark look */
        .flatpickr-input {
            padding:10px 40px 10px 14px !important;
            background:rgba(255,255,255,0.06) !important;
            border:1.5px solid rgba(255,255,255,0.12) !important;
            border-radius:10px !important;
            color:var(--text-light) !important;
            font-family:'Inter',sans-serif !important;
            font-size:14px !important;
            cursor:pointer !important;
            min-width:170px;
            transition:all 0.25s;
            outline:none;
        }
        .flatpickr-input:focus,.flatpickr-input.active {
            border-color:var(--indigo-bright) !important;
            box-shadow:0 0 0 3px rgba(92,107,192,0.2) !important;
        }
        .flatpickr-calendar {
            background:#131e35 !important;
            border:1px solid rgba(255,255,255,.1) !important;
            box-shadow:0 24px 60px rgba(0,0,0,.6) !important;
            border-radius:16px !important;
            font-family:'Inter',sans-serif !important;
        }
        .flatpickr-day.selected,.flatpickr-day.selected:hover {
            background:var(--indigo) !important;
            border-color:var(--indigo) !important;
        }
        .flatpickr-day:hover { background:rgba(92,107,192,.25) !important; }
        .flatpickr-day.today { border-color:var(--teal) !important; color:var(--teal) !important; }
        .flatpickr-day.today.selected { color:#fff !important; }
        .flatpickr-months { padding:8px 0 4px; }
        .flatpickr-month,.flatpickr-weekday { color:var(--text-light) !important; }
        .flatpickr-current-month,.flatpickr-current-month input { color:var(--text-light) !important; font-weight:700; font-size:15px !important; }
        .flatpickr-prev-month svg,.flatpickr-next-month svg { fill:rgba(255,255,255,.6) !important; }
        .flatpickr-prev-month:hover svg,.flatpickr-next-month:hover svg { fill:#fff !important; }
        .flatpickr-day.flatpickr-disabled { color:rgba(255,255,255,.15) !important; }
        .numInputWrapper:hover { background:transparent !important; }
        .flatpickr-input-wrap { position:relative; }
        .fp-cal-icon { position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--indigo-bright);font-size:14px;pointer-events:none; }
        .nav-day-btn {
            width:34px; height:34px;
            border-radius:8px;
            border:1px solid rgba(255,255,255,0.1);
            background:rgba(255,255,255,0.04);
            color:var(--text-muted);
            cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            font-size:14px;
            transition:all 0.2s;
        }
        .nav-day-btn:hover:not(:disabled) { background:var(--indigo-light); border-color:var(--indigo); color:var(--indigo-bright); }
        .nav-day-btn:disabled { opacity:0.3; cursor:not-allowed; }

        /* student rows */
        .att-student-row {
            display:flex; align-items:center; gap:14px;
            padding:14px 16px;
            border:1px solid var(--glass-border);
            border-radius:12px;
            margin-bottom:10px;
            background:var(--glass);
            transition:all 0.2s ease;
            cursor:pointer;
            user-select:none;
        }
        .att-student-row:hover { border-color:var(--indigo); background:var(--indigo-light); }
        .att-student-row.present { border-color:rgba(6,214,160,0.4); background:rgba(6,214,160,0.06); }
        .att-checkbox { display:none; }
        .att-toggle {
            width:26px; height:26px;
            border-radius:7px;
            border:2px solid rgba(255,255,255,0.2);
            display:flex; align-items:center; justify-content:center;
            flex-shrink:0;
            transition:all 0.2s;
            font-size:12px;
        }
        .att-student-row.present .att-toggle { background:var(--success); border-color:var(--success); color:white; }
        .att-avatar {
            width:40px; height:40px;
            border-radius:50%;
            background:linear-gradient(135deg,var(--indigo),var(--teal));
            display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:14px; color:white;
            flex-shrink:0;
        }
        .select-all-bar {
            display:flex; align-items:center; justify-content:space-between;
            padding:10px 16px;
            background:rgba(255,255,255,0.03);
            border:1px solid var(--glass-border);
            border-radius:10px;
            margin-bottom:14px;
        }
        .readonly-notice {
            display:flex; align-items:center; gap:10px;
            padding:12px 16px;
            background:rgba(252,163,17,0.08);
            border:1px solid rgba(252,163,17,0.2);
            border-radius:10px;
            font-size:13px;
            color:var(--warning);
            margin-bottom:16px;
        }
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
                <h1 class="page-title">Take <span>Attendance</span></h1>
                <nav class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <i class="fas fa-chevron-right"></i><span>Attendance</span>
                </nav>
            </div>

            <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>">
                <i class="fas fa-<?= $msgType==='success'?'check-circle':'times-circle' ?>"></i> <?= $msg ?>
            </div>
            <?php endif; ?>

            <?php if ($myCourses->num_rows === 0): ?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No courses assigned. Contact the Director.</div>
            <?php else: ?>

            <!-- Course + Date Selector -->
            <div class="cams-card">
                <div class="card-body" style="padding:18px 24px">
                    <form method="GET" id="filterForm" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">

                        <!-- Course -->
                        <div style="display:flex;flex-direction:column;gap:5px">
                            <label style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted)">Course</label>
                            <select name="courseId" class="form-input" style="min-width:260px" onchange="this.form.submit()">
                                <?php $myCourses->data_seek(0); while($c=$myCourses->fetch_assoc()): ?>
                                <option value="<?=$c['Id']?>" <?=$selectedCourseId==$c['Id']?'selected':''?>>[<?=$c['courseCode']?>] <?=htmlspecialchars($c['courseName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:5px">
                            <label style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted)">Subject</label>
                            <select name="subjectId" class="form-input" style="min-width:240px" onchange="this.form.submit()">
                                <option value="0">General Course Attendance</option>
                                <?php if($mySubjects): $mySubjects->data_seek(0); while($sub=$mySubjects->fetch_assoc()): ?>
                                <option value="<?=$sub['Id']?>" <?=$selectedSubjectId==$sub['Id']?'selected':''?>>[<?=$sub['subjectCode']?>] <?=htmlspecialchars($sub['subjectName'])?> <?=htmlspecialchars($sub['semester'] ?: '')?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <!-- Prev Day -->
                        <div style="margin-top:18px">
                            <button type="button" class="nav-day-btn" id="prevDay" title="Previous day"><i class="fas fa-chevron-left"></i></button>
                        </div>

                        <!-- Date picker -->
                        <div style="display:flex;flex-direction:column;gap:5px">
                            <label style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted)">Attendance Date</label>
                            <div style="position:relative">
                                <input type="text" name="date" id="datePicker"
                                       value="<?=$selectedDate?>"
                                       class="flatpickr-input"
                                       readonly="readonly"
                                       placeholder="Select date...">
                                <i class="fas fa-calendar-alt fp-cal-icon"></i>
                            </div>
                        </div>

                        <!-- Next Day (disabled if today) -->
                        <div style="margin-top:18px">
                            <button type="button" class="nav-day-btn" id="nextDay"
                                    <?=$selectedDate>=$today?'disabled':''?>
                                    title="Next day"><i class="fas fa-chevron-right"></i></button>
                        </div>

                        <!-- Today shortcut -->
                        <div style="margin-top:18px">
                            <button type="button" class="btn btn-sm btn-primary" id="todayBtn" <?=$isToday?'disabled':''?>>
                                <i class="fas fa-calendar-day"></i> Today
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Date Display Bar -->
            <div class="date-picker-bar">
                <i class="fas fa-calendar-alt" style="color:var(--indigo-bright);font-size:20px"></i>
                <div>
                    <div class="date-display"><?=$displayDate?></div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
                        <?= $isToday ? "Today's attendance session" : "Historical attendance session" ?>
                    </div>
                </div>
                <span class="date-badge <?=$isToday?'today':'past'?>">
                    <?=$isToday?'Today':'Past Date'?>
                </span>
                <?php if ($alreadyTaken): ?>
                <span class="badge badge-present" style="margin-left:auto"><i class="fas fa-check-circle"></i> Already Taken</span>
                <?php else: ?>
                <span class="badge badge-warning" style="margin-left:auto"><i class="fas fa-clock"></i> Pending</span>
                <?php endif; ?>
            </div>

            <?php if (count($students) > 0): ?>
            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-users"></i> Students (<?=count($students)?>)</div>
                    <span style="font-size:12px;color:var(--text-muted)">
                        <?= $alreadyTaken ? '<span style="color:var(--success)"><i class="fas fa-edit"></i> Edit Attendance</span>' : '<span style="color:var(--teal)"><i class="fas fa-edit"></i> Mark Attendance</span>' ?>
                    </span>
                </div>
                <div class="card-body">

                    <?php if (false): ?>
                    <!-- READ ONLY view -->
                    <div class="readonly-notice">
                        <i class="fas fa-info-circle fa-lg"></i>
                        Attendance for <strong><?=$displayDate?></strong> has already been recorded. Showing saved records below.
                    </div>
                    <div>
                    <?php foreach ($students as $s):
                        $isPresent = isset($existingAtt[$s['Id']]) && $existingAtt[$s['Id']] == 1;
                    ?>
                    <div class="att-student-row <?=$isPresent?'present':''?>" style="cursor:default">
                        <div class="att-toggle" style="<?=$isPresent?'background:var(--success);border-color:var(--success);color:white':'background:rgba(239,35,60,0.12);border-color:rgba(239,35,60,0.3);color:var(--red-alert)'?>">
                            <i class="fas fa-<?=$isPresent?'check':'times'?>"></i>
                        </div>
                        <div class="att-avatar" style="<?=$isPresent?'':'background:linear-gradient(135deg,#c9184a,#6d0025)'?>">
                            <?=strtoupper(substr($s['firstName'],0,1).substr($s['lastName'],0,1))?>
                        </div>
                        <div style="flex:1">
                            <div style="font-weight:600"><?=htmlspecialchars($s['firstName'].' '.$s['lastName'])?></div>
                            <div style="font-size:12px;color:var(--text-muted)"><?=$s['rollNumber']?></div>
                        </div>
                        <span class="badge <?=$isPresent?'badge-present':'badge-absent'?>">
                            <i class="fas fa-<?=$isPresent?'check':'times'?>"></i> <?=$isPresent?'Present':'Absent'?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    </div>

                    <?php else: ?>
                    <!-- EDITABLE form -->
                    <form method="POST">
                        <input type="hidden" name="courseId" value="<?=$selectedCourseId?>">
                        <input type="hidden" name="subjectId" value="<?=$selectedSubjectId?>">
                        <input type="hidden" name="attendanceDate" value="<?=$selectedDate?>">

                        <div class="select-all-bar">
                            <span style="font-size:13px;font-weight:600;color:var(--text-muted)">
                                <i class="fas fa-hand-pointer" style="margin-right:6px;color:var(--indigo-bright)"></i>
                                Click students to mark as Present
                            </span>
                            <div style="display:flex;gap:8px">
                                <button type="button" class="btn btn-success btn-sm" onclick="selectAll()">
                                    <i class="fas fa-check-double"></i> All Present
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="clearAll()">
                                    <i class="fas fa-times"></i> All Absent
                                </button>
                            </div>
                        </div>

                        <div id="studentList">
                        <?php foreach ($students as $s):
                            $isPresent = isset($existingAtt[$s['Id']]) && $existingAtt[$s['Id']] == 1;
                        ?>
                        <div class="att-student-row <?=$isPresent?'present':''?>" id="row_<?=$s['Id']?>" onclick="toggleStudent(<?=$s['Id']?>)">
                            <input type="hidden" name="studentIds[]" value="<?=$s['Id']?>">
                            <input type="checkbox" class="att-checkbox" id="chk_<?=$s['Id']?>" name="check[]" value="<?=$s['Id']?>" <?=$isPresent?'checked':''?>>
                            <div class="att-toggle" id="tog_<?=$s['Id']?>"><?=$isPresent?'<i class="fas fa-check" style="font-size:12px"></i>':''?></div>
                            <div class="att-avatar"><?=strtoupper(substr($s['firstName'],0,1).substr($s['lastName'],0,1))?></div>
                            <div style="flex:1">
                                <div style="font-weight:600"><?=htmlspecialchars($s['firstName'].' '.$s['lastName'])?></div>
                                <div style="font-size:12px;color:var(--text-muted)"><?=$s['rollNumber']?></div>
                            </div>
                            <div id="status_<?=$s['Id']?>" style="font-size:12px;color:<?=$isPresent?'var(--success)':'var(--text-muted)'?>"><?=$isPresent?'Present':'Absent'?></div>
                        </div>
                        <?php endforeach; ?>
                        </div>

                        <div style="margin-top:20px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
                            <button type="submit" name="saveAttendance" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?=$alreadyTaken?'Update':'Save'?> Attendance for <?=date('d M Y',strtotime($selectedDate))?>
                            </button>
                            <span id="presentCount" style="font-size:13px;color:var(--text-muted)"><?=array_sum(array_map(fn($s)=>isset($existingAtt[$s['Id']]) && $existingAtt[$s['Id']]==1 ? 1 : 0, $students))?> / <?=count($students)?> Present</span>
                        </div>
                    </form>
                    <?php endif; ?>

                </div>
            </div>
            <?php else: ?>
            <div class="cams-card"><div class="card-body"><div class="empty-state">
                <div class="empty-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="empty-title">No students in this course</div>
                <div class="empty-text"><a href="addStudents.php?courseId=<?=$selectedCourseId?>" style="color:var(--teal)">Add students</a> to take attendance.</div>
            </div></div></div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
const today     = '<?=$today?>';
let totalStudents = <?=count($students)?>;
let presentCount  = document.querySelectorAll('.att-checkbox:checked').length;

// Initialize Flatpickr
const fpInstance = flatpickr('#datePicker', {
    dateFormat: 'Y-m-d',
    defaultDate: '<?=$selectedDate?>',
    maxDate: 'today',
    disableMobile: true,
    theme: 'dark',
    onChange: function(selectedDates, dateStr) {
        document.getElementById('filterForm').submit();
    }
});

/* â”€â”€ student toggle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
function toggleStudent(id) {
    const chk    = document.getElementById('chk_' + id);
    const row    = document.getElementById('row_' + id);
    const tog    = document.getElementById('tog_' + id);
    const status = document.getElementById('status_' + id);
    if (!chk) return;
    chk.checked = !chk.checked;
    if (chk.checked) {
        row.classList.add('present');
        tog.innerHTML = '<i class="fas fa-check" style="font-size:12px"></i>';
        if (status) { status.textContent = 'Present'; status.style.color = 'var(--success)'; }
        presentCount++;
    } else {
        row.classList.remove('present');
        tog.innerHTML = '';
        if (status) { status.textContent = 'Absent'; status.style.color = 'var(--text-muted)'; }
        presentCount--;
    }
    updateCount();
}
function selectAll() {
    presentCount = 0;
    document.querySelectorAll('.att-checkbox').forEach(chk => {
        const id = chk.id.replace('chk_','');
        chk.checked = true; presentCount++;
        document.getElementById('row_'+id).classList.add('present');
        document.getElementById('tog_'+id).innerHTML='<i class="fas fa-check" style="font-size:12px"></i>';
        const s=document.getElementById('status_'+id);
        if(s){s.textContent='Present';s.style.color='var(--success)';}
    });
    updateCount();
}
function clearAll() {
    presentCount = 0;
    document.querySelectorAll('.att-checkbox').forEach(chk => {
        const id = chk.id.replace('chk_','');
        chk.checked = false;
        document.getElementById('row_'+id).classList.remove('present');
        document.getElementById('tog_'+id).innerHTML='';
        const s=document.getElementById('status_'+id);
        if(s){s.textContent='Absent';s.style.color='var(--text-muted)';}
    });
    updateCount();
}
function updateCount() {
    const el = document.getElementById('presentCount');
    if (el) el.textContent = presentCount + ' / ' + totalStudents + ' Present';
}

/* â”€â”€ date navigation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
const datePicker = document.getElementById('datePicker');
const prevBtn    = document.getElementById('prevDay');
const nextBtn    = document.getElementById('nextDay');
const todayBtn   = document.getElementById('todayBtn');

function navigateDate(offset) {
    const d = new Date(datePicker.value + 'T00:00:00');
    d.setDate(d.getDate() + offset);
    const newDate = d.toISOString().split('T')[0];
    if (newDate > today) return;          // block future
    datePicker.value = newDate;
    document.getElementById('filterForm').submit();
}

if (prevBtn) prevBtn.addEventListener('click', () => {
    const cur = fpInstance.selectedDates[0] || new Date();
    cur.setDate(cur.getDate() - 1);
    fpInstance.setDate(cur);
    document.getElementById('filterForm').submit();
});
if (nextBtn) nextBtn.addEventListener('click', () => {
    const cur = fpInstance.selectedDates[0] || new Date();
    const nxt = new Date(cur); nxt.setDate(cur.getDate() + 1);
    if (nxt <= new Date()) { fpInstance.setDate(nxt); document.getElementById('filterForm').submit(); }
});
if (todayBtn) todayBtn.addEventListener('click', () => {
    fpInstance.setDate(new Date());
    document.getElementById('filterForm').submit();
});


</script>
</body>
</html>
