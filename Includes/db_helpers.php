<?php

function resetAutoIncrement(mysqli $connection, string $tableName, string $idColumn = 'Id'): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $idColumn)) {
        return;
    }

    $table = "`$tableName`";
    $column = "`$idColumn`";
    $result = $connection->query("SELECT COALESCE(MAX($column), 0) + 1 AS next_id FROM $table");

    if (!$result) {
        return;
    }

    $row = $result->fetch_assoc();
    $nextId = max(1, (int)($row['next_id'] ?? 1));
    $connection->query("ALTER TABLE $table AUTO_INCREMENT = $nextId");
}

function tableExists(mysqli $connection, string $tableName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
        return false;
    }

    $table = $connection->real_escape_string($tableName);
    $result = $connection->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

function columnExists(mysqli $connection, string $tableName, string $columnName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) {
        return false;
    }

    $table = $connection->real_escape_string($tableName);
    $column = $connection->real_escape_string($columnName);
    $result = $connection->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function updateMappedIdColumn(mysqli $connection, string $tableName, string $columnName, array $idMap, string $where = ''): void
{
    if (!$idMap || !tableExists($connection, $tableName) || !columnExists($connection, $tableName, $columnName)) {
        return;
    }

    $caseParts = [];
    $oldIds = [];

    foreach ($idMap as $oldId => $newId) {
        $oldId = (int)$oldId;
        $newId = (int)$newId;

        if ($oldId === $newId) {
            continue;
        }

        $caseParts[] = "WHEN $oldId THEN $newId";
        $oldIds[] = $oldId;
    }

    if (!$caseParts) {
        return;
    }

    $table = "`$tableName`";
    $column = "`$columnName`";
    $whereSql = "$column IN (" . implode(',', $oldIds) . ")";

    if ($where !== '') {
        $whereSql .= " AND ($where)";
    }

    $connection->query("UPDATE $table SET $column = CASE $column " . implode(' ', $caseParts) . " ELSE $column END WHERE $whereSql");
}

function resequenceTableIds(mysqli $connection, string $tableName, array $references = [], string $orderBy = 'Id'): void
{
    if (!tableExists($connection, $tableName) || !columnExists($connection, $tableName, 'Id')) {
        return;
    }

    $safeOrderBy = columnExists($connection, $tableName, $orderBy) ? $orderBy : 'Id';
    $table = "`$tableName`";
    $result = $connection->query("SELECT `Id` FROM $table ORDER BY `$safeOrderBy`, `Id`");

    if (!$result) {
        return;
    }

    $idMap = [];
    $nextId = 1;
    $maxId = 0;

    while ($row = $result->fetch_assoc()) {
        $oldId = (int)$row['Id'];
        $idMap[$oldId] = $nextId++;
        $maxId = max($maxId, $oldId);
    }

    if (!$idMap) {
        resetAutoIncrement($connection, $tableName);
        return;
    }

    $changed = false;
    foreach ($idMap as $oldId => $newId) {
        if ($oldId !== $newId) {
            $changed = true;
            break;
        }
    }

    if ($changed) {
        $offset = $maxId + count($idMap) + 1000;

        foreach ($idMap as $oldId => $newId) {
            if ($oldId !== $newId) {
                $temporaryId = $oldId + $offset;
                $connection->query("UPDATE $table SET `Id` = $temporaryId WHERE `Id` = $oldId");
            }
        }

        foreach ($idMap as $oldId => $newId) {
            if ($oldId !== $newId) {
                $temporaryId = $oldId + $offset;
                $connection->query("UPDATE $table SET `Id` = $newId WHERE `Id` = $temporaryId");
            }
        }

        foreach ($references as $reference) {
            updateMappedIdColumn(
                $connection,
                $reference['table'] ?? '',
                $reference['column'] ?? '',
                $idMap,
                $reference['where'] ?? ''
            );
        }
    }

    resetAutoIncrement($connection, $tableName);
}

function resequenceCollegeAttendanceIds(mysqli $connection): void
{
    $connection->query("SET FOREIGN_KEY_CHECKS = 0");
    $connection->begin_transaction();

    try {
        resequenceTableIds($connection, 'tbldirector', [
            ['table' => 'tblhod_course', 'column' => 'assignedByDirectorId'],
            ['table' => 'tblhod_subject', 'column' => 'assignedByDirectorId'],
            ['table' => 'tblstudent_course', 'column' => 'assignedById', 'where' => "assignedByRole = 'Director'"],
            ['table' => 'tblstudent_subject', 'column' => 'assignedById', 'where' => "assignedByRole = 'Director'"],
        ]);

        resequenceTableIds($connection, 'tbldepartment', [
            ['table' => 'tblhod', 'column' => 'deptId'],
            ['table' => 'tblteacher', 'column' => 'deptId'],
            ['table' => 'tblstudent', 'column' => 'deptId'],
            ['table' => 'tblsubject', 'column' => 'deptId'],
            ['table' => 'tblteacher_attendance', 'column' => 'deptId'],
        ]);

        resequenceTableIds($connection, 'tblhod', [
            ['table' => 'tblhod_course', 'column' => 'hodId'],
            ['table' => 'tblhod_subject', 'column' => 'hodId'],
            ['table' => 'tblteacher_attendance', 'column' => 'takenByHodId'],
            ['table' => 'tblteacher_subject', 'column' => 'assignedByHodId'],
            ['table' => 'tblstudent_course', 'column' => 'assignedById', 'where' => "assignedByRole = 'HOD'"],
            ['table' => 'tblstudent_subject', 'column' => 'assignedById', 'where' => "assignedByRole = 'HOD'"],
        ]);

        resequenceTableIds($connection, 'tblteacher', [
            ['table' => 'tblteacher_course', 'column' => 'teacherId'],
            ['table' => 'tblteacher_subject', 'column' => 'teacherId'],
            ['table' => 'tblteacher_attendance', 'column' => 'teacherId'],
            ['table' => 'tblattendance', 'column' => 'takenByTeacherId'],
        ]);

        resequenceTableIds($connection, 'tblcourse', [
            ['table' => 'tblteacher_course', 'column' => 'courseId'],
            ['table' => 'tblhod_course', 'column' => 'courseId'],
            ['table' => 'tblstudent', 'column' => 'courseId'],
            ['table' => 'tblstudent_course', 'column' => 'courseId'],
            ['table' => 'tblsubject', 'column' => 'courseId'],
            ['table' => 'tblattendance', 'column' => 'courseId'],
        ]);

        resequenceTableIds($connection, 'tblstudent', [
            ['table' => 'tblstudent_course', 'column' => 'studentId'],
            ['table' => 'tblstudent_subject', 'column' => 'studentId'],
            ['table' => 'tblattendance', 'column' => 'studentId'],
        ]);

        resequenceTableIds($connection, 'tblsubject', [
            ['table' => 'tblhod_subject', 'column' => 'subjectId'],
            ['table' => 'tblteacher_subject', 'column' => 'subjectId'],
            ['table' => 'tblstudent_subject', 'column' => 'subjectId'],
            ['table' => 'tblattendance', 'column' => 'subjectId'],
        ]);

        foreach ([
            'tblteacher_course',
            'tblhod_course',
            'tblstudent_course',
            'tblattendance',
            'tblteacher_attendance',
            'tblhod_subject',
            'tblteacher_subject',
            'tblstudent_subject',
        ] as $tableName) {
            resequenceTableIds($connection, $tableName);
        }

        $connection->commit();
    } catch (Throwable $exception) {
        $connection->rollback();
    }

    $connection->query("SET FOREIGN_KEY_CHECKS = 1");
}
