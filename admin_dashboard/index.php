<?php
session_start();
require_once '../includes/db_connect.php'; 
require_once '../includes/csrf.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$adminID = $_SESSION['emplID']; 
$adminName = $_SESSION['admin_name'];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime("-1 day"));

// Fetch Admin Profile
$adminQuery = $conn->prepare("SELECT profile_image, firstName, lastName FROM employees WHERE emplID = ?");
$adminQuery->bind_param("s", $adminID);
$adminQuery->execute();
$adminData = $adminQuery->get_result()->fetch_assoc() ?: [
    'profile_image' => null,
    'firstName' => 'Administrator',
    'lastName' => ''
];

function getInitials($firstname, $lastname) {
    return strtoupper(substr($firstname ?? '', 0, 1) . substr($lastname ?? '', 0, 1));
}

$photoFilename = $adminData['profile_image'] ?? null;
$isRemoteAdminPhoto = (!empty($photoFilename) && preg_match('/^https?:\\/\\//i', $photoFilename));
$photoFilePath = __DIR__ . "/../profilepictures/admin/" . $photoFilename;
$cacheBuster = (!$isRemoteAdminPhoto && !empty($photoFilename) && is_file($photoFilePath)) ? ('?v=' . filemtime($photoFilePath)) : '';
$photoUrl = $isRemoteAdminPhoto ? $photoFilename : "../profilepictures/admin/" . $photoFilename . $cacheBuster;
$hasPhoto = ($isRemoteAdminPhoto || (!empty($photoFilename) && is_file($photoFilePath)));
$adminInitials = getInitials($adminData['firstName'] ?? '', $adminData['lastName'] ?? '');
$csrfToken = csrf_token();

// --- TREND CALCULATIONS ---
$todayRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date = '$today'");
$countToday = $todayRes->fetch_assoc()['count'] ?? 0;
$yesterdayRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date = '$yesterday'");
$countYesterday = $yesterdayRes->fetch_assoc()['count'] ?? 0;
$trendDay = ($countYesterday > 0) ? round((($countToday - $countYesterday) / $countYesterday) * 100) : ($countToday > 0 ? 100 : 0);

$weekRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$countWeek = $weekRes->fetch_assoc()['count'] ?? 0;
$prevWeekRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$countPrevWeek = $prevWeekRes->fetch_assoc()['count'] ?? 0;
$trendWeek = ($countPrevWeek > 0) ? round((($countWeek - $countPrevWeek) / $countPrevWeek) * 100) : ($countWeek > 0 ? 100 : 0);

$monthRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
$countMonth = $monthRes->fetch_assoc()['count'] ?? 0;
$prevMonthRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE MONTH(date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))");
$countPrevMonth = $prevMonthRes->fetch_assoc()['count'] ?? 0;
$trendMonth = ($countPrevMonth > 0) ? round((($countMonth - $countPrevMonth) / $countPrevMonth) * 100) : ($countMonth > 0 ? 100 : 0);

$overallRes = $conn->query("SELECT COUNT(*) as count FROM history_logs");
$countOverall = $overallRes->fetch_assoc()['count'] ?? 0;
$totalUsersRes = $conn->query("SELECT (SELECT COUNT(*) FROM students) + (SELECT COUNT(*) FROM employees) as total_users");
$countTotalUsers = $totalUsersRes->fetch_assoc()['total_users'] ?? 0;
$activeRes = $conn->query("SELECT (SELECT COUNT(*) FROM students WHERE status != 'Blocked') + (SELECT COUNT(*) FROM employees WHERE status != 'Blocked') as count");
$countActive = $activeRes->fetch_assoc()['count'] ?? 0;
$blockedRes = $conn->query("SELECT (SELECT COUNT(*) FROM students WHERE LOWER(status) = 'blocked') + (SELECT COUNT(*) FROM employees WHERE LOWER(status) = 'blocked') as total_blocked");
$countBlocked = $blockedRes->fetch_assoc()['total_blocked'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | NEU Library Admin</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --neu-blue: #0038a8; --neu-hover: #002a80; --bg-light: #f1f4f9; }
        body { background-color: var(--bg-light); font-family: 'Segoe UI', sans-serif; }
        .navbar { background: white; border-bottom: 2px solid var(--neu-blue); padding: 0.5rem 2rem; }
        .nav-link { font-weight: 600; color: #555; transition: 0.2s; border-radius: 8px; margin: 0 3px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 0.9rem; }
        .nav-link:hover { color: var(--neu-blue); background: #f8f9fa; }
        .nav-link.active { color: var(--neu-blue) !important; background: #eef2ff; }
        
        .text-blue { color: var(--neu-blue) !important; }
        .btn-blue { background-color: var(--neu-blue); color: white; border-radius: 50px; }
        .btn-blue:hover { background-color: var(--neu-hover); color: white; }
        
        .analytics-card { 
            background: white; border-radius: 12px; padding: 1.25rem; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #eee;
            position: relative; transition: transform 0.2s; height: 100%;
        }
        .analytics-card:hover { transform: translateY(-5px); }
        .card-label { font-size: 0.75rem; font-weight: 700; color: #8e8e8e; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-value { font-size: 1.8rem; font-weight: 800; color: #1a1a1a; margin: 5px 0; }
        .card-icon-box { 
            position: absolute; top: 20px; right: 20px; 
            width: 40px; height: 40px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        }
        .trend-up { font-size: 0.75rem; color: #10b981; font-weight: 600; background: #ecfdf5; padding: 2px 8px; border-radius: 4px; }
        .trend-down { font-size: 0.75rem; color: #ef4444; font-weight: 600; background: #fef2f2; padding: 2px 8px; border-radius: 4px; }

        .stat-card { background: white; border-radius: 15px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-left: 5px solid var(--neu-blue); }
        .stat-card.blocked { border-left-color: #dc3545; }
        .table-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .visitor-avatar { width: 45px; height: 45px; object-fit: cover; border-radius: 50%; }
        .initials-avatar { width: 45px; height: 45px; border-radius: 50%; background: var(--neu-blue); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .filter-section { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .stats-filter-grid { display: grid; grid-template-columns: repeat(6, minmax(120px, 1fr)); gap: 12px; align-items: end; }
        .stats-filter-grid .form-select, .stats-filter-grid .form-control { padding: 8px 10px; font-size: 0.85rem; }
        .stats-filter-grid .form-label { font-size: 0.7rem; }
        .stats-filter-grid .btn { height: 38px; }
        .visitor-filter-grid { display: grid; grid-template-columns: minmax(220px, 2fr) repeat(3, minmax(140px, 1fr)) minmax(90px, 120px); gap: 12px; align-items: center; }
        .visitor-filter-grid .form-select, .visitor-filter-grid .form-control { padding: 8px 10px; font-size: 0.85rem; }
        .visitor-filter-grid .btn { height: 38px; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, minmax(200px, 1fr)); gap: 16px; }
        @media (max-width: 992px) { .stats-grid { grid-template-columns: repeat(2, minmax(180px, 1fr)); } }
        @media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }
        .highlight-grid { display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 16px; }
        @media (max-width: 992px) { .highlight-grid { grid-template-columns: repeat(2, minmax(200px, 1fr)); } }
        @media (max-width: 640px) { .highlight-grid { grid-template-columns: 1fr; } }
        .badge-role { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .role-student { background: #eef2ff; color: #4338ca; }
        .role-employee { background: #fff7ed; color: #c2410c; }
        .btn-status-action { font-size: 0.75rem; font-weight: 700; padding: 6px 14px; border-radius: 8px; text-transform: uppercase; border: none; }
        .btn-block { background-color: #fee2e2; color: #dc2626; }
        .btn-unblock { background-color: #dcfce7; color: #16a34a; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; }}
        .animate-pulse { animation: pulse 1.5s infinite; color: #fff; margin-right: 5px; }

          .pagination .page-link { color: var(--neu-blue); border: none; margin: 0 2px; border-radius: 5px; cursor: pointer; }
          .pagination .page-item.active .page-link { background-color: var(--neu-blue); color: white; }

          @media (max-width: 992px) {
              .navbar { padding: 0.5rem 1rem; }
              .stats-filter-grid { grid-template-columns: repeat(3, minmax(120px, 1fr)); }
              .visitor-filter-grid { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
          }
          @media (max-width: 768px) {
              .stats-filter-grid { grid-template-columns: repeat(2, minmax(120px, 1fr)); }
              .visitor-filter-grid { grid-template-columns: 1fr 1fr; }
              .visitor-filter-grid .btn { width: 100%; }
          }
          @media (max-width: 576px) {
              .stats-filter-grid { grid-template-columns: 1fr; }
              .visitor-filter-grid { grid-template-columns: 1fr; }
              .analytics-card .card-value { font-size: 1.5rem; }
          }
      </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top navbar-light">
    <div class="container-fluid">
        <a href="index.php" class="navbar-brand fw-bold text-blue d-flex align-items-center">
            <img src="../assets/neu.png" alt="Logo" height="35" class="me-2"> NEU LIBRARY ADMIN
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto ms-lg-4">
                <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="visitor_history.php"><i class="bi bi-clock-history"></i> Visitor History Logs</a></li>
                <li class="nav-item"><a class="nav-link" href="blocklist.php"><i class="bi bi-shield-slash"></i> Blocklist</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="stats.php"><i class="bi bi-graph-up"></i> Statistics</a></li>
                <li class="nav-item"><a class="nav-link" href="user_management.php"><i class="bi bi-people"></i> Users</a></li>
            </ul>
            <div class="vr ms-3 me-3"></div>
            <div class="d-flex align-items-center">
                <div class="text-end me-2 d-none d-md-block">
                    <div class="fw-bold small"><?php echo htmlspecialchars($adminData['firstName'] . ' ' . $adminData['lastName']); ?></div>
                    <div class="text-muted" style="font-size: 10px;">Administrator</div>
                </div>
                <?php if ($hasPhoto): ?>
                    <img src="<?php echo htmlspecialchars($photoUrl); ?>" class="rounded-circle me-3" style="width:40px; height:40px; object-fit:cover; border: 2px solid var(--neu-blue);">
                <?php else: ?>
                    <div class="initials-avatar me-3"><?php echo $adminInitials; ?></div>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-sm btn-outline-danger px-3 rounded-pill">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 px-md-5 mt-4">
    <?php if (isset($_SESSION['show_welcome']) && $_SESSION['show_welcome'] === true): ?>
        <div id="welcomeAlert" class="alert alert-white shadow-sm border-0 d-flex align-items-center p-4 animate__animated animate__fadeInDown" 
             style="border-left: 5px solid var(--neu-blue) !important; border-radius: 15px; background: white;">
            <div class="bg-light rounded-circle p-3 me-3 text-blue">
                <i class="bi bi-hand-thumbs-up-fill fs-4" style="color: var(--neu-blue);"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0 text-blue" style="color: var(--neu-blue);">Welcome back, <?= htmlspecialchars($adminData['firstName']) ?>!</h4>
                <p class="text-muted mb-0 small">You are logged in as a System Administrator. Have a productive day!</p>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
            // Unset immediately so it doesn't show on refresh or when coming back from other pages
            unset($_SESSION['show_welcome']); 
        ?>
    <?php endif; ?>
</div>

<div class="container-fluid px-4 px-md-5 py-4">
    <?php if(isset($_GET['msg'])): ?>
        <div id="statusAlert" class="alert <?php echo ($_GET['msg'] == 'StatusUpdated') ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show shadow-sm border-0 mb-4" role="alert" style="border-left: 5px solid <?php echo ($_GET['msg'] == 'StatusUpdated') ? '#10b981' : '#dc3545'; ?> !important;">
            <i class="bi <?php echo ($_GET['msg'] == 'StatusUpdated') ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-2"></i>
            <strong><?php echo ($_GET['msg'] == 'StatusUpdated') ? 'Success!' : 'Error!'; ?></strong> 
            <?php echo ($_GET['msg'] == 'StatusUpdated') ? 'User status has been updated successfully.' : 'An error occurred while updating status.'; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Daily Attendance Overview</h2>
            <p class="text-muted">Live dashboard for <strong><?php echo date('F d, Y'); ?></strong></p>
        </div>
        <div class="dropdown">
            <button class="btn btn-blue px-4 shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-download me-2"></i> Export Logs
            </button>
            <ul class="dropdown-menu shadow border-0">
                <li><a class="dropdown-item" href="#" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i> Save as PDF</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportToCSV()"><i class="bi bi-filetype-csv me-2 text-success"></i> Save as Excel/CSV</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="window.print()"><i class="bi bi-printer me-2 text-primary"></i> Print View</a></li>
            </ul>
        </div>
    </div>

    <div class="stats-grid mb-4">
        <div>
            <div class="analytics-card">
                <div class="card-label">Visitors Today</div>
                <div class="card-value text-blue" id="valToday"><?php echo number_format($countToday); ?></div>
                <div class="card-icon-box" style="background: #e0f2fe; color: #0369a1;"><i class="bi bi-people-fill"></i></div>
                <span id="trendToday" class="<?php echo ($trendDay >= 0) ? 'trend-up' : 'trend-down'; ?>">
                    <i id="trendIcon" class="bi <?php echo ($trendDay >= 0) ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow'; ?>"></i> 
                    <span id="trendText"><?php echo abs($trendDay); ?>%</span>
                </span> 
                <span class="text-muted small">vs yesterday</span>
            </div>
        </div>
        <div>
            <div class="analytics-card">
                <div class="card-label">This Week</div>
                <div class="card-value" style="color: #a16207;" id="valWeek"><?php echo number_format($countWeek); ?></div>
                <div class="card-icon-box" style="background: #fef9c3; color: #a16207;"><i class="bi bi-calendar-event"></i></div>
                <span id="trendWeek" class="<?php echo ($trendWeek >= 0) ? 'trend-up' : 'trend-down'; ?>">
                    <i class="bi <?php echo ($trendWeek >= 0) ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow'; ?>"></i> <?php echo abs($trendWeek); ?>%
                </span> 
                <span class="text-muted small">vs last week</span>
            </div>
        </div>
        <div>
            <div class="analytics-card">
                <div class="card-label">This Month</div>
                <div class="card-value" style="color: #15803d;" id="valMonth"><?php echo number_format($countMonth); ?></div>
                <div class="card-icon-box" style="background: #dcfce7; color: #15803d;"><i class="bi bi-calendar-month"></i></div>
                <span id="trendMonth" class="<?php echo ($trendMonth >= 0) ? 'trend-up' : 'trend-down'; ?>">
                    <i class="bi <?php echo ($trendMonth >= 0) ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow'; ?>"></i> <?php echo abs($trendMonth); ?>%
                </span> 
                <span class="text-muted small">vs last month</span>
            </div>
        </div>
        <div>
            <div class="analytics-card">
                <div class="card-label">Custom Range</div>
                <div class="card-value" style="color: #0f766e;" id="valRange">0</div>
                <div class="card-icon-box" style="background: #ccfbf1; color: #0f766e;"><i class="bi bi-calendar-range"></i></div>
                <span class="text-muted small" id="rangeLabel">Select range</span>
            </div>
        </div>
        <div>
            <div class="analytics-card">
                <div class="card-label">Overall Visitors</div>
                <div class="card-value" style="color: #0369a1;" id="valOverall"><?php echo number_format($countOverall); ?></div>
                <div class="card-icon-box" style="background: #f0fdf4; color: #16a34a;"><i class="bi bi-person-check-fill"></i></div>
                <span class="text-muted small">All-time logs</span>
            </div>
        </div>
        <div>
            <div class="analytics-card" id="securityCard">
                <div class="card-label">Security Status</div>
                <div class="card-value" id="securityStatusText" style="color: #be185d;">OK</div>
                <div class="card-icon-box" id="securityIconBox" style="background: #fce7f3; color: #be185d;">
                    <i class="bi bi-shield-check" id="securityIcon"></i>
                </div>
                <span id="securitySubtext" class="trend-up" style="background: #fce7f3; color: #be185d;">all systems safe</span>
            </div>
        </div>
    </div>

    <div class="highlight-grid mb-4">
        <div class="analytics-card">
            <div class="card-label">Total Users</div>
            <div class="card-value" style="color: #0f766e;" id="valTotalUsers"><?php echo number_format($countTotalUsers); ?></div>
            <div class="card-icon-box" style="background: #ccfbf1; color: #0f766e;"><i class="bi bi-people-fill"></i></div>
            <span class="trend-up" style="background: #ccfbf1; color: #0f766e;"><i class="bi bi-person-plus-fill"></i> Students + Employees</span>
        </div>
        <div class="analytics-card">
            <div class="card-label">Total Active</div>
            <div class="card-value" style="color: #7e22ce;" id="valActive"><?php echo number_format($countActive); ?></div>
            <div class="card-icon-box" style="background: #f3e8ff; color: #7e22ce;"><i class="bi bi-bar-chart-fill"></i></div>
            <span class="trend-up" style="background: #f3e8ff; color: #7e22ce;"><i class="bi bi-arrow-up-right"></i> System Wide</span>
        </div>
        <div class="stat-card blocked d-flex justify-content-between align-items-center h-100">
            <div>
                <div class="text-danger small fw-bold mb-1">GLOBAL BLOCKED ACCOUNTS</div>
                <h2 class="fw-bold mb-0 text-danger" id="valBlocked"><?php echo $countBlocked; ?></h2>
            </div>
            <i class="bi bi-shield-lock-fill text-danger fs-1 opacity-1"></i>
        </div>
    </div>

    <div class="filter-section mb-4">
        <div class="stats-filter-grid">
            <div>
                <label class="small fw-bold text-muted">Reason</label>
                <select id="statsReason" class="form-select form-select-sm">
                    <option value="">All Reasons</option>
                    <option value="Research">Research / Thesis Work</option>
                    <option value="Study">Quiet Study</option>
                    <option value="Group Study">Group Study / Collaboration</option>
                    <option value="Borrowing">Borrowing/Returning Books</option>
                    <option value="Clearance">Clearance Signing</option>
                    <option value="ID Validation">Library Card / ID Validation</option>
                    <option value="Resting">Resting / Between Classes</option>
                    <option value="Computer Use">Computer / Internet Access</option>
                    <option value="Printing">Printing / Photocopying Services</option>
                    <option value="E-Resources">Accessing Online Databases/E-Books</option>
                    <option value="Event">School Event / Seminar / Orientation</option>
                    <option value="Others">Others</option>
                </select>
            </div>
            <div>
                <label class="small fw-bold text-muted">Department</label>
                <select id="statsDept" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php
                    $depts = $conn->query("SELECT departmentID, departmentName FROM departments ORDER BY departmentName ASC");
                    while($d = $depts->fetch_assoc()) {
                        echo "<option value='".htmlspecialchars($d['departmentID'])."'>".$d['departmentName']."</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label class="small fw-bold text-muted">User Type</label>
                <select id="statsRole" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Student">Student</option>
                    <option value="Employee">Employee</option>
                </select>
            </div>
            <div>
                <label class="small fw-bold text-muted">From</label>
                <input type="date" id="statsFrom" class="form-control form-control-sm">
            </div>
            <div>
                <label class="small fw-bold text-muted">To</label>
                <input type="date" id="statsTo" class="form-control form-control-sm">
            </div>
            <div>
                <label class="small fw-bold text-muted">&nbsp;</label>
                <button type="button" id="statsClear" class="btn btn-outline-secondary btn-sm w-100">Clear</button>
            </div>
        </div>
    </div>
    <div class="table-card">
        <div class="p-4 bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-blue d-flex align-items-center gap-2 flex-wrap">
                <i class="bi bi-people-fill"></i>
                Today's Current Visitors:
                <span class="badge rounded-pill bg-light text-blue border" id="todayCountBadge"><?php echo number_format($countToday); ?></span>
                <span class="badge rounded-pill bg-primary-subtle text-primary border" id="todayStudentBadge">Students: 0</span>
                <span class="badge rounded-pill bg-warning-subtle text-warning border" id="todayEmployeeBadge">Faculty/Admin: 0</span>
            </h5>
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted fw-bold">Rows:</label>
                    <select id="rowsPerPage" class="form-select form-select-sm" style="width: auto;">
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                    </select>
                </div>
                <span class="badge bg-success rounded-pill small"><i class="bi bi-record-fill animate-pulse"></i>LIVE UPDATING</span>
            </div>
        </div>

        <div class="p-3 bg-light border-bottom">
            <div class="visitor-filter-grid">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Search by Name or ID...">
                </div>
                <select id="filterDept" class="form-select">
                    <option value="">All Departments</option>
                    <?php
                    $depts = $conn->query("SELECT departmentName FROM departments ORDER BY departmentName ASC");
                    while($d = $depts->fetch_assoc()) {
                        echo "<option value='".htmlspecialchars($d['departmentName'])."'>".$d['departmentName']."</option>";
                    }
                    ?>
                </select>
                <select id="filterRole" class="form-select">
                    <option value="">All Roles</option>
                    <option value="STUDENT">Student</option>
                    <option value="EMPLOYEE">Employee</option>
                </select>
                <select id="filterReason" class="form-select">
                    <option value="">All Reasons</option>
                    <option value="Research">Research / Thesis Work</option>
                    <option value="Study">Quiet Study</option>
                    <option value="Group Study">Group Study / Collaboration</option>
                    <option value="Borrowing">Borrowing/Returning Books</option>
                    <option value="Clearance">Clearance Signing</option>
                    <option value="ID Validation">Library Card / ID Validation</option>
                    <option value="Resting">Resting / Between Classes</option>
                    <option value="Computer Use">Computer / Internet Access</option>
                    <option value="Printing">Printing / Photocopying Services</option>
                    <option value="E-Resources">Accessing Online Databases/E-Books</option>
                    <option value="Event">School Event / Seminar / Orientation</option>
                    <option value="Others">Others</option>
                </select>
                <button type="button" id="clearFilters" class="btn btn-outline-secondary btn-sm w-100">Clear</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="mainVisitorTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Visitor Details</th>
                        <th>Program/Department</th>
                        <th>User Role</th> 
                        <th>Log Timestamp</th> 
                        <th>Reason for Visit</th> 
                        <th>Status</th>
                        <th class="text-center action-col">Actions</th>
                    </tr>
                </thead>
                <tbody id="logTableBody"></tbody>
            </table>
        </div>

        <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
            <div class="small text-muted" id="paginationInfo">Showing 0 to 0 of 0 entries</div>
            <nav><ul class="pagination pagination-sm mb-0" id="paginationControls"></ul></nav>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div id="modalBodyText">Are you sure you want to perform this action?</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <a id="modalConfirmBtn" href="#" class="btn btn-blue px-4 rounded-pill">Proceed</a>
            </div>
        </div>
    </div>
</div>

<form id="statusActionForm" method="POST" action="toggle_status.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    <input type="hidden" name="user_id" id="statusUserId">
    <input type="hidden" name="user_type" id="statusUserType">
    <input type="hidden" name="current_status" id="statusCurrentStatus">
    <input type="hidden" name="reason" id="statusReason">
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentPage = 1;
    let rowsPerPage = 5;

    // MODAL REASON LOGIC
    function confirmStatusChange(userId, userType, currentStatus, action, name) {
        const modalBody = document.getElementById('modalBodyText');
        const confirmBtn = document.getElementById('modalConfirmBtn');
        
        if (action.toLowerCase() === 'block') {
            modalBody.innerHTML = `
                <p>Are you sure you want to <strong>block</strong> ${name}?</p>
                <div class="mt-3">
                    <label class="small fw-bold text-muted mb-1">Reason for blocking:</label>
                    <textarea id="blockReasonInput" class="form-control" rows="3" placeholder="Enter reason here..." required title="Please provide a reason for blocking."></textarea>
                </div>
            `;
            confirmBtn.className = 'btn btn-danger px-4 rounded-pill';
        } else {
            modalBody.innerHTML = `Are you sure you want to <strong>unblock</strong> ${name}?`;
            confirmBtn.className = 'btn btn-success px-4 rounded-pill';
        }

        confirmBtn.onclick = function(e) {
            e.preventDefault();
            
            if (action.toLowerCase() === 'block') {
                const reasonInput = document.getElementById('blockReasonInput');
                const reason = reasonInput.value.trim();
                
                if (!reason) {
                    reasonInput.classList.add('is-invalid');
                    reasonInput.reportValidity();
                    reasonInput.focus();
                    return;
                }
                document.getElementById('statusReason').value = reason;
            } else {
                document.getElementById('statusReason').value = '';
            }

            document.getElementById('statusUserId').value = userId;
            document.getElementById('statusUserType').value = userType;
            document.getElementById('statusCurrentStatus').value = currentStatus;
            document.getElementById('statusActionForm').submit();
        };

        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    }

    $(document).on('click', '.status-action-btn', function() {
        const userId = $(this).data('id');
        const userType = $(this).data('type');
        const currentStatus = $(this).data('status');
        const userName = $(this).data('name');
        const action = (currentStatus || '').toLowerCase() === 'blocked' ? 'unblock' : 'block';
        confirmStatusChange(userId, userType, currentStatus, action, userName);
    });

    function exportToCSV() {
        let csv = [];
        csv.push("Visitor,ID,Program,Role,Timestamp,Reason,Status");
        $("#logTableBody tr").each(function() {
            if ($(this).css('display') === 'none') return;
            let row = [];
            row.push('"' + $(this).find('td:eq(0) .fw-bold').text().trim() + '"');
            row.push('"' + $(this).find('td:eq(0) .text-muted').text().replace('ID: ', '').trim() + '"');
            row.push('"' + $(this).find('td:eq(1)').text().trim() + '"');
            row.push('"' + $(this).find('td:eq(2)').text().trim() + '"');
            row.push('"' + $(this).find('td:eq(3) .fw-bold').text().trim() + " " + $(this).find('td:eq(3) .text-muted').text().trim() + '"');
            row.push('"' + $(this).find('td:eq(4)').text().trim() + '"');
            row.push('"' + $(this).find('td:eq(5)').text().trim() + '"');
            csv.push(row.join(","));
        });
        let csv_string = csv.join("\n");
        let link = document.createElement("a");
        link.style.display = 'none';
        link.setAttribute("target", "_blank");
        link.setAttribute("href", "data:text/csv;charset=utf-8," + encodeURIComponent(csv_string));
        link.setAttribute("download", "Library_Logs_" + new Date().toLocaleDateString() + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function exportToPDF() {
        if (typeof window.jspdf === 'undefined') {
            alert("PDF library not loaded. Please refresh and try again.");
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape', 'pt', 'a4');
        const now = new Date();
        const displayDate = now.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: '2-digit' });
        const fileDate = now.toISOString().slice(0, 10);
        const totalCount = $('#todayCountBadge').text().trim();
        const studentCount = $('#todayStudentBadge').text().trim();
        const employeeCount = $('#todayEmployeeBadge').text().trim();
        const rows = [];
        $("#logTableBody tr").each(function() {
            if ($(this).css('display') === 'none') return;
            rows.push([
                $(this).find('td:eq(0) .fw-bold').text().trim(),
                $(this).find('td:eq(0) .text-muted').text().replace('ID: ', '').trim(),
                $(this).find('td:eq(1)').text().trim(),
                $(this).find('td:eq(2)').text().trim(),
                ($(this).find('td:eq(3) .fw-bold').text().trim() + " " + $(this).find('td:eq(3) .text-muted').text().trim()).trim(),
                $(this).find('td:eq(4)').text().trim(),
                $(this).find('td:eq(5)').text().trim()
            ]);
        });
        doc.text("NEU Library Visitor Logs", 40, 40);
        doc.setFontSize(11);
        doc.setTextColor(90);
        doc.text("Report Date: " + displayDate, 40, 60);
        doc.text(`Today's Visitors: ${totalCount} | ${studentCount} | ${employeeCount}`, 40, 78);
        doc.autoTable({
            startY: 96,
            head: [["Visitor", "ID", "Program", "Role", "Timestamp", "Reason", "Status"]],
            body: rows
        });
        doc.save("NEU_Library_Logs_" + fileDate + ".pdf");
    }

    function applyPagination() {
        const $rows = $("#logTableBody tr");
        const searchTerm = $('#tableSearch').val().toLowerCase();
        const deptTerm = $('#filterDept').val().toLowerCase();
        const roleTerm = $('#filterRole').val().toUpperCase();
        const reasonTerm = $('#filterReason').val().toLowerCase();
        const reasonOptions = [
            'research',
            'study',
            'group study',
            'borrowing',
            'clearance',
            'id validation',
            'resting',
            'computer use',
            'printing',
            'e-resources',
            'event'
        ];
        rowsPerPage = parseInt($('#rowsPerPage').val());

        let visibleRows = [];
        $rows.each(function() {
            const rowText = $(this).find('td:eq(0)').text().toLowerCase();
            const rowDept = $(this).find('td:eq(1)').text().toLowerCase();
            const rowRole = $(this).find('.badge-role').text().toUpperCase();
            const rowReason = $(this).find('td:eq(4)').text().toLowerCase();

            const combinedSearch = (rowText + " " + rowDept + " " + rowReason).toLowerCase();
            const matchesSearch = combinedSearch.indexOf(searchTerm) > -1;
            const matchesDept = deptTerm === "" || rowDept.indexOf(deptTerm) > -1;
            const matchesRole = roleTerm === "" || rowRole === roleTerm;
            let matchesReason = reasonTerm === "" || rowReason.trim() === reasonTerm;
            if (reasonTerm === "others") {
                matchesReason = reasonOptions.every(option => rowReason.indexOf(option) === -1);
            }

            if (matchesSearch && matchesDept && matchesRole && matchesReason) {
                visibleRows.push($(this));
            } else {
                $(this).hide();
            }
        });

        const totalRows = visibleRows.length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        
        $rows.hide(); 
        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        visibleRows.slice(start, end).forEach(row => row.show());
        
        if (totalRows === 0 && searchTerm !== "") {
            // Check if the searched user exists in the system but hasn't logged in today
            checkUserExists(searchTerm);
        } else {
            updatePaginationUI(totalRows, totalPages);
        }
    }

    function checkUserExists(searchTerm) {
        $.ajax({
            url: 'check_user_exists.php',
            type: 'POST',
            dataType: 'json',
            data: { search: searchTerm },
            success: function(response) {
                if (response && response.exists) {
                    // User exists - check if blocked or just hasn't logged in
                    let message, subMessage;
                    if (response.status && response.status.toLowerCase() === 'blocked') {
                        message = "This account is currently blocked";
                        subMessage = `${response.name} cannot access the system due to account restrictions.`;
                    } else {
                        message = "It looks like he/she haven't logged in yet";
                        subMessage = `${response.name} exists in the system but has no activity today.`;
                    }
                    
                    $('#logTableBody').html(`
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-info-circle fs-1 mb-3 d-block"></i>
                                    <h5 class="fw-bold">${message}</h5>
                                    <p class="mb-0">${subMessage}</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    updatePaginationUI(0, 0);
                } else {
                    // User doesn't exist in the system
                    $('#logTableBody').html(`
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-search fs-1 mb-3 d-block"></i>
                                No visitors found matching your search.
                            </td>
                        </tr>
                    `);
                    updatePaginationUI(0, 0);
                }
            },
            error: function() {
                // Fallback to normal no results message
                $('#logTableBody').html(`
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-search fs-1 mb-3 d-block"></i>
                            No visitors found matching your search.
                        </td>
                    </tr>
                `);
                updatePaginationUI(0, 0);
            }
        });
    }

    function updatePaginationUI(totalRows, totalPages) {
        const startIdx = totalRows > 0 ? (currentPage - 1) * rowsPerPage + 1 : 0;
        const endIdx = Math.min(currentPage * rowsPerPage, totalRows);
        $('#paginationInfo').text(`Showing ${startIdx} to ${endIdx} of ${totalRows} entries`);
        let controls = "";
        const maxVisible = 3;
        const addPage = (page, label, isActive = false, isDisabled = false) => {
            const activeClass = isActive ? 'active' : '';
            const disabledClass = isDisabled ? 'disabled' : '';
            if (isDisabled) {
                controls += `<li class="page-item ${disabledClass} ${activeClass}"><span class="page-link">${label}</span></li>`;
                return;
            }
            controls += `<li class="page-item ${disabledClass} ${activeClass}"><a class="page-link" onclick="changePage(${page})">${label}</a></li>`;
        };

        if (totalPages > 1) {
            addPage(currentPage - 1, 'Prev', false, currentPage === 1);

            if (totalPages <= maxVisible) {
                for (let i = 1; i <= totalPages; i++) addPage(i, i, i === currentPage, false);
            } else {
                addPage(1, 1, currentPage === 1, false);
                let start = Math.max(2, currentPage - 2);
                let end = Math.min(totalPages - 1, currentPage + 2);
                let middleCount = end - start + 1;
            if (middleCount < 1) {
                if (start === 2) end = Math.min(totalPages - 1, end + (1 - middleCount));
                else if (end === totalPages - 1) start = Math.max(2, start - (1 - middleCount));
            }
                if (start > 2) addPage(null, '…', false, true);
                for (let i = start; i <= end; i++) addPage(i, i, i === currentPage, false);
                if (end < totalPages - 1) addPage(null, '…', false, true);
                addPage(totalPages, totalPages, currentPage === totalPages, false);
            }

            addPage(currentPage + 1, 'Next', false, currentPage === totalPages);
        }
        $('#paginationControls').html(controls);
    }

    function changePage(page) { if(page >= 1) { currentPage = page; applyPagination(); } }

    function loadLogs() {
        const statsReason = $('#statsReason').val();
        const statsDept = $('#statsDept').val();
        const statsRole = $('#statsRole').val();
        const statsFrom = $('#statsFrom').val();
        const statsTo = $('#statsTo').val();

        $.ajax({
            url: 'fetch_logs.php',
            type: 'GET',
            dataType: 'json',
            data: {
                stats_reason: statsReason,
                stats_dept: statsDept,
                stats_role: statsRole,
                stats_from: statsFrom,
                stats_to: statsTo
            },
            success: function(response) {
                $('#logTableBody').html(response.html);
                applyPagination();
                $('#valToday').text(response.todayCount.toLocaleString());
                $('#todayCountBadge').text(response.todayCount.toLocaleString());
                $('#todayStudentBadge').text(`Students: ${response.todayStudentCount.toLocaleString()}`);
                $('#todayEmployeeBadge').text(`Faculty/Admin: ${response.todayEmployeeCount.toLocaleString()}`);
                $('#valWeek').text(response.weekCount.toLocaleString());
                $('#valMonth').text(response.monthCount.toLocaleString());
                $('#valOverall').text(response.overallCount.toLocaleString());
                $('#valTotalUsers').text(response.totalUsersCount.toLocaleString());
                $('#valActive').text(response.totalActiveCount.toLocaleString());
                $('#valBlocked').text(response.globalBlockedCount.toLocaleString());
                $('#valRange').text(response.rangeCount.toLocaleString());
                $('#rangeLabel').text(response.rangeLabel || 'Select range');

                const trendDay = response.yesterdayCount > 0 
                    ? Math.round(((response.todayCount - response.yesterdayCount) / response.yesterdayCount) * 100) 
                    : (response.todayCount > 0 ? 100 : 0);
                
                $('#trendText').text(Math.abs(trendDay) + '%');
                $('#trendToday').attr('class', trendDay >= 0 ? 'trend-up' : 'trend-down');
                $('#trendIcon').attr('class', trendDay >= 0 ? 'bi bi-graph-up-arrow' : 'bi bi-graph-down-arrow');

                const blocked = parseInt(response.globalBlockedCount);
                if (blocked > 10) {
                    $('#securityStatusText').text("ALERT").css("color", "#dc3545");
                    $('#securityIconBox').css({"background": "#fef2f2", "color": "#dc3545"});
                    $('#securityIcon').attr("class", "bi bi-shield-exclamation");
                    $('#securitySubtext').css({"background": "#fef2f2", "color": "#dc3545"}).text("High block volume");
                } else if (blocked > 0) {
                    $('#securityStatusText').text("STRICT").css("color", "#a16207");
                    $('#securityIconBox').css({"background": "#fef9c3", "color": "#a16207"});
                    $('#securityIcon').attr("class", "bi bi-shield-lock");
                    $('#securitySubtext').css({"background": "#fef9c3", "color": "#a16207"}).text("Active restrictions");
                } else {
                    $('#securityStatusText').text("OK").css("color", "#be185d");
                    $('#securityIconBox').css({"background": "#fce7f3", "color": "#be185d"});
                    $('#securityIcon').attr("class", "bi bi-shield-check");
                    $('#securitySubtext').css({"background": "#fce7f3", "color": "#be185d"}).text("all systems safe");
                }
            }
        });
    }

    $(document).ready(function() {
        // --- WELCOME ALERT AUTO-CLOSE ---
        if ($('#welcomeAlert').length) {
            setTimeout(function() {
                var welcomeAlert = document.getElementById('welcomeAlert');
                if (welcomeAlert) {
                    var bsAlert = bootstrap.Alert.getOrCreateInstance(welcomeAlert);
                    bsAlert.close();
                }
            }, 5000);
        }

        loadLogs();
        setInterval(loadLogs, 5000);
        $('#tableSearch').on('keyup', function() { currentPage = 1; applyPagination(); });
        $('#filterDept, #filterRole, #filterReason, #rowsPerPage').on('change', function() { currentPage = 1; applyPagination(); });
        $('#clearFilters').on('click', function() {
            $('#tableSearch').val(''); $('#filterDept').val(''); $('#filterRole').val(''); $('#filterReason').val('');
            currentPage = 1; applyPagination();
        });
        if ($('#statusAlert').length) setTimeout(() => { bootstrap.Alert.getOrCreateInstance($('#statusAlert')[0]).close(); }, 5000);

        $('#statsReason, #statsDept, #statsRole, #statsFrom, #statsTo').on('change', function() {
            loadLogs();
        });
        $('#statsClear').on('click', function() {
            $('#statsReason').val('');
            $('#statsDept').val('');
            $('#statsRole').val('');
            $('#statsFrom').val('');
            $('#statsTo').val('');
            loadLogs();
        });
    });
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
</body>
</html>
