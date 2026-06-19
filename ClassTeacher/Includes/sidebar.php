<?php
// Shared sidebar for Teacher panel
$currentPage = basename($_SERVER['PHP_SELF']);
function navLink($href, $icon, $label, $current) {
    $active = ($current === $href) ? ' active' : '';
    return "<li class='nav-item'><a class='nav-link{$active}' href='{$href}'><i class='fas fa-fw fa-{$icon}'></i><span>{$label}</span></a></li>";
}
?>
<div id="sidebar">
    <div class="sidebar-brand">
        <a href="index.php" style="display:flex;align-items:center;gap:12px;text-decoration:none;">
            <img src="../img/pimt-logo.png" style="width:36px;height:36px;border-radius:8px;">
            <div class="sidebar-brand-text">PIMT<small>Teacher Panel</small></div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <p class="sidebar-section-label">Overview</p>
        <ul style="list-style:none;padding:0">
            <?= navLink('index.php','tachometer-alt','Dashboard',$currentPage) ?>
        </ul>
        <p class="sidebar-section-label">My Work</p>
        <ul style="list-style:none;padding:0">
            <?= navLink('myCourses.php','book-open','My Courses',$currentPage) ?>
            <?= navLink('myStudents.php','user-graduate','My Students',$currentPage) ?>
            <?= navLink('addStudents.php','user-plus','Add Students',$currentPage) ?>
            <?= navLink('takeAttendance.php','calendar-check','Take Attendance',$currentPage) ?>
            <?= navLink('viewAttendance.php','chart-bar','View Attendance',$currentPage) ?>
            <?= navLink('studentAnalytics.php','chart-pie','Student Analytics',$currentPage) ?>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <?= navLink('profile.php','user-circle','My Profile',$currentPage) ?>
        <a class="logout-btn" href="logout.php">
            <i class="fas fa-power-off"></i> Logout
        </a>
    </div>
</div>
