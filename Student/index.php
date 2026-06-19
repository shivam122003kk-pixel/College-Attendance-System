<?php
session_start();
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'Student') {
    header("Location: ../index.php"); exit;
}
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';

$studentId = $_SESSION['userId'];
$courseId  = $_SESSION['courseId'];

// Course info
$course = $conn->query("SELECT * FROM tblcourse WHERE Id = $courseId")->fetch_assoc();

// Student info
$student = $conn->query("
    SELECT s.*, d.deptName 
    FROM tblstudent s 
    LEFT JOIN tbldepartment d ON s.deptId = d.Id 
    WHERE s.Id = $studentId
")->fetch_assoc();

// Attendance summary
$att = $conn->query("
    SELECT COUNT(*) as totalDays, SUM(status) as presentDays
    FROM tblattendance WHERE studentId = $studentId AND courseId = $courseId
")->fetch_assoc();

$totalDays   = $att['totalDays'] ?? 0;
$presentDays = $att['presentDays'] ?? 0;
$absentDays  = $totalDays - $presentDays;
$pct         = ($totalDays > 0) ? round($presentDays / $totalDays * 100, 1) : null;
$isLow       = ($pct !== null && $pct < 75);
$safeClass   = $isLow ? 'danger' : 'safe';

// Needed to reach 75%
$needed = 0;
if ($pct !== null && $isLow) {
    // x = classes needed: (presentDays + x) / (totalDays + x) >= 0.75
    $x = 0;
    while (($totalDays + $x) > 0 && round(($presentDays + $x) / ($totalDays + $x) * 100, 1) < 75) {
        $x++;
    }
    $needed = $x;
}

// Recent 5 records
$recent = $conn->query("
    SELECT dateTaken, status FROM tblattendance
    WHERE studentId = $studentId AND courseId = $courseId
    ORDER BY dateTaken DESC LIMIT 7
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Student Dashboard - PIMT</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .meter-ring { --pct: <?= $pct !== null ? round($pct * 3.6) : 0 ?>deg; }
        .info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; }
        .info-box {
            background:var(--glass);
            border:1px solid var(--glass-border);
            border-radius:12px;
            padding:16px;
            text-align:center;
        }
        .info-box-val { font-family:'Outfit',sans-serif; font-size:28px; font-weight:800; }
        .info-box-lbl { font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-top:4px; }
        .course-hero {
            background: linear-gradient(135deg, var(--navy-light), rgba(92,107,192,0.15));
            border:1px solid var(--indigo-light);
            border-radius:16px;
            padding:24px;
            margin-bottom:24px;
            position:relative;
            overflow:hidden;
        }
        .course-hero::before {
            content:'';position:absolute;top:-40px;right:-40px;
            width:160px;height:160px;border-radius:50%;
            background:rgba(92,107,192,0.1);
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
                <h1 class="page-title">My <span>Dashboard</span></h1>
                <nav class="breadcrumb"><i class="fas fa-home"></i><span>Dashboard</span></nav>
            </div>

            <!-- Digital ID Card -->
            <div class="cams-card" style="background:linear-gradient(135deg, var(--navy2), #152744); border:1px solid rgba(79,99,210,0.3); margin-bottom:24px; position:relative; overflow:hidden;">
                <!-- Decorative background elements -->
                <div style="position:absolute;top:-50px;right:-50px;width:150px;height:150px;border-radius:50%;background:rgba(0,180,216,0.1);pointer-events:none;"></div>
                <div style="position:absolute;bottom:-50px;left:-50px;width:150px;height:150px;border-radius:50%;background:rgba(79,99,210,0.1);pointer-events:none;"></div>
                
                <div class="card-body" style="display:flex; flex-wrap:wrap; gap:24px; align-items:center; position:relative; z-index:1;">
                    <!-- Logo & Photo Column -->
                    <div style="display:flex; flex-direction:column; align-items:center; gap:16px;">
                        <?php if(!empty($student['photo'])): ?>
                            <img src="../uploads/students/<?= htmlspecialchars($student['photo']) ?>" style="width:100px; height:100px; border-radius:16px; object-fit:cover; border:3px solid var(--glass-border); box-shadow:0 8px 24px rgba(0,0,0,0.2);">
                        <?php else: ?>
                            <div style="width:100px; height:100px; border-radius:16px; background:linear-gradient(135deg,var(--indigo),var(--teal)); display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:800; color:#fff; border:3px solid var(--glass-border); box-shadow:0 8px 24px rgba(0,0,0,0.2);">
                                <?= strtoupper(substr($_SESSION['firstName'],0,1).substr($_SESSION['lastName'],0,1)) ?>
                            </div>
                        <?php endif; ?>
                        <div style="background:rgba(255,255,255,0.05); padding:4px 12px; border-radius:20px; font-size:11px; color:var(--text-light); font-weight:600; letter-spacing:1px;"><i class="fas fa-check-circle" style="color:var(--success); margin-right:4px;"></i> Active Student</div>
                    </div>
                    
                    <!-- Details Column -->
                    <div style="flex:1; min-width:200px;">
                        <h2 style="font-family:'Outfit',sans-serif; font-size:28px; font-weight:800; color:#fff; margin-bottom:4px;"><?= htmlspecialchars($_SESSION['firstName'].' '.$_SESSION['lastName']) ?></h2>
                        <div style="font-size:14px; color:var(--teal); font-weight:600; margin-bottom:16px; letter-spacing:1px;"><?= htmlspecialchars($student['deptName'] ?? 'General Department') ?></div>
                        
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div>
                                <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px;">Roll Number</div>
                                <div style="font-size:14px; font-weight:600; color:var(--text-light);"><?= htmlspecialchars($_SESSION['rollNumber']) ?></div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px;">Course</div>
                                <div style="font-size:14px; font-weight:600; color:var(--text-light);"><?= htmlspecialchars($course['courseCode']) ?></div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px;">Gender</div>
                                <div style="font-size:14px; font-weight:600; color:var(--text-light);"><?= htmlspecialchars($student['gender'] ?? 'N/A') ?></div>
                            </div>
                            <div>
                                <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px;">Enrolled</div>
                                <div style="font-size:14px; font-weight:600; color:var(--text-light);"><?= date('M Y', strtotime($student['dateCreated'] ?? 'now')) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- QR Code Column -->
                    <div style="background:#fff; padding:12px; border-radius:16px; display:flex; flex-direction:column; align-items:center; gap:8px; box-shadow:0 8px 32px rgba(0,0,0,0.3);">
                        <div id="qrcode"></div>
                        <div style="font-family:monospace; font-size:12px; font-weight:700; color:#000; letter-spacing:2px;"><?= htmlspecialchars($_SESSION['rollNumber']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Warning Banner -->
            <?php if ($isLow): ?>
            <div class="alert alert-danger" style="font-size:14px;padding:16px 20px">
                <i class="fas fa-exclamation-triangle fa-lg"></i>
                <div>
                    <strong><i class="fas fa-exclamation-triangle"></i> Attendance Below Threshold!</strong><br>
                    Your current attendance is <strong><?= $pct ?>%</strong>.
                    You need <strong>75%</strong> minimum.
                    <?php if ($needed > 0): ?>
                    Attend <strong><?= $needed ?> more class(es)</strong> consecutively to reach 75%.
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($pct !== null): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Great!</strong> Your attendance is <strong><?= $pct ?>%</strong> - above the 75% threshold. Keep it up!
            </div>
            <?php endif; ?>

            <!-- Course Hero -->
            <?php if ($course): ?>
            <div class="course-hero">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
                    <div>
                        <div style="font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--teal);margin-bottom:6px">My Enrolled Course</div>
                        <h2 style="font-family:'Outfit',sans-serif;font-size:22px;font-weight:800;color:var(--text-light);margin-bottom:6px"><?= htmlspecialchars($course['courseName']) ?></h2>
                        <p style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($course['description']) ?></p>
                    </div>
                    <div style="text-align:right;flex-shrink:0">
                        <span class="badge badge-indigo" style="font-size:14px;padding:6px 14px"><?= $course['courseCode'] ?></span><br>
                        <span class="badge badge-info" style="margin-top:6px"><?= $course['duration'] ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="info-grid" style="margin-bottom:24px">
                <div class="info-box">
                    <div class="info-box-val" style="color:var(--indigo-bright)"><?= $totalDays ?></div>
                    <div class="info-box-lbl">Total Classes</div>
                </div>
                <div class="info-box">
                    <div class="info-box-val" style="color:var(--success)"><?= $presentDays ?></div>
                    <div class="info-box-lbl">Present</div>
                </div>
                <div class="info-box">
                    <div class="info-box-val" style="color:var(--red-alert)"><?= $absentDays ?></div>
                    <div class="info-box-lbl">Absent</div>
                </div>
                <div class="info-box" style="border-color:<?= $isLow ? 'rgba(239,35,60,0.3)' : 'rgba(6,214,160,0.3)' ?>">
                    <div class="info-box-val" style="color:<?= $isLow ? 'var(--red-alert)' : 'var(--success)' ?>">
                        <?= $pct !== null ? $pct.'%' : 'N/A' ?>
                    </div>
                    <div class="info-box-lbl">Attendance %</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap">
                <!-- Meter -->
                <div class="cams-card">
                    <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie"></i> Attendance Meter</div></div>
                    <div class="card-body">
                        <?php if ($pct !== null): ?>
                        <div class="big-meter">
                            <div class="meter-ring <?= $safeClass ?>" style="--pct:<?= round($pct*3.6) ?>deg">
                                <div class="meter-inner">
                                    <div class="meter-pct"><?= $pct ?>%</div>
                                    <div class="meter-label">Attendance</div>
                                </div>
                            </div>
                            <div style="font-size:13px;color:var(--text-muted);margin-top:12px">
                                Minimum Required: <strong style="color:var(--gold)">75%</strong>
                            </div>
                            <?php if ($isLow && $needed > 0): ?>
                            <div style="margin-top:8px;padding:10px 16px;background:rgba(239,35,60,0.08);border:1px solid rgba(239,35,60,0.2);border-radius:10px;font-size:13px">
                                Attend <strong style="color:var(--red-alert)"><?= $needed ?> more class(es)</strong> to reach 75%
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="padding:40px">
                            <div class="empty-icon"><i class="fas fa-chart-pie"></i></div>
                            <div class="empty-title">No attendance data</div>
                            <div class="empty-text">Attendance has not been taken yet.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Records -->
                <div class="cams-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-history"></i> Recent Classes</div>
                        <a href="viewAttendance.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body" style="padding:0">
                    <?php if ($recent && $recent->num_rows > 0): while($r = $recent->fetch_assoc()): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--glass-border)">
                        <div>
                            <div style="font-weight:600;font-size:13px"><?= date('l', strtotime($r['dateTaken'])) ?></div>
                            <div style="font-size:11px;color:var(--text-muted)"><?= date('d M Y', strtotime($r['dateTaken'])) ?></div>
                        </div>
                        <?php if ($r['status'] == 1): ?>
                        <span class="badge badge-present"><i class="fas fa-check"></i> Present</span>
                        <?php else: ?>
                        <span class="badge badge-absent"><i class="fas fa-times"></i> Absent</span>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; else: ?>
                    <div class="empty-state"><div class="empty-icon"><i class="fas fa-calendar"></i></div><div class="empty-title">No records yet</div></div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<script>


// Generate QR Code locally
new QRCode(document.getElementById("qrcode"), {
    text: "<?= addslashes($_SESSION['rollNumber']) ?>",
    width: 100,
    height: 100,
    colorDark : "#000000",
    colorLight : "#ffffff",
    correctLevel : QRCode.CorrectLevel.H
});
</script>
</body>
</html>
