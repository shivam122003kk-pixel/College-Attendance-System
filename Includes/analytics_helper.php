<?php
/**
 * PIMT Analytics Helper
 * Shared functions for generating analytics data across all panels
 */

/**
 * Get subject-wise attendance data for a student (pie chart data)
 */
function getStudentSubjectAttendance($conn, $studentId) {
    $result = $conn->query("
        SELECT sb.subjectName, sb.subjectCode,
               COUNT(a.Id) as totalDays,
               SUM(a.status) as presentDays,
               CASE WHEN COUNT(a.Id) > 0 THEN ROUND(SUM(a.status)/COUNT(a.Id)*100,1) ELSE 0 END as pct
        FROM tblattendance a
        JOIN tblsubject sb ON a.subjectId = sb.Id
        WHERE a.studentId = $studentId AND a.subjectId > 0
        GROUP BY sb.Id
        ORDER BY sb.subjectName
    ");
    $data = ['labels' => [], 'present' => [], 'absent' => [], 'pcts' => [], 'total' => []];
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $data['labels'][]  = $r['subjectCode'] ?: $r['subjectName'];
            $data['present'][] = (int)$r['presentDays'];
            $data['absent'][]  = (int)($r['totalDays'] - $r['presentDays']);
            $data['pcts'][]    = (float)$r['pct'];
            $data['total'][]   = (int)$r['totalDays'];
        }
    }
    // Fallback: course-level if no subject data
    if (empty($data['labels'])) {
        $result2 = $conn->query("
            SELECT c.courseName, c.courseCode,
                   COUNT(a.Id) as totalDays,
                   SUM(a.status) as presentDays,
                   CASE WHEN COUNT(a.Id) > 0 THEN ROUND(SUM(a.status)/COUNT(a.Id)*100,1) ELSE 0 END as pct
            FROM tblattendance a
            JOIN tblcourse c ON a.courseId = c.Id
            WHERE a.studentId = $studentId
            GROUP BY c.Id
        ");
        if ($result2) {
            while ($r = $result2->fetch_assoc()) {
                $data['labels'][]  = $r['courseCode'];
                $data['present'][] = (int)$r['presentDays'];
                $data['absent'][]  = (int)($r['totalDays'] - $r['presentDays']);
                $data['pcts'][]    = (float)$r['pct'];
                $data['total'][]   = (int)$r['totalDays'];
            }
        }
    }
    return $data;
}

/**
 * Get full student record including all subjects attendance
 */
function getStudentFullRecord($conn, $studentId) {
    $student = $conn->query("
        SELECT s.*, c.courseName, c.courseCode, d.deptName
        FROM tblstudent s
        JOIN tblcourse c ON s.courseId = c.Id
        LEFT JOIN tbldepartment d ON s.deptId = d.Id
        WHERE s.Id = $studentId
    ")->fetch_assoc();

    if (!$student) return null;

    $subjects = $conn->query("
        SELECT sb.subjectName, sb.subjectCode, sb.semester,
               COUNT(a.Id) as totalDays,
               SUM(a.status) as presentDays,
               CASE WHEN COUNT(a.Id) > 0 THEN ROUND(SUM(a.status)/COUNT(a.Id)*100,1) ELSE NULL END as pct
        FROM tblsubject sb
        LEFT JOIN tblattendance a ON a.subjectId = sb.Id AND a.studentId = $studentId
        WHERE sb.courseId = {$student['courseId']}
        GROUP BY sb.Id
        ORDER BY sb.subjectName
    ");

    $subjectData = [];
    if ($subjects) {
        while ($r = $subjects->fetch_assoc()) {
            $subjectData[] = $r;
        }
    }

    $student['subjects'] = $subjectData;
    return $student;
}
