<?php
// HOD Sidebar
$currentPage = basename($_SERVER['PHP_SELF']);
function hodNav($href, $icon, $label, $current) {
    $active = ($current === $href) ? ' active' : '';
    return "<li class='nav-item'><a class='nav-link{$active}' href='{$href}'><i class='fas fa-fw fa-{$icon}'></i><span>{$label}</span></a></li>";
}
?>
<div id="sidebar">
    <div class="sidebar-brand">
        <a href="index.php" style="display:flex;align-items:center;gap:12px;text-decoration:none;">
            <img src="../img/pimt-logo.png" style="width:36px;height:36px;border-radius:8px;">
            <div class="sidebar-brand-text">PIMT<small style="color:#00b4d8">HOD Panel</small></div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <p class="sidebar-section-label">Overview</p>
        <ul style="list-style:none;padding:0">
            <?= hodNav('index.php','tachometer-alt','Dashboard',$currentPage) ?>
        </ul>
        <p class="sidebar-section-label">Department</p>
        <ul style="list-style:none;padding:0">
            <?= hodNav('myDepartment.php','building','My Department',$currentPage) ?>
            <?= hodNav('manageTeachers.php','chalkboard-teacher','Manage Teachers',$currentPage) ?>
            <?= hodNav('manageStudents.php','user-graduate','Manage Students',$currentPage) ?>
            <?= hodNav('assignCourse.php','link','Assign Courses',$currentPage) ?>
            <?= hodNav('takeAttendance.php','calendar-check','Take Student Attendance',$currentPage) ?>
            <?= hodNav('takeTeacherAttendance.php','clipboard-list','Teacher Attendance',$currentPage) ?>
        </ul>
        <p class="sidebar-section-label">Records & Analytics</p>
        <ul style="list-style:none;padding:0">
            <?= hodNav('viewAttendance.php','calendar-check','Student Attendance',$currentPage) ?>
            <?= hodNav('studentAnalytics.php','chart-pie','Student Analytics',$currentPage) ?>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <?= hodNav('profile.php','user-circle','My Profile',$currentPage) ?>
        <a class="logout-btn" href="logout.php"><i class="fas fa-power-off"></i> Logout</a>
    </div>
</div>
