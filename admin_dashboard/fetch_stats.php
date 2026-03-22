<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$dept = $_GET['dept'] ?? '';
$reason = $_GET['reason'] ?? '';
$userType = $_GET['user_type'] ?? '';
$preset = $_GET['preset'] ?? '';

$today = date('Y-m-d');
if (empty($from) && empty($to)) {
    if ($preset === '7d') {
        $from = date('Y-m-d', strtotime('-6 days'));
        $to = $today;
    } elseif ($preset === '30d') {
        $from = date('Y-m-d', strtotime('-29 days'));
        $to = $today;
    } elseif ($preset === '90d') {
        $from = date('Y-m-d', strtotime('-89 days'));
        $to = $today;
    } elseif ($preset === 'year') {
        $from = date('Y-01-01');
        $to = $today;
    } elseif ($preset === 'all') {
        $from = '';
        $to = '';
    } else {
        $from = date('Y-m-d', strtotime('-29 days'));
        $to = $today;
        $preset = '30d';
    }
}

function buildBranch($userTable, $userAlias, $userIdColumn, $userTypeValue, $from, $to, $dept, $reason) {
    $conditions = ["h.user_type = '$userTypeValue'"];
    $params = [];
    $types = '';

    if (!empty($from)) {
        $conditions[] = "h.date >= ?";
        $params[] = $from;
        $types .= 's';
    }
    if (!empty($to)) {
        $conditions[] = "h.date <= ?";
        $params[] = $to;
        $types .= 's';
    }
    if (!empty($reason)) {
        $conditions[] = "h.reason = ?";
        $params[] = $reason;
        $types .= 's';
    }
    if (!empty($dept)) {
        $conditions[] = "$userAlias.departmentID = ?";
        $params[] = $dept;
        $types .= 's';
    }

    $sql = "SELECT h.user_identifier, h.user_type, h.date, h.time, h.reason,
                $userAlias.firstName, $userAlias.lastName, $userAlias.departmentID,
                d.departmentName, $userAlias.status
            FROM history_logs h
            JOIN $userTable $userAlias ON h.user_identifier = $userAlias.$userIdColumn
            LEFT JOIN departments d ON $userAlias.departmentID = d.departmentID
            WHERE " . implode(' AND ', $conditions);

    return [$sql, $params, $types];
}

$useStudent = ($userType === '' || $userType === 'Student');
$useEmployee = ($userType === '' || $userType === 'Employee');

$parts = [];
$params = [];
$types = '';

if ($useStudent) {
    [$studentSql, $studentParams, $studentTypes] = buildBranch('students', 's', 'studentID', 'Student', $from, $to, $dept, $reason);
    $parts[] = $studentSql;
    $params = array_merge($params, $studentParams);
    $types .= $studentTypes;
}

if ($useEmployee) {
    [$employeeSql, $employeeParams, $employeeTypes] = buildBranch('employees', 'e', 'emplID', 'Employee', $from, $to, $dept, $reason);
    $parts[] = $employeeSql;
    $params = array_merge($params, $employeeParams);
    $types .= $employeeTypes;
}

if (empty($parts)) {
    echo json_encode([
        'summary' => [
            'totalVisits' => 0,
            'uniqueVisitors' => 0,
            'studentVisits' => 0,
            'employeeVisits' => 0,
            'blockedVisits' => 0,
            'rangeLabel' => 'No data'
        ],
        'trend' => ['labels' => [], 'counts' => []],
        'reasons' => [],
        'departments' => [],
        'types' => [],
        'hours' => [],
        'topVisitors' => [],
        'blockedTrend' => []
    ]);
    exit();
}

$baseSql = implode(" UNION ALL ", $parts);

function getScalar($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res ? array_values($res)[0] : 0;
}

function getRows($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$summary = [
    'totalVisits' => (int) getScalar($conn, "SELECT COUNT(*) FROM ($baseSql) t", $types, $params),
    'uniqueVisitors' => (int) getScalar($conn, "SELECT COUNT(DISTINCT CONCAT(t.user_type, ':', t.user_identifier)) FROM ($baseSql) t", $types, $params),
    'studentVisits' => (int) getScalar($conn, "SELECT COUNT(*) FROM ($baseSql) t WHERE t.user_type = 'Student'", $types, $params),
    'employeeVisits' => (int) getScalar($conn, "SELECT COUNT(*) FROM ($baseSql) t WHERE t.user_type = 'Employee'", $types, $params),
    'blockedVisits' => (int) getScalar($conn, "SELECT COUNT(*) FROM ($baseSql) t WHERE LOWER(t.status) = 'blocked'", $types, $params)
];

$rangeLabel = 'All time';
if (!empty($from) || !empty($to)) {
    $rangeLabel = trim(($from ?: '...') . ' to ' . ($to ?: '...'));
} elseif ($preset === '30d') {
    $rangeLabel = 'Last 30 days';
} elseif ($preset === '7d') {
    $rangeLabel = 'Last 7 days';
} elseif ($preset === '90d') {
    $rangeLabel = 'Last 90 days';
} elseif ($preset === 'year') {
    $rangeLabel = 'Year to date';
}
$summary['rangeLabel'] = $rangeLabel;

$trendRows = getRows($conn, "SELECT t.date, COUNT(*) as total FROM ($baseSql) t GROUP BY t.date ORDER BY t.date ASC", $types, $params);
$trend = ['labels' => [], 'counts' => []];
foreach ($trendRows as $row) {
    $trend['labels'][] = $row['date'];
    $trend['counts'][] = (int) $row['total'];
}

$reasonRows = getRows($conn, "SELECT t.reason, COUNT(*) as total FROM ($baseSql) t GROUP BY t.reason ORDER BY total DESC", $types, $params);
$reasons = [];
foreach ($reasonRows as $row) {
    $reasons[] = ['label' => $row['reason'], 'value' => (int) $row['total']];
}

$deptRows = getRows($conn, "SELECT COALESCE(t.departmentName, 'Unassigned') as dept, COUNT(*) as total FROM ($baseSql) t GROUP BY dept ORDER BY total DESC", $types, $params);
$departments = [];
foreach ($deptRows as $row) {
    $departments[] = ['label' => $row['dept'], 'value' => (int) $row['total']];
}

$typeRows = getRows($conn, "SELECT t.user_type, COUNT(*) as total FROM ($baseSql) t GROUP BY t.user_type", $types, $params);
$typesData = [];
foreach ($typeRows as $row) {
    $typesData[] = ['label' => $row['user_type'], 'value' => (int) $row['total']];
}

$hourRows = getRows($conn, "SELECT HOUR(t.time) as hour_val, COUNT(*) as total FROM ($baseSql) t GROUP BY hour_val ORDER BY hour_val ASC", $types, $params);
$hours = array_fill(0, 24, 0);
foreach ($hourRows as $row) {
    $hour = (int) $row['hour_val'];
    $hours[$hour] = (int) $row['total'];
}

$topRows = getRows($conn, "SELECT t.user_identifier, t.user_type, CONCAT(t.firstName, ' ', t.lastName) as full_name, COUNT(*) as total
    FROM ($baseSql) t
    GROUP BY t.user_identifier, t.user_type, full_name
    ORDER BY total DESC
    LIMIT 10", $types, $params);

$blockedTrendRows = getRows($conn, "SELECT t.date, COUNT(*) as total
    FROM ($baseSql) t
    WHERE LOWER(t.status) = 'blocked'
    GROUP BY t.date
    ORDER BY t.date ASC", $types, $params);
$blockedTrend = ['labels' => [], 'counts' => []];
foreach ($blockedTrendRows as $row) {
    $blockedTrend['labels'][] = $row['date'];
    $blockedTrend['counts'][] = (int) $row['total'];
}

echo json_encode([
    'summary' => $summary,
    'trend' => $trend,
    'reasons' => $reasons,
    'departments' => $departments,
    'types' => $typesData,
    'hours' => $hours,
    'topVisitors' => $topRows,
    'blockedTrend' => $blockedTrend
]);
