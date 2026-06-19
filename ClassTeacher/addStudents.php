<?php
session_start();
if(!isset($_SESSION['userId'])||$_SESSION['role']!=='Teacher'){header("Location: ../index.php");exit;}
include '../Includes/dbcon.php';
include '../Includes/assignment_schema.php';
include '../Includes/db_helpers.php';
$teacherId=(int)$_SESSION['userId'];
$msg=''; $msgType='';

if(isset($_POST['addStudent'])){
    $fn=trim($_POST['firstName']); $ln=trim($_POST['lastName']); $gender=$_POST['gender'];
    $roll=strtoupper(trim($_POST['rollNumber'])); $pass=trim($_POST['password']); $courseIds=array_values(array_filter(array_map('intval', $_POST['courseIds'] ?? []))); $cid=(int)($courseIds[0] ?? 0); $sdeptId=(int)$_POST['deptId'];
    $subjectIds=array_values(array_filter(array_map('intval', $_POST['subjectIds'] ?? [])));
    $semester=trim($_POST['semester']); $subjectDuration=trim($_POST['subjectDuration']);
    $photo=null;
    if(!empty($_FILES['photo']['name'])){
        $ext=strtolower(pathinfo($_FILES['photo']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,['jpg','jpeg','png'])&&$_FILES['photo']['size']<=2097152){
            $fname='student_'.time().'_'.rand(100,999).'.'.$ext;
            move_uploaded_file($_FILES['photo']['tmp_name'],'../uploads/students/'.$fname);
            $photo=$fname;
        } else { $msg="Photo must be JPG/PNG under 2MB."; $msgType='warning'; }
    }
    if(!$msg){
        if(!$courseIds){$msg="Select at least one course!";$msgType='warning';}
        else {
            $valid = [];
            $v=$conn->prepare("SELECT Id FROM tblteacher_course WHERE teacherId=? AND courseId=?");
            foreach($courseIds as $courseId){$v->bind_param("ii",$teacherId,$courseId);$v->execute();if($v->get_result()->num_rows>0)$valid[]=$courseId;}
            if(count($valid)!==count($courseIds)){$msg="You can assign only courses assigned to you!";$msgType='danger';}
            else {
                $ck=$conn->prepare("SELECT Id FROM tblstudent WHERE rollNumber=?");$ck->bind_param("s",$roll);$ck->execute();
                if($ck->get_result()->num_rows>0){$msg="Roll number already exists!";$msgType='danger';}
                else{
                    $st=$conn->prepare("INSERT INTO tblstudent (firstName,lastName,gender,rollNumber,password,courseId,deptId,photo) VALUES(?,?,?,?,?,?,?,?)");
                    $st->bind_param("sssssiis",$fn,$ln,$gender,$roll,$pass,$cid,$sdeptId,$photo);
                    $st->execute();
                    $studentId=$conn->insert_id;
                    $sc=$conn->prepare("INSERT IGNORE INTO tblstudent_course (studentId,courseId,assignedByRole,assignedById) VALUES (?,?,'Teacher',?)");
                    foreach($valid as $courseId){$sc->bind_param("iii",$studentId,$courseId,$teacherId);$sc->execute();}
                    if($subjectIds){
                        $ss=$conn->prepare("
                            INSERT IGNORE INTO tblstudent_subject (studentId,subjectId,semester,duration,assignedByRole,assignedById)
                            SELECT ?, ?, ?, ?, 'Teacher', ? FROM tblteacher_subject WHERE teacherId=? AND subjectId=? LIMIT 1
                        ");
                        foreach($subjectIds as $subjectId){$ss->bind_param("iissiii",$studentId,$subjectId,$semester,$subjectDuration,$teacherId,$teacherId,$subjectId);$ss->execute();}
                    }
                    $msg="Student enrolled! Login: $roll / $pass"; $msgType='success';
                }
            }
        }
    }
}

if(isset($_POST['assignExistingStudents'])){
    $assignCourseId=(int)($_POST['assignCourseId'] ?? 0);
    $subjectIds=array_values(array_filter(array_map('intval', $_POST['assignSubjectIds'] ?? [])));
    $studentIds=array_values(array_filter(array_map('intval', $_POST['assignStudentIds'] ?? [])));
    $semester=trim($_POST['assignSemester'] ?? '');
    $subjectDuration=trim($_POST['assignDuration'] ?? '');

    if(!$assignCourseId){$msg="Select a course first."; $msgType='warning';}
    elseif(!$subjectIds){$msg="Select at least one subject."; $msgType='warning';}
    elseif(!$studentIds){$msg="Select at least one student."; $msgType='warning';}
    else{
        $courseOk=$conn->prepare("SELECT Id FROM tblteacher_course WHERE teacherId=? AND courseId=?");
        $courseOk->bind_param("ii",$teacherId,$assignCourseId);
        $courseOk->execute();
        if($courseOk->get_result()->num_rows===0){$msg="You can assign students only for your course."; $msgType='danger';}
        else{
            $added=0;
            $ss=$conn->prepare("
                INSERT IGNORE INTO tblstudent_subject (studentId,subjectId,semester,duration,assignedByRole,assignedById)
                SELECT s.Id, sub.Id, COALESCE(NULLIF(?,''), sub.semester), COALESCE(NULLIF(?,''), sub.duration), 'Teacher', ?
                FROM tblstudent s
                JOIN tblteacher_course tc ON tc.teacherId=? AND tc.courseId=?
                JOIN tblteacher_subject ts ON ts.teacherId=? AND ts.subjectId=?
                JOIN tblsubject sub ON sub.Id=ts.subjectId AND sub.courseId=tc.courseId
                LEFT JOIN tblstudent_course sc ON sc.studentId=s.Id AND sc.courseId=tc.courseId
                WHERE s.Id=? AND (s.courseId=tc.courseId OR sc.Id IS NOT NULL)
                LIMIT 1
            ");
            foreach($subjectIds as $subjectId){
                foreach($studentIds as $studentId){
                    $ss->bind_param("ssiiiiii",$semester,$subjectDuration,$teacherId,$teacherId,$assignCourseId,$teacherId,$subjectId,$studentId);
                    $ss->execute();
                    $added += $ss->affected_rows > 0 ? 1 : 0;
                }
            }
            $msg=$added>0 ? "$added subject assignment(s) added for existing course students." : "Selected students were already assigned, or subject/course did not match.";
            $msgType=$added>0 ? 'success' : 'warning';
        }
    }
}
if(isset($_GET['delete'])){$id=(int)$_GET['delete'];$conn->query("DELETE FROM tblstudent_course WHERE studentId=$id");$conn->query("DELETE FROM tblstudent WHERE Id=$id");$conn->query("DELETE FROM tblattendance WHERE studentId=$id");resequenceCollegeAttendanceIds($conn);$msg="Student removed.";$msgType='warning';}

$myCourses=$conn->query("SELECT c.Id,c.courseName,c.courseCode FROM tblteacher_course tc JOIN tblcourse c ON tc.courseId=c.Id WHERE tc.teacherId=$teacherId ORDER BY c.courseName");
$courseRows=[];
if($myCourses){while($c=$myCourses->fetch_assoc()){$courseRows[]=$c;} $myCourses->data_seek(0);}
$mySubjects=$conn->query("
    SELECT s.Id,s.subjectName,s.subjectCode,s.semester,s.duration,c.courseName,c.courseCode,d.deptName
    FROM tblteacher_subject ts
    JOIN tblsubject s ON ts.subjectId=s.Id
    JOIN tblcourse c ON s.courseId=c.Id
    JOIN tbldepartment d ON s.deptId=d.Id
    WHERE ts.teacherId=$teacherId
    ORDER BY c.courseName,s.subjectName
");
$filterCourse=isset($_GET['courseId'])?(int)$_GET['courseId']:0;
$assignCourseId=$filterCourse ?: (int)($courseRows[0]['Id'] ?? 0);
$assignSubjects=$assignCourseId?$conn->query("
    SELECT s.Id,s.subjectName,s.subjectCode,s.semester,s.duration
    FROM tblteacher_subject ts
    JOIN tblsubject s ON ts.subjectId=s.Id
    WHERE ts.teacherId=$teacherId AND s.courseId=$assignCourseId
    ORDER BY s.subjectName
"):false;
$courseStudents=$assignCourseId?$conn->query("
    SELECT s.Id,s.firstName,s.lastName,s.rollNumber,s.gender,s.photo,d.deptName,
           GROUP_CONCAT(DISTINCT ss.subjectId ORDER BY ss.subjectId SEPARATOR ',') as assignedSubjectIds,
           GROUP_CONCAT(DISTINCT CONCAT(sub.subjectCode, ' - ', sub.subjectName) ORDER BY sub.subjectName SEPARATOR ', ') as assignedSubjects
    FROM tblstudent s
    JOIN tblteacher_course tc ON tc.teacherId=$teacherId AND tc.courseId=$assignCourseId
    LEFT JOIN tblstudent_course sc ON sc.studentId=s.Id AND sc.courseId=tc.courseId
    LEFT JOIN tblstudent_subject ss ON ss.studentId=s.Id
    LEFT JOIN tblsubject sub ON sub.Id=ss.subjectId AND sub.courseId=tc.courseId
    LEFT JOIN tbldepartment d ON s.deptId=d.Id
    WHERE s.courseId=tc.courseId OR sc.Id IS NOT NULL
    GROUP BY s.Id ORDER BY s.firstName,s.lastName
"):false;
$where=$filterCourse?"AND COALESCE(sc.courseId,s.courseId)=$filterCourse":'';
$students=$conn->query("
    SELECT s.*,c.courseName,c.courseCode, d.deptName,
           COALESCE(GROUP_CONCAT(DISTINCT c2.courseCode ORDER BY c2.courseName SEPARATOR ', '), c.courseCode) as courseCodes
           , GROUP_CONCAT(DISTINCT CONCAT(sub.subjectCode, ' - ', sub.subjectName) ORDER BY sub.subjectName SEPARATOR ', ') as subjectList
    FROM tblstudent s
    JOIN tblcourse c ON s.courseId=c.Id
    LEFT JOIN tblstudent_course sc ON s.Id=sc.studentId
    LEFT JOIN tblcourse c2 ON sc.courseId=c2.Id
    LEFT JOIN tblstudent_subject ss ON s.Id=ss.studentId
    LEFT JOIN tblsubject sub ON ss.subjectId=sub.Id
    JOIN tblteacher_course tc ON COALESCE(sc.courseId,s.courseId)=tc.courseId
    LEFT JOIN tbldepartment d ON s.deptId=d.Id
    WHERE tc.teacherId=$teacherId $where
    GROUP BY s.Id ORDER BY s.dateCreated DESC");
?>
<!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" href="../img/pimt-logo.png">
    <title>Add Students - PIMT Teacher</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="../css/cams.css" rel="stylesheet">
    <style>
    .photo-upload-box{display:flex;align-items:center;gap:14px;padding:14px;background:rgba(255,255,255,.03);border:2px dashed rgba(255,255,255,.1);border-radius:12px;cursor:pointer;transition:all .25s}
    .photo-upload-box:hover{border-color:var(--indigo);background:var(--indigo-light)}
    .photo-preview{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--text-muted);background:var(--glass);flex-shrink:0;overflow:hidden}
    .gender-select{display:flex;gap:8px}
    .gender-opt{flex:1;padding:10px 6px;border-radius:10px;border:1.5px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);color:var(--text-muted);font-size:12px;font-weight:600;text-align:center;cursor:pointer;transition:all .2s;user-select:none}
    .gender-opt:hover{border-color:var(--indigo);background:var(--indigo-light)}
    .gender-opt.male.sel{border-color:#60a5fa;background:rgba(96,165,250,.12);color:#60a5fa}
    .gender-opt.female.sel{border-color:#f472b6;background:rgba(244,114,182,.12);color:#f472b6}
    .gender-opt.other.sel{border-color:var(--gold);background:rgba(255,200,68,.1);color:var(--gold)}
    .mini-note{font-size:12px;color:var(--text-muted);line-height:1.5}
    .check-cell{width:42px;text-align:center}
    .student-check{width:17px;height:17px;accent-color:#4f63d2}
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
                <h1 class="page-title"><span>Enroll</span> Students</h1>
                <nav class="breadcrumb"><a href="index.php"><i class="fas fa-home"></i></a><i class="fas fa-chevron-right"></i><span>Add Students</span></nav>
            </div>
            <?php if($msg):?><div class="alert alert-<?=$msgType?>"><i class="fas fa-info-circle"></i> <?=$msg?></div><?php endif;?>

            <?php if($myCourses->num_rows===0):?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No courses assigned. Contact the Director or HOD.</div>
            <?php else:?>
            <div class="cams-card">
                <div class="card-header"><div class="card-title"><i class="fas fa-user-plus"></i> Enroll New Student</div></div>
                <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Course(s) *</label>
                            <select name="courseIds[]" class="form-input" multiple size="5" required>
                                <?php $myCourses->data_seek(0);while($c=$myCourses->fetch_assoc()):?>
                                <option value="<?=$c['Id']?>" <?=$filterCourse==$c['Id']?'selected':''?>>[<?=$c['courseCode']?>] <?=htmlspecialchars($c['courseName'])?></option>
                                <?php endwhile;?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subject(s)</label>
                            <div class="search-box" style="margin-bottom:8px"><i class="fas fa-search"></i><input type="text" id="subjectOptionSearch" class="form-input" placeholder="Search subjects..."></div>
                            <select name="subjectIds[]" id="subjectSelect" class="form-input" multiple size="5">
                                <?php if($mySubjects): while($sub=$mySubjects->fetch_assoc()): ?>
                                <option value="<?=$sub['Id']?>" data-search="<?=htmlspecialchars(strtolower($sub['subjectName'].' '.$sub['subjectCode'].' '.$sub['courseName'].' '.$sub['courseCode'].' '.$sub['deptName']))?>">
                                    [<?=$sub['subjectCode']?>] <?=htmlspecialchars($sub['subjectName'])?> - <?=htmlspecialchars($sub['courseCode'])?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department *</label>
                            <select name="deptId" class="form-input" required>
                                <option value="">-- Select Dept --</option>
                                <?php $allDepts=$conn->query("SELECT * FROM tbldepartment ORDER BY deptName"); while($d=$allDepts->fetch_assoc()): ?>
                                <option value="<?=$d['Id']?>"><?=htmlspecialchars($d['deptName'])?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">First Name *</label><input type="text" name="firstName" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">Last Name *</label><input type="text" name="lastName" class="form-input" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Gender *</label>
                            <div class="gender-select">
                                <div class="gender-opt male sel" data-val="Male" onclick="selectGender(this)"><i class="fas fa-mars"></i><br>Male</div>
                                <div class="gender-opt female" data-val="Female" onclick="selectGender(this)"><i class="fas fa-venus"></i><br>Female</div>
                                <div class="gender-opt other" data-val="Other" onclick="selectGender(this)"><i class="fas fa-genderless"></i><br>Other</div>
                            </div>
                            <input type="hidden" name="gender" id="genderInput" value="Male">
                        </div>
                        <div class="form-group"><label class="form-label">Roll Number *</label><input type="text" name="rollNumber" class="form-input" placeholder="e.g. CSE2024006" required></div>
                        <div class="form-group"><label class="form-label">Password *</label><input type="text" name="password" class="form-input" required></div>
                        <div class="form-group"><label class="form-label">Semester</label><input type="text" name="semester" class="form-input" placeholder="e.g. Semester 1"></div>
                        <div class="form-group"><label class="form-label">Subject Duration</label><input type="text" name="subjectDuration" class="form-input" placeholder="e.g. 6 Months"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Student Photo (optional)</label>
                            <div class="photo-upload-box" onclick="document.getElementById('sPhoto').click()">
                                <div class="photo-preview" id="sPhotoPreview"><i class="fas fa-camera"></i></div>
                                <div>
                                    <div style="font-size:13px;font-weight:600;color:var(--text-light)">Click to upload photo</div>
                                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px">JPG or PNG &middot; max 2MB</div>
                                </div>
                            </div>
                            <input type="file" id="sPhoto" name="photo" accept="image/jpeg,image/png" style="display:none" onchange="previewImg(this,'sPhotoPreview')">
                        </div>
                    </div>
                    <button type="submit" name="addStudent" class="btn btn-primary"><i class="fas fa-user-plus"></i> Enroll Student</button>
                </form>
                </div>
            </div>

            <div class="cams-card">
                <div class="card-header">
                    <div>
                        <div class="card-title"><i class="fas fa-user-check"></i> Add Existing Course Students to Subject</div>
                        <div class="mini-note">Students already enrolled by HOD in this course can be mapped to your subject here.</div>
                    </div>
                    <form method="GET" style="display:flex;gap:8px;align-items:center">
                        <select name="courseId" class="form-input" style="max-width:260px;padding:7px 12px;font-size:13px" onchange="this.form.submit()">
                            <?php foreach($courseRows as $c):?>
                            <option value="<?=$c['Id']?>" <?=$assignCourseId==$c['Id']?'selected':''?>>[<?=htmlspecialchars($c['courseCode'])?>] <?=htmlspecialchars($c['courseName'])?></option>
                            <?php endforeach;?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
                    </form>
                </div>
                <div class="card-body">
                    <?php if(!$assignCourseId):?>
                    <div class="empty-state"><div class="empty-title">Select a course to load students</div></div>
                    <?php elseif(!$assignSubjects || $assignSubjects->num_rows===0):?>
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No subjects assigned to you for this course.</div>
                    <?php else:?>
                    <form method="POST">
                        <input type="hidden" name="assignCourseId" value="<?=$assignCourseId?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Subject(s) *</label>
                                <select name="assignSubjectIds[]" class="form-input" multiple size="5" required>
                                    <?php while($sub=$assignSubjects->fetch_assoc()):?>
                                    <option value="<?=$sub['Id']?>">[<?=htmlspecialchars($sub['subjectCode'])?>] <?=htmlspecialchars($sub['subjectName'])?></option>
                                    <?php endwhile;?>
                                </select>
                            </div>
                            <div class="form-group"><label class="form-label">Semester</label><input type="text" name="assignSemester" class="form-input" placeholder="Use subject default if blank"></div>
                            <div class="form-group"><label class="form-label">Subject Duration</label><input type="text" name="assignDuration" class="form-input" placeholder="Use subject default if blank"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin:12px 0">
                            <div class="mini-note">Select students from the course list below.</div>
                            <button type="button" class="btn btn-sm" style="background:var(--glass);border:1px solid var(--glass-border)" onclick="toggleAssignChecks(this)"><i class="fas fa-check-square"></i> Select All</button>
                        </div>
                        <div class="table-wrapper scroll-y">
                            <table class="cams-table">
                                <thead><tr><th class="check-cell"></th><th>Student</th><th>Roll No</th><th>Gender</th><th>Department</th><th>Assigned Subjects</th></tr></thead>
                                <tbody>
                                <?php if($courseStudents&&$courseStudents->num_rows>0): while($s=$courseStudents->fetch_assoc()):
                                    $gColor=$s['gender']==='Female'?'#f472b6':($s['gender']==='Other'?'var(--gold)':'#60a5fa');
                                ?>
                                <tr>
                                    <td class="check-cell"><input type="checkbox" class="student-check assign-student-check" name="assignStudentIds[]" value="<?=$s['Id']?>"></td>
                                    <td><strong><?=htmlspecialchars($s['firstName'].' '.$s['lastName'])?></strong></td>
                                    <td><span class="badge badge-indigo"><?=htmlspecialchars($s['rollNumber'])?></span></td>
                                    <td><span style="font-size:12px;font-weight:600;color:<?=$gColor?>"><?=htmlspecialchars($s['gender'])?></span></td>
                                    <td><?=htmlspecialchars($s['deptName'] ?? 'Unassigned')?></td>
                                    <td><?=htmlspecialchars($s['assignedSubjects'] ?: 'Not assigned yet')?></td>
                                </tr>
                                <?php endwhile; else:?>
                                <tr><td colspan="6"><div class="empty-state"><div class="empty-title">No students found in this course</div></div></td></tr>
                                <?php endif;?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" name="assignExistingStudents" class="btn btn-primary" style="margin-top:14px"><i class="fas fa-link"></i> Add Selected Students to Subject</button>
                    </form>
                    <?php endif;?>
                </div>
            </div>
            <?php endif;?>

            <div class="cams-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-list"></i> Students (<?=$students?$students->num_rows:0?>)</div>
                    <div class="table-tools"><div class="search-box"><i class="fas fa-search"></i><input class="form-input" data-table-search="#teacherStudentsTable" placeholder="Search students, courses, subjects..."></div></div>
                    <form method="GET" style="display:flex;gap:8px;align-items:center">
                        <select name="courseId" class="form-input" style="max-width:220px;padding:7px 12px;font-size:13px">
                            <option value="0">All Courses</option>
                            <?php $myCourses->data_seek(0);while($c=$myCourses->fetch_assoc()):?><option value="<?=$c['Id']?>" <?=$filterCourse==$c['Id']?'selected':''?>><?=htmlspecialchars($c['courseName'])?></option><?php endwhile;?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
                    </form>
                </div>
                <div class="table-wrapper scroll-y">
                    <table class="cams-table" id="teacherStudentsTable">
                        <thead><tr><th>#</th><th>Photo</th><th>Name</th><th>Gender</th><th>Roll No</th><th>Course</th><th>Subject</th><th>Department</th><th>Enrolled</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if($students&&$students->num_rows>0):$i=1;while($s=$students->fetch_assoc()):
                            $gColor=$s['gender']==='Female'?'#f472b6':($s['gender']==='Other'?'var(--gold)':'#60a5fa');
                        ?>
                        <tr>
                            <td><?=$i++?></td>
                            <td><?php if(!empty($s['photo'])):?><img src="../uploads/students/<?=htmlspecialchars($s['photo'])?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover"><?php else:?><div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--indigo),var(--teal));display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:13px"><?=strtoupper(substr($s['firstName'],0,1).substr($s['lastName'],0,1))?></div><?php endif;?></td>
                            <td><strong><?=htmlspecialchars($s['firstName'].' '.$s['lastName'])?></strong></td>
                            <td><span style="font-size:12px;font-weight:600;color:<?=$gColor?>"><?=$s['gender']?></span></td>
                            <td><span class="badge badge-indigo"><?=$s['rollNumber']?></span></td>
                            <td><span class="badge badge-info"><?=htmlspecialchars($s['courseCodes'])?></span></td>
                            <td><?=htmlspecialchars($s['subjectList'] ?: 'Not assigned')?></td>
                            <td><?=htmlspecialchars($s['deptName'] ?? 'Unassigned')?></td>
                            <td><?=date('d M Y',strtotime($s['dateCreated']))?></td>
                            <td><a href="?delete=<?=$s['Id']?>&courseId=<?=$filterCourse?>" class="btn btn-sm btn-danger" onclick="return PIMTAlert.confirmLink(this, 'Remove student?')"><i class="fas fa-trash"></i></a></td>
                        </tr>
                        <?php endwhile;else:?>
                        <tr><td colspan="10"><div class="empty-state"><div class="empty-icon"><i class="fas fa-user-graduate"></i></div><div class="empty-title">No students enrolled yet</div></div></td></tr>
                        <?php endif;?>
                        <tr class="search-empty-row"><td colspan="10"><div class="empty-state"><div class="empty-title">No matching students</div></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include 'Includes/footer.php'; ?>
    </div>
</div>
<script src="../js/cams-search.js"></script>
<script>
const subjectSearch = document.getElementById('subjectOptionSearch');
if (subjectSearch) {
    subjectSearch.addEventListener('input', function() {
        const term = this.value.trim().toLowerCase();
        document.querySelectorAll('#subjectSelect option').forEach(opt => {
            opt.hidden = term && !opt.dataset.search.includes(term);
        });
    });
}
function selectGender(el){document.querySelectorAll('.gender-opt').forEach(x=>x.classList.remove('sel'));el.classList.add('sel');document.getElementById('genderInput').value=el.dataset.val;}
function previewImg(input,previewId){const prev=document.getElementById(previewId);if(input.files&&input.files[0]){const r=new FileReader();r.onload=e=>{prev.innerHTML='<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%">';};r.readAsDataURL(input.files[0]);}}
function toggleAssignChecks(btn){
    const checks=Array.from(document.querySelectorAll('.assign-student-check'));
    const shouldCheck=checks.some(ch=>!ch.checked);
    checks.forEach(ch=>ch.checked=shouldCheck);
    btn.innerHTML=shouldCheck?'<i class="fas fa-square"></i> Clear All':'<i class="fas fa-check-square"></i> Select All';
}
// Sidebar toggle handled by topbar.php
</script>
</body></html>
