<?php
session_start();
require_once '../includes/db_connect.php';

// Restrict visitor history export to logged-in admins
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit('Unauthorized access');
}

$search = trim($_GET['search'] ?? '');
$filterDate = trim($_GET['filterDate'] ?? '');
$filterMonth = trim($_GET['filterMonth'] ?? '');
$filterYear = trim($_GET['filterYear'] ?? '');
$filterReason = trim($_GET['filterReason'] ?? '');
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

$sql = "SELECT h.*, 
        CASE WHEN h.user_type = 'Student' THEN s.firstName ELSE e.firstName END as fName,
        CASE WHEN h.user_type = 'Student' THEN s.lastName ELSE e.lastName END as lName,
        CASE WHEN h.user_type = 'Student' THEN d1.departmentName ELSE d2.departmentName END as deptName
        FROM history_logs h
        LEFT JOIN students s ON h.user_identifier = s.studentID AND h.user_type = 'Student'
        LEFT JOIN employees e ON h.user_identifier = e.emplID AND h.user_type = 'Employee'
        LEFT JOIN departments d1 ON s.departmentID = d1.departmentID
        LEFT JOIN departments d2 ON e.departmentID = d2.departmentID
        WHERE 1=1";

$where = [];
$params = [];
$types = "";

if ($search !== '') {
    $like = "%$search%";
    $where[] = "(s.firstName LIKE ? OR s.lastName LIKE ? OR e.firstName LIKE ? OR e.lastName LIKE ? OR h.user_identifier LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
    $types .= "sssss";
}
if ($filterDate !== '') {
    $where[] = "h.date = ?";
    $params[] = $filterDate;
    $types .= "s";
}
if ($filterMonth !== '' && ctype_digit($filterMonth)) {
    $where[] = "MONTH(h.date) = ?";
    $params[] = (int)$filterMonth;
    $types .= "i";
}
if ($filterYear !== '' && ctype_digit($filterYear)) {
    $where[] = "YEAR(h.date) = ?";
    $params[] = (int)$filterYear;
    $types .= "i";
}
if ($filterReason !== '') {
    if ($filterReason === 'Others') {
        $placeholders = implode(',', array_fill(0, count($reasonOptions), '?'));
        $where[] = "h.reason NOT IN ($placeholders)";
        foreach ($reasonOptions as $opt) {
            $params[] = $opt;
            $types .= "s";
        }
    } else {
        $where[] = "h.reason = ?";
        $params[] = $filterReason;
        $types .= "s";
    }
}

if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}

$sql .= " ORDER BY h.date DESC, h.time DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $roleClass = ($row['user_type'] == 'Student') ? 'role-student' : 'role-employee';
        echo "<tr>
                <td class='ps-4 fw-bold text-blue'>{$row['user_identifier']}</td>
                <td class='fw-semibold'>".htmlspecialchars($row['fName'].' '.$row['lName'])."</td>
                <td><span class='badge-role $roleClass'>".strtoupper($row['user_type'])."</span></td>
                <td class='small text-muted'>".htmlspecialchars($row['deptName'] ?? 'N/A')."</td>
                <td class='small'><i>".htmlspecialchars($row['reason'])."</i></td>
                <td class='fw-bold'>".date('M d, Y', strtotime($row['date']))."</td>
                <td class='text-blue fw-bold'>".date('h:i A', strtotime($row['time']))."</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='7' class='text-center py-5 text-muted'>No records found.</td></tr>";
}
?>
