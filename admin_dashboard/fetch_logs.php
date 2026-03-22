<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once '../includes/db_connect.php';

date_default_timezone_set('Asia/Manila'); 
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime("-1 day"));

$statsReason = trim($_GET['stats_reason'] ?? '');
$statsDept = trim($_GET['stats_dept'] ?? '');
$statsRole = trim($_GET['stats_role'] ?? '');
$statsFrom = trim($_GET['stats_from'] ?? '');
$statsTo = trim($_GET['stats_to'] ?? '');

$statsRole = in_array($statsRole, ['Student', 'Employee'], true) ? $statsRole : '';
$reasonOptions = [
    'Research',
    'Study',
    'Group Study',
    'Borrowing',
    'Clearance',
    'ID Validation',
    'Resting',
    'Computer Use',
    'Printing',
    'E-Resources',
    'Event'
];

$baseFrom = "
    FROM history_logs h
    LEFT JOIN students s ON h.user_identifier = s.studentID AND h.user_type = 'Student'
    LEFT JOIN employees e ON h.user_identifier = e.emplID AND h.user_type = 'Employee'
    LEFT JOIN departments d ON d.departmentID = COALESCE(s.departmentID, e.departmentID)
";

$filterSql = "";
$filterParams = [];
$filterTypes = "";

if ($statsReason !== '') {
    if ($statsReason === 'Others') {
        $placeholders = implode(',', array_fill(0, count($reasonOptions), '?'));
        $filterSql .= " AND h.reason NOT IN ($placeholders)";
        foreach ($reasonOptions as $opt) {
            $filterParams[] = $opt;
            $filterTypes .= "s";
        }
    } else {
        $filterSql .= " AND h.reason = ?";
        $filterParams[] = $statsReason;
        $filterTypes .= "s";
    }
}
if ($statsDept !== '') {
    $filterSql .= " AND d.departmentID = ?";
    $filterParams[] = $statsDept;
    $filterTypes .= "s";
}
if ($statsRole !== '') {
    $filterSql .= " AND h.user_type = ?";
    $filterParams[] = $statsRole;
    $filterTypes .= "s";
}

function runCountQuery($conn, $baseFrom, $whereSql, $params, $types) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count $baseFrom WHERE $whereSql");
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res['count'] ?? 0;
}

// Analytics Queries (filtered)
$countToday = runCountQuery(
    $conn,
    $baseFrom,
    "h.date = ?$filterSql",
    array_merge([$today], $filterParams),
    "s" . $filterTypes
);

$countTodayStudents = runCountQuery(
    $conn,
    $baseFrom,
    "h.date = ? AND h.user_type = 'Student'$filterSql",
    array_merge([$today], $filterParams),
    "s" . $filterTypes
);

$countTodayEmployees = runCountQuery(
    $conn,
    $baseFrom,
    "h.date = ? AND h.user_type = 'Employee'$filterSql",
    array_merge([$today], $filterParams),
    "s" . $filterTypes
);

$countYesterday = runCountQuery(
    $conn,
    $baseFrom,
    "h.date = ?$filterSql",
    array_merge([$yesterday], $filterParams),
    "s" . $filterTypes
);

$countWeek = runCountQuery(
    $conn,
    $baseFrom,
    "h.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)$filterSql",
    $filterParams,
    $filterTypes
);

$countPrevWeek = runCountQuery(
    $conn,
    $baseFrom,
    "h.date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND h.date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)$filterSql",
    $filterParams,
    $filterTypes
);

$countMonth = runCountQuery(
    $conn,
    $baseFrom,
    "MONTH(h.date) = MONTH(CURRENT_DATE()) AND YEAR(h.date) = YEAR(CURRENT_DATE())$filterSql",
    $filterParams,
    $filterTypes
);

$countPrevMonth = runCountQuery(
    $conn,
    $baseFrom,
    "MONTH(h.date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(h.date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))$filterSql",
    $filterParams,
    $filterTypes
);

$countOverall = runCountQuery(
    $conn,
    $baseFrom,
    "1=1$filterSql",
    $filterParams,
    $filterTypes
);

$blockedRes = $conn->query("SELECT (SELECT COUNT(*) FROM students WHERE status = 'Blocked') + (SELECT COUNT(*) FROM employees WHERE status = 'Blocked') as total_blocked");
$totalBlockedGlobal = $blockedRes->fetch_assoc()['total_blocked'] ?? 0;

$activeRes = $conn->query("SELECT (SELECT COUNT(*) FROM students WHERE status = 'Active') + (SELECT COUNT(*) FROM employees WHERE status = 'Active') as count");
$totalActiveGlobal = $activeRes->fetch_assoc()['count'] ?? 0;

$totalUsersRes = $conn->query("SELECT (SELECT COUNT(*) FROM students) + (SELECT COUNT(*) FROM employees) as total_users");
$totalUsersGlobal = $totalUsersRes->fetch_assoc()['total_users'] ?? 0;

$trendWeek = ($countPrevWeek > 0) ? round((($countWeek - $countPrevWeek) / $countPrevWeek) * 100) : ($countWeek > 0 ? 100 : 0);
$trendMonth = ($countPrevMonth > 0) ? round((($countMonth - $countPrevMonth) / $countPrevMonth) * 100) : ($countMonth > 0 ? 100 : 0);

$rangeCount = 0;
$rangeLabel = "Select range";
if ($statsFrom !== '' || $statsTo !== '') {
    $whereRange = "1=1";
    $rangeParams = $filterParams;
    $rangeTypes = $filterTypes;
    if ($statsFrom !== '' && $statsTo !== '') {
        $whereRange = "h.date BETWEEN ? AND ?$filterSql";
        array_unshift($rangeParams, $statsTo);
        array_unshift($rangeParams, $statsFrom);
        $rangeTypes = "ss" . $filterTypes;
        $rangeLabel = date('M d, Y', strtotime($statsFrom)) . " - " . date('M d, Y', strtotime($statsTo));
    } elseif ($statsFrom !== '') {
        $whereRange = "h.date >= ?$filterSql";
        array_unshift($rangeParams, $statsFrom);
        $rangeTypes = "s" . $filterTypes;
        $rangeLabel = "From " . date('M d, Y', strtotime($statsFrom));
    } elseif ($statsTo !== '') {
        $whereRange = "h.date <= ?$filterSql";
        array_unshift($rangeParams, $statsTo);
        $rangeTypes = "s" . $filterTypes;
        $rangeLabel = "Up to " . date('M d, Y', strtotime($statsTo));
    }
    $rangeCount = runCountQuery($conn, $baseFrom, $whereRange, $rangeParams, $rangeTypes);
}

// Table Data Query
$logQuery = "
    SELECT 
        h.logID, h.user_identifier, h.user_type, h.date, h.time, h.reason,
        s.firstName as sFN, s.lastName as sLN, e.firstName as eFN, e.lastName as eLN,
        COALESCE(s.profile_image, e.profile_image) as user_pic,
        COALESCE(s.status, e.status) as user_status,
        d.departmentName as program
    FROM history_logs h
    LEFT JOIN students s ON h.user_identifier = s.studentID AND h.user_type = 'Student'
    LEFT JOIN employees e ON h.user_identifier = e.emplID AND h.user_type = 'Employee'
    LEFT JOIN departments d ON d.departmentID = COALESCE(s.departmentID, e.departmentID)
    WHERE h.date = '$today' 
    ORDER BY h.time DESC";

$logsResult = $conn->query($logQuery);

$html = "";
if($logsResult && $logsResult->num_rows > 0) {
    while($row = $logsResult->fetch_assoc()) {
        // Handle names carefully for deleted users
        $firstName = $row['sFN'] ?? $row['eFN'] ?? 'Unknown';
        $lastName = $row['sLN'] ?? $row['eLN'] ?? 'User';
        $isDeleted = ($firstName === 'Unknown');

        $statusRaw = $row['user_status'] ?? 'N/A';
        $isBlocked = (strtolower($statusRaw) === 'blocked');
        $uType = strtolower($row['user_type']);
        $pTypeFolder = ($uType === 'student') ? 'student' : 'admin'; 
        
        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
        $photoFilename = $row['user_pic'] ?? '';
        $isRemotePhoto = (!empty($photoFilename) && preg_match('/^https?:\\/\\//i', $photoFilename));
        $userPhotoUrl = $isRemotePhoto ? $photoFilename : "../profilepictures/" . $pTypeFolder . "/" . $photoFilename;
        $userPhotoFsPath = __DIR__ . "/../profilepictures/" . $pTypeFolder . "/" . $photoFilename;
        
        if (!$isDeleted && ($isRemotePhoto || (!empty($photoFilename) && $photoFilename !== 'default.png' && is_file($userPhotoFsPath)))) {
            $cacheBuster = (!$isRemotePhoto && is_file($userPhotoFsPath)) ? ('?v=' . filemtime($userPhotoFsPath)) : '';
            $imgHtml = "<img src='" . htmlspecialchars($userPhotoUrl . $cacheBuster, ENT_QUOTES) . "' class='visitor-avatar me-3 shadow-sm' onerror=\"this.outerHTML='<div class=\\'initials-avatar me-3 shadow-sm\\'>$initials</div>'\">";
        } else {
            $imgHtml = "<div class='initials-avatar me-3 shadow-sm' style='background:#0038a8;'>$initials</div>";
        }

        $fullName = htmlspecialchars($firstName . ' ' . $lastName);
        
        // Logic for Action Button
        if ($isDeleted) {
            $actionBtn = "<span class='badge bg-secondary opacity-50'>System Record</span>";
            $statusBadge = "<span class='badge bg-secondary rounded-pill' style='font-size:10px;'>DELETED</span>";
        } else {
            $btnClass = $isBlocked ? 'btn-unblock' : 'btn-block';
            $btnText = $isBlocked ? 'Unblock' : 'Block User';
            
            $actionBtn = "<button type='button' class='btn btn-status-action $btnClass shadow-sm status-action-btn' data-id='".htmlspecialchars($row['user_identifier'], ENT_QUOTES)."' data-type='".htmlspecialchars($uType, ENT_QUOTES)."' data-status='".htmlspecialchars($statusRaw, ENT_QUOTES)."' data-name='".htmlspecialchars($fullName, ENT_QUOTES)."'>$btnText</button>";
            $statusBadge = "<span class='badge ".($isBlocked ? 'bg-danger' : 'bg-success')." rounded-pill' style='font-size:10px;'>".strtoupper($statusRaw)."</span>";
        }

        $html .= "
        <tr>
            <td class='ps-4'>
                <div class='d-flex align-items-center'>
                    $imgHtml
                    <div>
                        <div class='fw-bold'>$fullName</div>
                        <div class='text-muted small'>ID: ".htmlspecialchars($row['user_identifier'])."</div>
                    </div>
                </div>
            </td>
            <td class='small text-muted'>".htmlspecialchars($row['program'] ?? 'N/A')."</td>
            <td><span class='badge-role ".($uType === 'student' ? 'role-student' : 'role-employee')."'>" . strtoupper($row['user_type']) . "</span></td>
            <td>
                <div class='fw-bold small text-blue'>" . date('M d, Y', strtotime($row['date'])) . "</div>
                <div class='text-muted small'>" . date('h:i A', strtotime($row['time'])) . "</div>
            </td>
            <td>
                <span class='small text-dark fw-medium'>
                    <i class='bi bi-chat-left-text me-1 text-blue'></i> " . htmlspecialchars($row['reason'] ?: 'N/A') . "
                </span>
            </td>
            <td>$statusBadge</td>
            <td class='text-center action-col'>$actionBtn</td>
        </tr>";
    }
} else {
    $html = "<tr><td colspan='7' class='text-center py-5 text-muted'>No visitors logged today ($today).</td></tr>";
}

echo json_encode([
    'html' => $html,
    'todayCount' => (int)$countToday,
    'todayStudentCount' => (int)$countTodayStudents,
    'todayEmployeeCount' => (int)$countTodayEmployees,
    'yesterdayCount' => (int)$countYesterday,
    'overallCount' => (int)$countOverall,
    'globalBlockedCount' => (int)$totalBlockedGlobal,
    'totalActiveCount' => (int)$totalActiveGlobal,
    'totalUsersCount' => (int)$totalUsersGlobal,
    'weekCount' => (int)$countWeek,
    'monthCount' => (int)$countMonth,
    'trendWeek' => (int)$trendWeek,
    'trendMonth' => (int)$trendMonth,
    'rangeCount' => (int)$rangeCount,
    'rangeLabel' => $rangeLabel
]);
