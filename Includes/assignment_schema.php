<?php
// Keeps course-assignment tables available without requiring a manual SQL import.
if (!isset($conn) || !($conn instanceof mysqli)) {
    return;
}

$conn->query("
    CREATE TABLE IF NOT EXISTS tblhod_course (
        Id int(10) NOT NULL AUTO_INCREMENT,
        hodId int(10) NOT NULL,
        courseId int(10) NOT NULL,
        assignedByDirectorId int(10) DEFAULT NULL,
        dateAssigned timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (Id),
        UNIQUE KEY unique_hod_course (hodId, courseId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->query("
    CREATE TABLE IF NOT EXISTS tblstudent_course (
        Id int(10) NOT NULL AUTO_INCREMENT,
        studentId int(10) NOT NULL,
        courseId int(10) NOT NULL,
        assignedByRole varchar(30) DEFAULT NULL,
        assignedById int(10) DEFAULT NULL,
        dateAssigned timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (Id),
        UNIQUE KEY unique_student_course (studentId, courseId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->query("
    CREATE TABLE IF NOT EXISTS tblsubject (
        Id int(10) NOT NULL AUTO_INCREMENT,
        subjectName varchar(255) NOT NULL,
        subjectCode varchar(40) NOT NULL,
        courseId int(10) NOT NULL,
        deptId int(10) NOT NULL,
        semester varchar(30) DEFAULT NULL,
        duration varchar(80) DEFAULT NULL,
        description text DEFAULT NULL,
        dateCreated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (Id),
        UNIQUE KEY unique_subject_course (subjectCode, courseId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->query("
    CREATE TABLE IF NOT EXISTS tblhod_subject (
        Id int(10) NOT NULL AUTO_INCREMENT,
        hodId int(10) NOT NULL,
        subjectId int(10) NOT NULL,
        assignedByDirectorId int(10) DEFAULT NULL,
        dateAssigned timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (Id),
        UNIQUE KEY unique_hod_subject (hodId, subjectId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->query("
    CREATE TABLE IF NOT EXISTS tblteacher_subject (
        Id int(10) NOT NULL AUTO_INCREMENT,
        teacherId int(10) NOT NULL,
        subjectId int(10) NOT NULL,
        assignedByHodId int(10) DEFAULT NULL,
        dateAssigned timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (Id),
        UNIQUE KEY unique_teacher_subject (teacherId, subjectId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->query("
    CREATE TABLE IF NOT EXISTS tblstudent_subject (
        Id int(10) NOT NULL AUTO_INCREMENT,
        studentId int(10) NOT NULL,
        subjectId int(10) NOT NULL,
        semester varchar(30) DEFAULT NULL,
        duration varchar(80) DEFAULT NULL,
        assignedByRole varchar(30) DEFAULT NULL,
        assignedById int(10) DEFAULT NULL,
        dateAssigned timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (Id),
        UNIQUE KEY unique_student_subject (studentId, subjectId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$attendanceColumn = $conn->query("SHOW COLUMNS FROM tblattendance LIKE 'subjectId'");
if ($attendanceColumn && $attendanceColumn->num_rows === 0) {
    $conn->query("ALTER TABLE tblattendance ADD subjectId int(10) NOT NULL DEFAULT 0 AFTER courseId");
}
$oldAttendanceIndex = $conn->query("SHOW INDEX FROM tblattendance WHERE Key_name='unique_attendance'");
if ($oldAttendanceIndex && $oldAttendanceIndex->num_rows > 0) {
    $conn->query("ALTER TABLE tblattendance DROP INDEX unique_attendance");
}
$conn->query("
    DELETE a1 FROM tblattendance a1
    JOIN tblattendance a2
      ON a1.studentId = a2.studentId
     AND a1.courseId = a2.courseId
     AND a1.subjectId = a2.subjectId
     AND a1.dateTaken = a2.dateTaken
     AND a1.Id > a2.Id
");
$subjectAttendanceIndex = $conn->query("SHOW INDEX FROM tblattendance WHERE Key_name='unique_attendance_subject'");
if ($subjectAttendanceIndex && $subjectAttendanceIndex->num_rows === 0) {
    $conn->query("ALTER TABLE tblattendance ADD UNIQUE KEY unique_attendance_subject (studentId, courseId, subjectId, dateTaken)");
}

$conn->query("
    INSERT IGNORE INTO tblhod_course (hodId, courseId)
    SELECT DISTINCT h.Id, tc.courseId
    FROM tblhod h
    JOIN tblteacher t ON t.deptId = h.deptId
    JOIN tblteacher_course tc ON tc.teacherId = t.Id
");

$conn->query("
    INSERT IGNORE INTO tblstudent_course (studentId, courseId, assignedByRole)
    SELECT Id, courseId, 'Legacy'
    FROM tblstudent
    WHERE courseId IS NOT NULL
");

$conn->query("
    INSERT IGNORE INTO tblhod_subject (hodId, subjectId)
    SELECT DISTINCT hc.hodId, s.Id
    FROM tblhod_course hc
    JOIN tblsubject s ON s.courseId = hc.courseId
");
?>
