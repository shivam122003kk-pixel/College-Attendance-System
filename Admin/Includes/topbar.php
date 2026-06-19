<?php
// Shared topbar for Director panel
if (!isset($_SESSION['userId'])) {
    header("Location: ../index.php");
    exit;
}
$firstName = $_SESSION['firstName'] ?? 'Director';
$lastName = $_SESSION['lastName'] ?? '';
$initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));

// Fetch director photo from DB
$photo = '';
$email = '';
if (isset($conn)) {
    $result = $conn->query("SELECT photo, emailAddress FROM tbldirector WHERE Id=" . (int) $_SESSION['userId']);
    if ($result && $result->num_rows > 0) {
        $dirRow = $result->fetch_assoc();
        $photo = $dirRow['photo'] ?? '';
        $email = $dirRow['emailAddress'] ?? '';
    }
}
$photoHtml = $photo
    ? "<img src=\"../uploads/directors/" . htmlspecialchars($photo) . "\" alt=\"\">"
    : $initials;
?>
<!-- Sidebar Overlay for mobile -->
<div id="sidebarOverlay"></div>

<div id="topbar">
    <div class="topbar-left">
        <button class="topbar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <a href="index.php" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
            <img src="../img/pimt-logo.png" style="width:28px;height:28px;border-radius:6px;object-fit:cover;"
                alt="PIMT">
            <span class="topbar-title">Punjab Institute of Management &amp; Technology</span>
        </a>
    </div>
    <div class="topbar-right">
        <span class="topbar-date"><i class="fas fa-calendar-alt"
                style="margin-right:5px;color:#5c6bc0"></i><?= date('D, d M Y') ?></span>
        <div class="topbar-user" id="profileTrigger">
            <div class="topbar-avatar"><?= $photoHtml ?></div>
            <div>
                <div class="topbar-name"><?= htmlspecialchars($firstName . ' ' . $lastName) ?></div>
                <div class="topbar-role">Director</div>
            </div>
            <i class="fas fa-chevron-down" style="font-size:10px;color:var(--text-muted);margin-left:4px;"></i>

            <!-- Profile Dropdown -->
            <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-dropdown-header">
                    <?php if ($photo): ?>
                        <img src="../uploads/directors/<?= htmlspecialchars($photo) ?>" class="profile-dropdown-avatar"
                            alt="">
                    <?php else: ?>
                        <div class="profile-dropdown-avatar"
                            style="background:linear-gradient(135deg,var(--indigo),var(--teal));display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:#fff;">
                            <?= $initials ?></div>
                    <?php endif; ?>
                    <div class="profile-dropdown-info">
                        <h4><?= htmlspecialchars($firstName . ' ' . $lastName) ?></h4>
                        <p><?= htmlspecialchars($email) ?></p>
                        <p style="color:var(--gold);margin-top:2px;">Director &middot; Full Access</p>
                    </div>
                </div>
                <div class="profile-dropdown-menu">
                    <a href="profile.php" class="profile-dropdown-item"><i class="fas fa-user-circle"></i> My
                        Profile</a>
                    <div class="profile-dropdown-divider"></div>
                    <a href="logout.php" class="profile-dropdown-item" style="color:#ff6b7a;"><i
                            class="fas fa-power-off" style="color:#ff6b7a;"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        var toggle = document.getElementById('sidebarToggle');
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        if (toggle && sidebar) {
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('active');
            });
        }
        if (overlay) overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
        var profileTrigger = document.getElementById('profileTrigger');
        var profileDropdown = document.getElementById('profileDropdown');
        if (profileTrigger && profileDropdown) {
            profileTrigger.addEventListener('click', function (e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('open');
            });
            document.addEventListener('click', function () {
                profileDropdown.classList.remove('open');
            });
            profileDropdown.addEventListener('click', function (e) { e.stopPropagation(); });
        }
    })();
</script>