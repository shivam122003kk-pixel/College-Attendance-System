<?php
$currentPage = basename($_SERVER['PHP_SELF']);
if (!function_exists('navLink')) {
    function navLink($href, $icon, $label, $current)
    {
        $a = ($current === $href) ? ' active' : '';
        return "<li class='nav-item'><a class='nav-link{$a}' href='{$href}'><i class='fas fa-fw fa-{$icon}'></i><span>{$label}</span></a></li>";
    }
}
?>
<div id="sidebar">
    <div class="sidebar-brand">
        <a href="index.php" style="display:flex;align-items:center;gap:12px;text-decoration:none;">
            <img src="../img/pimt-logo.png" style="width:36px;height:36px;border-radius:8px;">
            <div class="sidebar-brand-text">PIMT<small>Director Panel</small></div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <p class="sidebar-section-label">Overview</p>
        <ul style="list-style:none;padding:0">
            <?= navLink('index.php', 'tachometer-alt', 'Dashboard', $currentPage) ?>
        </ul>
        <p class="sidebar-section-label">Management</p>
        <ul style="list-style:none;padding:0">
            <?= navLink('manageDepartments.php', 'building', 'Departments', $currentPage) ?>
            <?= navLink('manageHODs.php', 'sitemap', 'Manage HODs', $currentPage) ?>
            <?= navLink('manageTeachers.php', 'chalkboard-teacher', 'Manage Teachers', $currentPage) ?>
            <?= navLink('manageCourses.php', 'book-open', 'Manage Courses', $currentPage) ?>
            <?= navLink('manageSubjects.php', 'book', 'Manage Subjects', $currentPage) ?>
            <?= navLink('assignCourse.php', 'link', 'Assign Courses', $currentPage) ?>
        </ul>
        <p class="sidebar-section-label">Records</p>
        <ul style="list-style:none;padding:0">
            <?= navLink('viewAllStudents.php', 'user-graduate', 'All Students', $currentPage) ?>
            <?= navLink('viewAllAttendance.php', 'calendar-check', 'All Attendance', $currentPage) ?>
            <?= navLink('studentAnalytics.php', 'chart-pie', 'Student Analytics', $currentPage) ?>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <?= navLink('profile.php', 'user-circle', 'My Profile', $currentPage) ?>
        <a class="logout-btn" href="logout.php"><i class="fas fa-power-off"></i> Logout</a>
    </div>
</div>
