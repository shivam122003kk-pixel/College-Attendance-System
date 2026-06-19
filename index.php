<?php
session_start();
include 'Includes/dbcon.php';

$error = "";

if (isset($_POST['login'])) {
    $role = $_POST['userType'];
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($role === "Director") {
        $stmt = $conn->prepare("SELECT * FROM tbldirector WHERE emailAddress = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($password === $row['password']) {
                $_SESSION['userId'] = $row['Id'];
                $_SESSION['firstName'] = $row['firstName'];
                $_SESSION['lastName'] = $row['lastName'];
                $_SESSION['emailAddress'] = $row['emailAddress'];
                $_SESSION['role'] = 'Director';
                header("Location: Admin/index.php");
                exit;
            }
        }
        $error = "Invalid credentials!";

    } elseif ($role === "HOD") {
        $stmt = $conn->prepare("SELECT * FROM tblhod WHERE emailAddress = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($password === $row['password']) {
                $_SESSION['userId'] = $row['Id'];
                $_SESSION['firstName'] = $row['firstName'];
                $_SESSION['lastName'] = $row['lastName'];
                $_SESSION['emailAddress'] = $row['emailAddress'];
                $_SESSION['deptId'] = $row['deptId'];
                $_SESSION['role'] = 'HOD';
                header("Location: HOD/index.php");
                exit;
            }
        }
        $error = "Invalid credentials!";

    } elseif ($role === "Teacher") {
        $stmt = $conn->prepare("SELECT * FROM tblteacher WHERE emailAddress = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($password === $row['password']) {
                $_SESSION['userId'] = $row['Id'];
                $_SESSION['firstName'] = $row['firstName'];
                $_SESSION['lastName'] = $row['lastName'];
                $_SESSION['emailAddress'] = $row['emailAddress'];
                $_SESSION['role'] = 'Teacher';
                header("Location: ClassTeacher/index.php");
                exit;
            }
        }
        $error = "Invalid credentials!";

    } elseif ($role === "Student") {
        $stmt = $conn->prepare("SELECT * FROM tblstudent WHERE rollNumber = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($password === $row['password']) {
                $_SESSION['userId'] = $row['Id'];
                $_SESSION['firstName'] = $row['firstName'];
                $_SESSION['lastName'] = $row['lastName'];
                $_SESSION['rollNumber'] = $row['rollNumber'];
                $_SESSION['courseId'] = $row['courseId'];
                $_SESSION['role'] = 'Student';
                header("Location: Student/index.php");
                exit;
            }
        }
        $error = "Invalid credentials!";
    } else {
        $error = "Please select your role!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/pimt-logo.png">
    <meta name="description" content="PIMT Punjab Institute of Management & Technology - Secure login portal.">
    <title>PIMT - College Attendance System</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0a1628">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        :root {
            --navy: #0a1628;
            --navy2: #101f35;
            --indigo: #315f9f;
            --indigo2: #527dbb;
            --teal: #1aa6a8;
            --gold: #d8aa43;
            --red: #ef233c;
            --success: #22b07d;
            --warning: #c98322;
            --txt: #e0e7ff;
            --muted: #96a3b8;
            --glass: rgba(255, 255, 255, 0.05);
            --gb: rgba(255, 255, 255, 0.09);
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            background: url('img/login-bg.png') center/cover no-repeat;
            background-attachment: fixed;
            overflow: hidden;
            position: relative;
            animation: backgroundDrift 12s ease-in-out infinite alternate;
        }

        @keyframes backgroundDrift {
            from {
                background-size: 100% auto;
                background-position: center center;
            }

            to {
                background-size: 106% auto;
                background-position: center top;
            }
        }

        /* dark overlay to ensure readability */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                linear-gradient(115deg, rgba(7, 15, 29, .74), rgba(9, 28, 48, .42) 46%, rgba(7, 15, 29, .76)),
                rgba(10, 22, 40, .18);
            pointer-events: none;
            z-index: 0;
            animation: backgroundFocus 1.4s ease forwards;
        }

        /* animated gradient on top */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse 70% 50% at 18% -10%, rgba(49, 95, 159, .32) 0%, transparent 62%),
                radial-gradient(ellipse 55% 60% at 92% 106%, rgba(26, 166, 168, .22) 0%, transparent 65%);
            pointer-events: none;
            z-index: 1;
            opacity: 0;
            animation: atmosphereIn 1.6s ease forwards .35s;
        }

        @keyframes backgroundFocus {
            from {
                backdrop-filter: blur(5px);
                opacity: .35;
            }

            to {
                backdrop-filter: blur(0);
                opacity: 1;
            }
        }

        @keyframes atmosphereIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .intro {
            position: fixed;
            inset: 0;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(5, 12, 24, .02);
            pointer-events: none;
            animation: introOut .85s ease forwards 2.85s;
        }

        .intro-mark {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            color: #fff;
            transform: translateY(8px);
            opacity: 0;
            animation: introMark .9s cubic-bezier(.2, .8, .2, 1) forwards .65s;
        }

        .intro-logo {
            width: 132px;
            height: 132px;
            border-radius: 30px;
            object-fit: cover;
            margin-bottom: 22px;
            box-shadow: 0 24px 70px rgba(2, 8, 18, .46), 0 0 0 1px rgba(255, 255, 255, .16);
        }

        .intro-name {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(28px, 4vw, 46px);
            font-weight: 900;
            line-height: 1;
            text-shadow: 0 16px 44px rgba(0, 0, 0, .36);
        }

        .intro-full {
            margin-top: 10px;
            font-size: clamp(13px, 1.5vw, 17px);
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .78);
        }

        @keyframes introMark {
            from {
                opacity: 0;
                transform: translateY(20px) scale(.94);
                filter: blur(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
                filter: blur(0);
            }
        }

        @keyframes introOut {
            to {
                opacity: 0;
                visibility: hidden;
            }
        }

        /* LEFT - branding */
        .brand-side {
            background:
                linear-gradient(145deg, rgba(10, 27, 49, .86), rgba(20, 47, 78, .78)),
                rgba(255, 255, 255, .04);
            border: 1px solid var(--gb);
            border-right: none;
            border-radius: 24px 0 0 24px;
            padding: 58px 48px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            text-align: center;
        }

        .brand-side::before {
            content: '';
            position: absolute;
            inset: auto 28px 28px 28px;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .28), transparent);
        }

        .brand-logo {
            width: 142px;
            height: 142px;
            border-radius: 32px;
            background: linear-gradient(135deg, var(--indigo), var(--teal));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: #fff;
            margin-bottom: 30px;
            box-shadow: 0 24px 70px rgba(2, 8, 18, .38), 0 0 0 1px rgba(255, 255, 255, .12);
        }

        .brand-name {
            font-family: 'Outfit', sans-serif;
            font-size: 46px;
            font-weight: 900;
            color: #fff;
            line-height: 1.1;
            margin-bottom: 14px;
        }

        .brand-name span {
            color: var(--teal);
        }

        .brand-full {
            font-size: 17px;
            color: var(--muted);
            line-height: 1.6;
            max-width: 360px;
        }

        .page {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 950px;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            min-height: 580px;
            opacity: 0;
            animation: pageReveal .8s ease forwards 2.85s;
        }

        @keyframes pageReveal {
            from {
                opacity: 0;
                transform: translateY(24px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .brand-side,
        .form-side {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity .7s ease, transform .7s ease;
        }

        .brand-side.show,
        .form-side.show {
            opacity: 1;
            transform: translateY(0);
        }

        .brand-logo {
            animation: logoPop .9s ease forwards 3.05s;
            opacity: 0;
        }

        @keyframes logoPop {
            0% {
                opacity: 0;
                transform: scale(.82) translateY(12px)
            }

            100% {
                opacity: 1;
                transform: scale(1) translateY(0)
            }
        }

        /* RIGHT - form */
        .form-side {
            background: rgba(10, 22, 40, .82);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--gb);
            border-radius: 0 24px 24px 0;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-title {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--txt);
            margin-bottom: 4px;
        }

        .form-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 28px;
        }

        /* role selector */
        .role-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 24px;
        }

        .role-card {
            padding: 10px 8px;
            border-radius: 12px;
            border: 1.5px solid rgba(255, 255, 255, .08);
            background: rgba(255, 255, 255, .03);
            cursor: pointer;
            text-align: center;
            transition: all .2s;
            user-select: none;
        }

        .role-card i {
            display: block;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .role-card span {
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: .5px;
        }

        .role-card.dir {
            --rc: #4f63d2;
        }

        .role-card.hod {
            --rc: #00b4d8;
        }

        .role-card.tea {
            --rc: #06d6a0;
        }

        .role-card.stu {
            --rc: #ffc844;
        }

        .role-card i {
            color: var(--rc, var(--muted));
        }

        .role-card:hover,
        .role-card.active {
            border-color: var(--rc, var(--indigo));
            background: rgba(79, 99, 210, .1);
            box-shadow: 0 0 0 3px rgba(79, 99, 210, .12);
        }

        .role-card.active span {
            color: var(--txt);
        }

        /* form fields */
        .fgroup {
            margin-bottom: 16px;
        }

        .flabel {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 7px;
        }

        .finput-wrap {
            position: relative;
        }

        .finput-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--indigo2);
            font-size: 14px;
        }

        .finput {
            width: 100%;
            padding: 11px 14px 11px 40px;
            background: rgba(255, 255, 255, .05);
            border: 1.5px solid rgba(255, 255, 255, .09);
            border-radius: 11px;
            color: var(--txt);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            outline: none;
            transition: all .25s;
        }

        .finput:focus {
            border-color: var(--indigo2);
            background: rgba(79, 99, 210, .08);
            box-shadow: 0 0 0 3px rgba(79, 99, 210, .16);
        }

        /* submit */
        .btn-submit {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--indigo), var(--teal));
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all .3s;
            position: relative;
            overflow: hidden;
            margin-top: 8px;
        }

        .btn-submit::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--indigo2), #00c8f0);
            opacity: 0;
            transition: opacity .3s;
        }

        .btn-submit:hover::after {
            opacity: 1;
        }

        .btn-submit span {
            position: relative;
            z-index: 1;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 28px rgba(79, 99, 210, .45);
        }

        /* error */
        .err-box {
            background: rgba(239, 35, 60, .1);
            border: 1px solid rgba(239, 35, 60, .28);
            border-radius: 10px;
            color: #ff6b7a;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media(max-width:680px) {
            .page {
                grid-template-columns: 1fr;
                max-width: 420px;
                max-height: 100vh;
                overflow-y: auto;
            }

            .brand-side {
                border-radius: 24px 24px 0 0;
                border-right: 1px solid var(--gb);
                border-bottom: none;
            }

            .form-side {
                border-radius: 0 0 24px 24px;
            }
        }
    </style>
    <script src="js/pimt-alerts.js"></script>
    <script src="js/pimt-actions.js"></script>
</head>

<body>
    <div class="intro" aria-hidden="true">
        <div class="intro-mark">
            <img class="intro-logo" src="img/pimt-logo.png" alt="">
            <div class="intro-name">PIMT</div>
            <div class="intro-full">Punjab Institute of Management &amp; Technology</div>
        </div>
    </div>

    <div class="page">
        <!-- BRAND SIDE -->
        <div class="brand-side">
            <div class="brand-logo" style="background:none;box-shadow:none;"><img src="img/pimt-logo.png"
                    style="width:100%;height:100%;border-radius:32px;object-fit:cover;box-shadow:0 24px 70px rgba(2,8,18,.38),0 0 0 1px rgba(255,255,255,.12);">
            </div>
            <div class="brand-name">PIMT<span>.</span></div>
            <div class="brand-full">Punjab Institute of Management<br>&amp; Technology</div>
        </div>

        <!-- FORM SIDE -->
        <div class="form-side">
            <div class="form-title">Welcome Back</div>
            <div class="form-sub">Sign in to your account</div>

            <?php if ($error): ?>
                <div class="err-box"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Role selector -->
            <div class="role-grid" id="roleGrid">
                <div class="role-card dir <?= ($_POST['userType'] ?? '') === 'Director' ? 'active' : '' ?>"
                    data-role="Director">
                    <i class="fas fa-user-shield"></i><span>Director</span>
                </div>
                <div class="role-card hod <?= ($_POST['userType'] ?? '') === 'HOD' ? 'active' : '' ?>" data-role="HOD">
                    <i class="fas fa-sitemap"></i><span>HOD</span>
                </div>
                <div class="role-card tea <?= ($_POST['userType'] ?? '') === 'Teacher' ? 'active' : '' ?>"
                    data-role="Teacher">
                    <i class="fas fa-chalkboard-teacher"></i><span>Teacher</span>
                </div>
                <div class="role-card stu <?= ($_POST['userType'] ?? '') === 'Student' ? 'active' : '' ?>"
                    data-role="Student">
                    <i class="fas fa-user-graduate"></i><span>Student</span>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="userType" id="roleInput"
                    value="<?= htmlspecialchars($_POST['userType'] ?? '') ?>" required>
                <div class="fgroup">
                    <label class="flabel" id="userLbl" for="userField">Email / Roll Number</label>
                    <div class="finput-wrap">
                        <i class="fas fa-at finput-icon" id="userIcon"></i>
                        <input type="text" name="username" class="finput" id="userField"
                            placeholder="Enter your email or roll number"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="fgroup">
                    <label class="flabel">Password</label>
                    <div class="finput-wrap">
                        <i class="fas fa-lock finput-icon"></i>
                        <input type="password" name="password" class="finput" placeholder="Enter your password"
                            required>
                    </div>
                </div>
                <button type="submit" name="login" class="btn-submit">
                    <span><i class="fas fa-sign-in-alt" style="margin-right:8px"></i>Sign In to PIMT</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        const cards = document.querySelectorAll('.role-card');
        const input = document.getElementById('roleInput');
        const lbl = document.getElementById('userLbl');
        const icon = document.getElementById('userIcon');
        const field = document.getElementById('userField');

        function syncLoginFields(role) {
            if (role === 'Student') {
                lbl.textContent = 'Roll Number';
                icon.className = 'fas fa-id-card finput-icon';
                field.placeholder = 'Enter roll number (e.g. CSE2024001)';
            } else {
                lbl.textContent = 'Email Address';
                icon.className = 'fas fa-at finput-icon';
                field.placeholder = 'Enter your email address';
            }
        }

        cards.forEach(c => c.addEventListener('click', () => {
            cards.forEach(x => x.classList.remove('active'));
            c.classList.add('active');
            input.value = c.dataset.role;
            syncLoginFields(c.dataset.role);
        }));

        syncLoginFields(input.value);

        document.querySelector('form').addEventListener('submit', e => {
            if (!input.value) {
                e.preventDefault();
                PIMTAlert.show('Please select your role first!', 'warning');
            }
        });

        window.addEventListener('load', () => {
            setTimeout(() => document.querySelector('.brand-side').classList.add('show'), 2900);
            setTimeout(() => document.querySelector('.form-side').classList.add('show'), 3650);
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('Service Worker registered', reg))
                    .catch(err => console.log('Service Worker registration failed', err));
            });
        }
    </script>
</body>

</html>
