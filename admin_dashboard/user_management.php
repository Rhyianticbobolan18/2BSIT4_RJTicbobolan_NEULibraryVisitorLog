<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$adminID = $_SESSION['emplID'];
$adminQuery = $conn->prepare("SELECT profile_image, firstName, lastName FROM employees WHERE emplID = ?");
$adminQuery->bind_param("s", $adminID);
$adminQuery->execute();
$adminData = $adminQuery->get_result()->fetch_assoc();

$deptQuery = $conn->query("SELECT * FROM departments ORDER BY departmentName ASC");
$departments = $deptQuery->fetch_all(MYSQLI_ASSOC);

$studentCountRes = $conn->query("SELECT COUNT(*) as count FROM students");
$studentCount = $studentCountRes->fetch_assoc()['count'] ?? 0;
$employeeCountRes = $conn->query("SELECT COUNT(*) as count FROM employees");
$employeeCount = $employeeCountRes->fetch_assoc()['count'] ?? 0;
$csrfToken = csrf_token();

// Helper function for Table Avatars
function renderProfileImage($firstName, $lastName, $image, $type) {
    $folder = ($type === 'Student') ? 'student' : 'admin';
    $filename = $image;
    $fallback = "https://ui-avatars.com/api/?name=" . urlencode(trim($firstName . ' ' . $lastName)) . "&background=0038a8&color=fff&bold=true";
    if (!empty($filename) && preg_match('/^https?:\\/\\//i', $filename)) {
        return '<img src="'.htmlspecialchars($filename).'" class="profile-img-sm me-2" alt="Profile" onerror="this.onerror=null;this.src=\''.$fallback.'\';">';
    }

    $urlPath = "../profilepictures/$folder/" . $filename;
    $filePath = __DIR__ . "/../profilepictures/$folder/" . $filename;

    if (!empty($filename) && $filename !== 'default.png' && is_file($filePath)) {
        $cacheBuster = '?v=' . filemtime($filePath);
        return '<img src="'.htmlspecialchars($urlPath . $cacheBuster).'" class="profile-img-sm me-2" alt="Profile" onerror="this.onerror=null;this.src=\''.$fallback.'\';">';
    }

    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
    return '<div class="initials-avatar me-2">'.$initials.'</div>';
}

// --- AJAX DATA HANDLER ---
if (isset($_GET['ajax'])) {
    $deptFilter = $_GET['dept'] ?? '';
    $search = $_GET['search'] ?? '';
    $tableType = $_GET['type'] ?? 'students';
    $tableType = ($tableType === 'employees') ? 'employees' : 'students';

    $idCol = ($tableType === 'students') ? 'studentID' : 'emplID';
    $sortBy = $_GET['sort_by'] ?? 'lastName';
    $sortDir = $_GET['sort_dir'] ?? 'ASC';

    // Sanitize sort input
    $sortBy = ($sortBy === 'id') ? 'id' : 'lastName';
    $sortDir = (strtoupper($sortDir) === 'DESC') ? 'DESC' : 'ASC';

    $sortColumn = ($sortBy === 'id') ? "u.$idCol" : "u.lastName";

    // Fetch latest login time for today (avoid duplicates)
    $sql = "SELECT u.*, d.departmentName, h.last_login, COALESCE(vc.visit_count, 0) AS visit_count
            FROM $tableType u
            LEFT JOIN departments d ON u.departmentID = d.departmentID
            LEFT JOIN (
                SELECT user_identifier, MAX(time) AS last_login
                FROM history_logs
                WHERE date = CURDATE()
                GROUP BY user_identifier
            ) h ON h.user_identifier = u.$idCol
            LEFT JOIN (
                SELECT user_identifier, COUNT(*) AS visit_count
                FROM history_logs
                GROUP BY user_identifier
            ) vc ON vc.user_identifier = u.$idCol
            WHERE 1=1";
    
    $where = [];
    $params = [];
    $types = "";
    if ($deptFilter) {
        $where[] = "u.departmentID = ?";
        $params[] = $deptFilter;
        $types .= "s";
    }
    if ($search) {
        $like = "%$search%";
        $where[] = "(u.firstName LIKE ? OR u.lastName LIKE ? OR $idCol LIKE ?)";
        array_push($params, $like, $like, $like);
        $types .= "sss";
    }
    if (!empty($where)) {
        $sql .= " AND " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY $sortColumn $sortDir";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if (isset($_GET['export']) && $_GET['export'] == '1') {
        $rows = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $userTypeLabel = ($tableType === 'students' ? 'Student' : 'Employee');
                $loginStatus = 'Not logged in';
                if (!empty($row['last_login'])) {
                    $loginStatus = 'Logged in today';
                    $loginTime = date('h:i A', strtotime($row['last_login']));
                    $loginStatus .= ' ' . $loginTime;
                }
                $rows[] = [
                    'name' => trim($row['firstName'] . ' ' . $row['lastName']),
                    'id' => $row[$idCol],
                    'department' => $row['departmentName'] ?? 'N/A',
                    'role' => ($tableType === 'students') ? 'Student' : ($row['role'] ?? 'Employee'),
                    'status' => $row['status'] ?? '',
                    'activity' => $loginStatus,
                    'visits' => $row['visit_count'] ?? 0
                ];
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['rows' => $rows]);
        exit();
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $userTypeLabel = ($tableType === 'students' ? 'Student' : 'Employee');
            
            // Determine login status
            $loginStatus = 'Not logged in';
            $loginBadgeClass = 'bg-secondary';
            $loginTime = '';
            
            if (!empty($row['last_login'])) {
                $loginStatus = 'Logged in today';
                $loginBadgeClass = 'bg-success';
                $loginTime = date('h:i A', strtotime($row['last_login']));
            }
            
            echo "<tr>";
            echo "<td class='ps-4'><div class='d-flex align-items-center'>" . renderProfileImage($row['firstName'], $row['lastName'], $row['profile_image'], $userTypeLabel) . "<span class='fw-bold'>{$row['firstName']} {$row['lastName']}</span></div></td>";
            echo "<td><span class='text-blue fw-semibold'>{$row[$idCol]}</span></td>";
            
            // Shared Columns for both tables
            echo "<td><span class='text-muted small'>".($row['departmentName'] ?? 'N/A')."</span></td>";
            
            if ($tableType === 'students') {
                echo "<td><span class='badge bg-light text-dark border'>Student</span></td>";
            } else {
                echo "<td><span class='badge bg-light text-dark border'>{$row['role']}</span></td>";
            }
            
            echo "<td><span class='status-badge bg-" . strtolower($row['status']) . "'>{$row['status']}</span></td>";

            if ($tableType === 'employees') {
                $isApproved = isset($row['is_admin_approved']) && (int)$row['is_admin_approved'] === 1;
                $approvalBadgeClass = $isApproved ? 'bg-success' : 'bg-secondary';
                $approvalLabel = $isApproved ? 'Approved' : 'Pending';
                $approvalBtnClass = $isApproved ? 'btn-outline-warning' : 'btn-outline-success';
                $approvalBtnLabel = $isApproved ? 'Revoke' : 'Approve';
                $approvalBtnTitle = $isApproved ? 'Revoke admin approval' : 'Approve admin access';
                $approvalBtnIcon = $isApproved ? 'bi-shield-x' : 'bi-shield-check';
                echo "<td class='text-center'><span class='badge {$approvalBadgeClass} mb-1'>{$approvalLabel}</span><br><button class='btn btn-sm {$approvalBtnClass} toggle-approval-btn' data-id='{$row[$idCol]}' data-approved='".($isApproved ? 1 : 0)."' data-name='".htmlspecialchars($row['firstName'].' '.$row['lastName'])."' data-bs-toggle='tooltip' data-bs-placement='top' title='{$approvalBtnTitle}'><i class='bi {$approvalBtnIcon} me-1'></i>{$approvalBtnLabel}</button></td>";
            }
            echo "<td><span class='badge {$loginBadgeClass}'>{$loginStatus}</span>" . (!empty($loginTime) ? "<br><small class='text-muted'>{$loginTime}</small>" : "") . "</td>";
            echo "<td><span class='fw-semibold text-dark'>{$row['visit_count']}</span></td>";
            
            $blockButtonClass = ($row['status'] === 'Blocked') ? 'btn-outline-success' : 'btn-outline-danger';
            $blockButtonIcon = ($row['status'] === 'Blocked') ? 'bi-check-circle' : 'bi-lock';
            $blockButtonTitle = ($row['status'] === 'Blocked') ? 'Unblock User' : 'Block User';
            
            echo "<td class='text-center'><div class='d-flex gap-1 justify-content-center'><button class='btn btn-sm btn-outline-primary px-1 view-user' data-id='{$row[$idCol]}' data-type='{$userTypeLabel}' data-bs-toggle='tooltip' data-bs-placement='top' title='View Details'><i class='bi bi-eye'></i></button><button class='btn btn-sm {$blockButtonClass} px-1 block-user-btn' data-id='{$row[$idCol]}' data-type='{$userTypeLabel}' data-status='{$row['status']}' data-bs-toggle='tooltip' data-bs-placement='top' title='{$blockButtonTitle}'><i class='bi {$blockButtonIcon}'></i></button></div></td>";
            echo "</tr>";
        }
    } else {
        echo "NO_RESULTS_FOUND";
    }
    exit();
}

$photoFilename = $adminData['profile_image'] ?? null;
$isRemoteAdminPhoto = (!empty($photoFilename) && preg_match('/^https?:\\/\\//i', $photoFilename));
$photoFilePath = __DIR__ . "/../profilepictures/admin/" . $photoFilename;
$cacheBuster = (!$isRemoteAdminPhoto && !empty($photoFilename) && is_file($photoFilePath)) ? ('?v=' . filemtime($photoFilePath)) : '';
$photoUrl = $isRemoteAdminPhoto ? $photoFilename : "../profilepictures/admin/" . $photoFilename . $cacheBuster;
$hasPhoto = ($isRemoteAdminPhoto || (!empty($photoFilename) && is_file($photoFilePath)));
$adminInitials = strtoupper(substr($adminData['firstName'] ?? '', 0, 1) . substr($adminData['lastName'] ?? '', 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | NEU Library Admin</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        .initials-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--neu-blue); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
        .profile-img-sm { width: 35px; height: 35px; object-fit: cover; border-radius: 50%; border: 1px solid #ddd; }
        .user-card, .table-card { background: white; border-radius: 16px; box-shadow: 0 16px 40px rgba(0,0,0,0.06); border: none; overflow: hidden; }
        .page-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; }
        .page-header h2 { margin-bottom: 0.25rem; }
        .page-header p { margin-bottom: 0; }
        .filter-bar { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; }
        .filter-bar .form-control, .filter-bar .form-select { min-width: 170px; }
        .filter-bar .input-group { flex: 1 1 380px; max-width: none; min-width: 260px; }
        .table-card thead { background: rgba(0, 56, 168, 0.08); }
        .table-card tr:hover { background: rgba(0, 56, 168, 0.04); }
        .table-card th, .table-card td { vertical-align: middle; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .bg-active { background: #dcfce7; color: #15803d; }
        .bg-blocked { background: #fee2e2; color: #b91c1c; }
        .export-toolbar { display: flex; justify-content: space-between; gap: 0.75rem; padding: 0.75rem 1rem; background: #fff; border-bottom: 1px solid #eef2f7; align-items: center; }
        .user-counts { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
        .user-counts .badge { font-size: 0.75rem; font-weight: 700; }
        #loadingOverlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.75); display: none; align-items: center; justify-content: center; z-index: 10; border-radius: 16px; }
        #confirmModal { z-index: 1070; }
        #deleteConfirmModal { z-index: 1080; }
          .view-user, .block-user-btn { min-width: 32px !important; width: 32px !important; height: 32px !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; }

          /* SweetAlert2 modal sizing + centering tweaks */
          .swal2-popup {
              max-width: 440px !important;
              width: 100% !important;
              padding: 1.25rem !important;
              box-shadow: 0 20px 52px rgba(0,0,0,0.18) !important;
          }
          @media (max-width: 992px) {
              .navbar { padding: 0.5rem 1rem; }
              .filter-bar { padding: 0.75rem; }
              .filter-bar .input-group { flex: 1 1 100%; }
          }
          @media (max-width: 768px) {
              .filter-bar .d-flex.gap-2.align-items-center { width: 100%; justify-content: space-between; }
              .filter-bar .d-flex.align-items-center.gap-2 { width: 100%; justify-content: flex-start; }
              .export-toolbar { flex-direction: column; align-items: stretch; }
              .export-toolbar .d-flex.gap-2 { width: 100%; }
              .user-counts { width: 100%; }
          }
          @media (max-width: 576px) {
              .filter-bar .form-control, .filter-bar .form-select { min-width: 100%; }
              .export-toolbar .btn { width: 100%; }
          }
      </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top navbar-light">
    <div class="container-fluid">
        <a href="index.php" class="navbar-brand fw-bold text-blue d-flex align-items-center">
            <img src="../assets/neu.png" alt="Logo" height="35" class="me-2"> NEU LIBRARY ADMIN
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto ms-lg-4">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="visitor_history.php"><i class="bi bi-clock-history"></i> Visitor History Logs</a></li>
                <li class="nav-item"><a class="nav-link" href="blocklist.php"><i class="bi bi-shield-slash"></i> Blocklist</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="stats.php"><i class="bi bi-graph-up"></i> Statistics</a></li>
                <li class="nav-item"><a class="nav-link active" href="user_management.php"><i class="bi bi-people"></i> Users</a></li>
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

<div class="container-fluid px-4 px-md-5 py-4">
        <div class="page-header">
            <div>
                <h2 class="fw-bold text-blue mb-1">Visitor Management</h2>
                <p class="text-muted mb-0">Real-time monitoring and administration of students and faculty access.</p>
            </div>
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-lg me-2"></i> Add User
            </button>
        </div>

        <?php if (isset($_GET['msg'])): ?>
        <?php 
            $isSuccess = in_array($_GET['msg'], ['StatusUpdated', 'UserDeleted']);
            $alertClass = $isSuccess ? 'alert-success' : 'alert-danger';
            $icon = $isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        ?>
        <div id="statusAlert" class="alert <?= $alertClass ?> alert-dismissible fade show shadow-sm border-0 mb-4" role="alert" style="border-left: 5px solid <?= $isSuccess ? '#10b981' : '#dc3545' ?> !important;">
            <i class="bi <?= $icon ?> me-2"></i>
            <strong><?= $isSuccess ? 'Success!' : 'Error!' ?></strong>
            <?php 
                if ($_GET['msg'] == 'UserDeleted') echo 'User account and associated data have been permanently removed.';
                elseif ($_GET['msg'] == 'StatusUpdated') echo 'User status has been updated successfully.';
                else echo 'An error occurred during the operation.';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="table-card position-relative">
        <div id="loadingOverlay"><div class="spinner-border text-primary"></div></div>

        <form id="filterForm" class="filter-bar">
            <div class="d-flex flex-wrap align-items-center gap-2 w-100">
                <div class="flex-grow-1">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search visitors by name or ID">
                    </div>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <button type="button" id="clearBtn" class="btn btn-outline-secondary btn-sm">Clear</button>
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted fw-bold mb-0">Rows:</label>
                    <select id="rowsPerPage" class="form-select form-select-sm" style="width: 80px;">
                        <option value="8" selected>8</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="30">30</option>
                    </select>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 w-100 mt-2">
                <select id="deptFilter" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $d): ?>
                        <option value="<?= $d['departmentID'] ?>"><?= $d['departmentName'] ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="sortBy" class="form-select form-select-sm">
                    <option value="lastName">Sort by Last Name</option>
                    <option value="id">Sort by ID</option>
                </select>

                <select id="sortDir" class="form-select form-select-sm">
                    <option value="ASC">Ascending</option>
                    <option value="DESC">Descending</option>
                </select>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center px-3 pt-2 bg-light border-bottom flex-wrap gap-2">
            <ul class="nav nav-tabs border-0" id="userTabs">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#students-pane" id="studentTabBtn">
                    <i class="bi bi-person-fill me-1"></i> Students
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#employees-pane" id="employeeTabBtn">
                    <i class="bi bi-person-badge-fill me-1"></i> Faculty / Admin
                </button>
            </li>
            </ul>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="students-pane">
                <div class="export-toolbar">
                    <div class="user-counts">
                        <span class="badge bg-primary-subtle text-primary border" id="studentCountBadge">Students: <?php echo number_format($studentCount); ?></span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="exportUsersPDF('students')">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Save as PDF
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="exportUsersCSV('students')">
                        <i class="bi bi-filetype-csv me-1"></i> Save as Excel/CSV
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-uppercase">
                            <tr><th class="ps-4">Full Name</th><th>Student ID</th><th>Department</th><th>Role</th><th>Status</th><th>Today's Activity</th><th>Visits</th><th class="text-center">Action</th></tr>
                        </thead>
                        <tbody id="studentTableBody"></tbody>
                    </table>
                </div>
                <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
                    <div class="small text-muted" id="studentPagInfo"></div>
                    <nav><ul class="pagination pagination-sm mb-0" id="studentPagination"></ul></nav>
                </div>
            </div>

            <div class="tab-pane fade" id="employees-pane">
                <div class="export-toolbar">
                    <div class="user-counts">
                        <span class="badge bg-warning-subtle text-warning border" id="employeeCountBadge">Faculty/Admin: <?php echo number_format($employeeCount); ?></span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="exportUsersPDF('employees')">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Save as PDF
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="exportUsersCSV('employees')">
                        <i class="bi bi-filetype-csv me-1"></i> Save as Excel/CSV
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-uppercase">
                            <tr><th class="ps-4">Full Name</th><th>Employee ID</th><th>Department</th><th>Role</th><th>Status</th><th class="text-center">Admin Approval</th><th>Today's Activity</th><th>Visits</th><th class="text-center">Action</th></tr>
                        </thead>
                        <tbody id="employeeTableBody"></tbody>
                    </table>
                </div>
                <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
                    <div class="small text-muted" id="employeePagInfo"></div>
                    <nav><ul class="pagination pagination-sm mb-0" id="employeePagination"></ul></nav>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-blue"><i class="bi bi-person-plus-fill me-2"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addUserForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">User Type</label>
                        <select class="form-select" name="user_type" id="addTypeSelect" required>
                            <option value="Student" selected>Student</option>
                            <option value="Employee">Faculty / Admin</option>
                        </select>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">First Name</label>
                            <input type="text" name="firstName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Last Name</label>
                            <input type="text" name="lastName" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Institutional Email</label>
                        <input type="email" name="institutionalEmail" class="form-control" placeholder="example@neu.edu.ph" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Department</label>
                        <select name="departmentID" class="form-select" required>
                            <option value="">Select Department...</option>
                            <?php foreach($departments as $d): ?>
                                <option value="<?= $d['departmentID'] ?>"><?= $d['departmentName'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="passwordFieldContainer" style="display: none;">
                        <label class="form-label fw-bold small text-muted">Password (Plain Text)</label>
                        <div class="input-group">
                            <input type="password" name="password" id="addPassword" class="form-control" placeholder="Set user password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                                <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Profile Image (Optional)</label>
                        <input type="file" name="profile_image" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-blue px-4 rounded-pill fw-bold">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4" id="userDetailsContent"></div>
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

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-trash3 text-danger mb-3 d-block" style="font-size: 3rem;"></i>
                <p class="fs-5 mb-1">Permanently delete <strong><span id="delUserNameText"></span></strong>?</p>
                <p class="text-muted small px-3">This action is irreversible. All activity logs and profile information for this user will be removed from the database.</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-light px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                <a id="delFinalBtn" href="#" class="btn btn-danger px-4 rounded-pill fw-bold shadow-sm">Confirm Delete</a>
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
<form id="deleteUserForm" method="POST" action="delete_user.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    <input type="hidden" name="id" id="deleteUserId">
    <input type="hidden" name="type" id="deleteUserType">
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<script>
let currentType = 'students';
let currentPage = 1;

function updateFilteredCount(totalRows) {
    if (currentType === 'employees') {
        $('#employeeCountBadge').text(`Faculty/Admin: ${totalRows}`);
    } else {
        $('#studentCountBadge').text(`Students: ${totalRows}`);
    }
}

function fetchUsers(isAutoSwitch = false) {
    $('#loadingOverlay').css('display', 'flex');
    const search = $('#searchInput').val();
    const dept = $('#deptFilter').val();
    const sortBy = $('#sortBy').val();
    const sortDir = $('#sortDir').val();

    $.ajax({
        url: 'user_management.php',
        method: 'GET',
        data: { ajax: 1, type: currentType, search: search, dept: dept, sort_by: sortBy, sort_dir: sortDir },
        success: function(res) {
            const targetBody = currentType === 'students' ? '#studentTableBody' : '#employeeTableBody';
            
            if (res.trim() === "NO_RESULTS_FOUND" && search !== "") {
                const otherType = currentType === 'students' ? 'employees' : 'students';
                $.ajax({
                    url: 'user_management.php',
                    method: 'GET',
                    data: { ajax: 1, type: otherType, search: search, dept: dept, sort_by: sortBy, sort_dir: sortDir },
                    success: function(otherRes) {
                        if (otherRes.trim() !== "NO_RESULTS_FOUND") {
                            currentType = otherType;
                            const tabId = (currentType === 'students') ? '#studentTabBtn' : '#employeeTabBtn';
                            bootstrap.Tab.getOrCreateInstance(document.querySelector(tabId)).show();
                            fetchUsers(true);
                        } else {
                            const colspan = (currentType === 'employees') ? 9 : 8;
                            $(targetBody).html(`<tr><td colspan='${colspan}' class='text-center py-5 text-muted'>No users found match.</td></tr>`);
                            currentPage = 1;
                            applyPagination();
                            updateFilteredCount(0);
                        }
                    }
                });
            } else {
                if (res.trim() === "NO_RESULTS_FOUND") {
                    const colspan = (currentType === 'employees') ? 9 : 8;
                    $(targetBody).html(`<tr><td colspan='${colspan}' class='text-center py-5 text-muted'>No users found match.</td></tr>`);
                    updateFilteredCount(0);
                } else {
                    $(targetBody).html(res);
                    updateFilteredCount($(targetBody).find('tr').length);
                }
                
                currentPage = 1;
                applyPagination();
                
                // Re-initialize tooltips after loading new data
                setTimeout(() => initializeTooltips(), 100);
            }
        },
        error: function() {
            const targetBody = currentType === 'students' ? '#studentTableBody' : '#employeeTableBody';
            const colspan = (currentType === 'employees') ? 9 : 8;
            $(targetBody).html(`<tr><td colspan='${colspan}' class='text-center py-5 text-danger'>Failed to load users. Please try again.</td></tr>`);
            updateFilteredCount(0);
        },
        complete: function() {
            $('#loadingOverlay').hide();
        }
    });
}

function applyPagination() {
    const tableBody = currentType === 'students' ? '#studentTableBody' : '#employeeTableBody';
    const rows = $(tableBody).find('tr');
    const totalRows = rows.length;
    const rowsPerPage = parseInt($('#rowsPerPage').val());
    const totalPages = Math.max(Math.ceil(totalRows / rowsPerPage), 1);

    if (currentPage > totalPages) currentPage = totalPages;
    rows.hide().slice((currentPage - 1) * rowsPerPage, currentPage * rowsPerPage).show();

    const infoId = currentType === 'students' ? '#studentPagInfo' : '#employeePagInfo';
    const pagId = currentType === 'students' ? '#studentPagination' : '#employeePagination';
    
    $(infoId).text(`Showing ${totalRows > 0 ? (currentPage - 1) * rowsPerPage + 1 : 0} to ${Math.min(currentPage * rowsPerPage, totalRows)} of ${totalRows} entries`);

    let controls = "";
    const maxVisible = 3;
    const addPage = (page, label, isActive = false, isDisabled = false) => {
        const activeClass = isActive ? 'active' : '';
        const disabledClass = isDisabled ? 'disabled' : '';
        if (isDisabled) {
            controls += `<li class="page-item ${disabledClass} ${activeClass}"><span class="page-link">${label}</span></li>`;
            return;
        }
        controls += `<li class="page-item ${disabledClass} ${activeClass}"><a class="page-link" href="javascript:void(0)" onclick="changePage(${page})">${label}</a></li>`;
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
    $(pagId).html(controls);
}

function changePage(page) {
    currentPage = page;
    applyPagination();
}

function fetchExportData(type, onSuccess) {
    const dept = $('#deptFilter').val();
    const search = $('#searchInput').val();
    const sortBy = $('#sortBy').val();
    const sortDir = $('#sortDir').val();
    $.ajax({
        url: 'user_management.php',
        type: 'GET',
        dataType: 'json',
        data: {
            ajax: 1,
            export: 1,
            type: type,
            dept: dept,
            search: search,
            sort_by: sortBy,
            sort_dir: sortDir
        },
        success: function(res) {
            onSuccess(res && res.rows ? res.rows : []);
        },
        error: function() {
            alert('Failed to export data. Please try again.');
        }
    });
}

function exportUsersCSV(type) {
    fetchExportData(type, function(rows) {
        if (!rows.length) {
            alert('No rows to export.');
            return;
        }
        const headers = type === 'employees'
            ? ['Full Name','Employee ID','Department','Role','Status',"Today's Activity",'Visits']
            : ['Full Name','Student ID','Department','Role','Status',"Today's Activity",'Visits'];

        const csv = [headers.map(h => `"${h}"`).join(',')];
        rows.forEach(r => {
            const cols = [
                r.name,
                r.id,
                r.department,
                r.role,
                r.status,
                r.activity,
                r.visits
            ].map(v => `"${String(v).replace(/\s+/g, ' ').trim()}"`);
            csv.push(cols.join(','));
        });
        const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `NEU_${type}_users_${new Date().toISOString().slice(0,10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
}

function exportUsersPDF(type) {
    if (typeof window.jspdf === 'undefined') {
        alert('PDF library not loaded. Please refresh and try again.');
        return;
    }
    fetchExportData(type, function(rows) {
        if (!rows.length) {
            alert('No rows to export.');
            return;
        }
        const headers = type === 'employees'
            ? ['Full Name','Employee ID','Department','Role','Status',"Today's Activity",'Visits']
            : ['Full Name','Student ID','Department','Role','Status',"Today's Activity",'Visits'];

        const data = rows.map(r => [
            r.name,
            r.id,
            r.department,
            r.role,
            r.status,
            r.activity,
            r.visits
        ]);

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape', 'pt', 'a4');
        const title = type === 'employees' ? 'NEU Faculty/Admin Users' : 'NEU Student Users';
        const dateStr = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: '2-digit' });
        const countLabel = type === 'employees' ? 'Faculty/Admin Count: ' : 'Student Count: ';
        const totalCount = rows.length;
        doc.text(title, 40, 40);
        doc.setFontSize(11);
        doc.setTextColor(90);
        doc.text('Report Date: ' + dateStr, 40, 60);
        doc.text(countLabel + totalCount, 40, 78);
        doc.autoTable({
            startY: 96,
            head: [headers],
            body: data
        });
        doc.save(`${title.replace(/\s+/g,'_')}_${new Date().toISOString().slice(0,10)}.pdf`);
    });
}

function showDeleteModal(id, type, name) {
    $('#userDetailsModal').modal('hide');
    $('#delUserNameText').text(name);
    $('#deleteUserId').val(id);
    $('#deleteUserType').val(type.toLowerCase());
    const delModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    delModal.show();
}

function confirmStatusChange(userId, userType, currentStatus, action, name) {
    $('#userDetailsModal').modal('hide');
    const modalBody = document.getElementById('modalBodyText');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    
    if (action.toLowerCase() === 'block') {
        modalBody.innerHTML = `
            <p>Are you sure you want to <strong>block</strong> ${name}?</p>
            <div class="mt-3">
                <label class="small fw-bold text-muted mb-1">Reason for blocking:</label>
                <textarea id="blockReasonInput" class="form-control" rows="3" placeholder="e.g. Violation of policy" required></textarea>
            </div>`;
        confirmBtn.className = 'btn btn-danger px-4 rounded-pill';
        confirmBtn.innerText = 'Block User';
    } else {
        modalBody.innerHTML = `Are you sure you want to <strong>restore access</strong> for <b>${name}</b>?`;
        confirmBtn.className = 'btn btn-success px-4 rounded-pill';
        confirmBtn.innerText = 'Unblock User';
    }

    confirmBtn.onclick = function(e) {
        e.preventDefault();
        if (action.toLowerCase() === 'block') {
            const reasonInput = document.getElementById('blockReasonInput');
            const reason = reasonInput.value.trim();
            if (!reason) {
                reasonInput.classList.add('is-invalid');
                reasonInput.focus();
                return;
            }
            $('#statusReason').val(reason);
        } else {
            $('#statusReason').val('');
        }
        $('#statusUserId').val(userId);
        $('#statusUserType').val(userType);
        $('#statusCurrentStatus').val(currentStatus);
        document.getElementById('statusActionForm').submit();
    };

    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    confirmModal.show();
}

$(document).ready(function() {
    fetchUsers();
    $('#filterForm').on('submit', function(e) { e.preventDefault(); fetchUsers(); });
    $('#clearBtn').on('click', function() { $('#filterForm')[0].reset(); fetchUsers(); });
    $('#rowsPerPage').on('change', function() { currentPage = 1; applyPagination(); });

    $('#sortBy, #sortDir').on('change', function() { currentPage = 1; fetchUsers(); });

    $('#studentTabBtn').on('click', function() { if(currentType !== 'students') { currentType = 'students'; fetchUsers(); } });
    $('#employeeTabBtn').on('click', function() { if(currentType !== 'employees') { currentType = 'employees'; fetchUsers(); } });

    $(document).on('click', '.view-user', function() {
        const id = $(this).data('id');
        const type = $(this).data('type');
        $('#userDetailsModal').modal('show');
        $('#userDetailsContent').html('<div class="p-5 text-center"><div class="spinner-border text-primary"></div></div>');
        $.ajax({
            url: 'fetch_user_details.php',
            method: 'POST',
            data: { id: id, type: type },
            success: function(response) { $('#userDetailsContent').html(response); }
        });
    });

    $(document).on('click', '.block-user-btn', function() {
        const id = $(this).data('id');
        const type = $(this).data('type');
        const status = $(this).data('status');
        const name = $(this).closest('tr').find('td:nth-child(1) span.fw-bold').text().trim();
        
        if (status === 'Blocked') {
            confirmStatusChange(id, type.toLowerCase(), 'blocked', 'unblock', name);
        } else {
            confirmStatusChange(id, type.toLowerCase(), 'active', 'block', name);
        }
    });

    $('#delFinalBtn').on('click', function(e) {
        e.preventDefault();
        document.getElementById('deleteUserForm').submit();
    });

    $(document).on('click', '.toggle-approval-btn', function() {
        const id = $(this).data('id');
        const approved = parseInt($(this).data('approved'), 10) === 1;
        const name = $(this).data('name') || 'this user';
        const actionLabel = approved ? 'Revoke' : 'Approve';
        const nextValue = approved ? 0 : 1;

        Swal.fire({
            icon: approved ? 'warning' : 'question',
            title: `${actionLabel} Admin Access?`,
            text: approved
                ? `This will block ${name} from accessing the admin dashboard.`
                : `This will allow ${name} to access the admin dashboard.`,
            showCancelButton: true,
            confirmButtonText: actionLabel,
            confirmButtonColor: approved ? '#f59e0b' : '#16a34a',
            cancelButtonColor: '#6b7280'
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.ajax({
                url: 'toggle_admin_approval.php',
                method: 'POST',
                data: { empl_id: id, approve: nextValue, csrf_token: '<?php echo htmlspecialchars($csrfToken); ?>' },
                success: function(res) {
                    if (res.trim() === 'success') {
                        Swal.fire({ icon: 'success', title: 'Updated!', showConfirmButton: false, timer: 1500, position: 'top-end', toast: true });
                        fetchUsers();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Update failed', text: res || 'Please try again.' });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'System error', text: 'Please try again.' });
                }
            });
        });
    });

    if ($('#statusAlert').length) setTimeout(() => { bootstrap.Alert.getOrCreateInstance($('#statusAlert')[0]).close(); }, 5000);

    // Initialize tooltips
    function initializeTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    initializeTooltips();
    
    // Re-initialize tooltips after AJAX load
    $(document).on('fetchUsers', function() {
        // Destroy existing tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            const tooltip = bootstrap.Tooltip.getInstance(el);
            if (tooltip) tooltip.dispose();
        });
        // Re-initialize
        initializeTooltips();
    });

    // ==========================================
    // ADD USER MODAL JAVASCRIPT LOGIC
    // ==========================================
    
    // 1. Toggle Password field visibility based on user type
    $('#addTypeSelect').on('change', function() {
        if ($(this).val() === 'Employee') {
            $('#passwordFieldContainer').slideDown();
            $('#addPassword').attr('required', true);
        } else {
            $('#passwordFieldContainer').slideUp();
            $('#addPassword').removeAttr('required').val('');
        }
    });

    // 2. Toggle Show/Hide for Plaintext Password
    $('#togglePasswordBtn').on('click', function() {
        const passField = $('#addPassword');
        const icon = $('#togglePasswordIcon');
        if (passField.attr('type') === 'password') {
            passField.attr('type', 'text');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        } else {
            passField.attr('type', 'password');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        }
    });

    // 3. Handle Add User Form Submission via AJAX
    $('#addUserForm').on('submit', function(e) {
        e.preventDefault();
        
        // Domain Check
        const emailInput = $(this).find('input[name="institutionalEmail"]').val().toLowerCase();
        const domain = "@neu.edu.ph";
        if (!emailInput.endsWith(domain)) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Email',
                text: 'Only @neu.edu.ph addresses are permitted.',
                confirmButtonColor: '#0038a8',
                position: 'center',
                width: 420
            });
            return false;
        }

        let formData = new FormData(this);
        if (!formData.has('csrf_token')) {
            formData.append('csrf_token', '<?php echo htmlspecialchars($csrfToken); ?>');
        }
        const submitBtn = $(this).find('button[type="submit"]');
        const originalBtnHtml = submitBtn.html();
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

        $.ajax({
            url: 'add_user_process.php',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                if(res.trim() === 'success') {
                    Swal.fire({ icon: 'success', title: 'User Added Successfully!', showConfirmButton: false, timer: 2000, position: 'top-end', toast: true });
                    
                    // Close modal and reset form
                    $('#addUserModal').modal('hide');
                    $('#addUserForm')[0].reset();
                    $('#passwordFieldContainer').hide();
                    
                    // Refresh the table
                    fetchUsers(); 
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Add Failed',
                        text: res,
                        confirmButtonColor: '#0038a8',
                        position: 'center',
                        width: 420
                    });
                }
                submitBtn.prop('disabled', false).html(originalBtnHtml);
            },
            error: function() {
                alert("A system error occurred.");
                submitBtn.prop('disabled', false).html(originalBtnHtml);
            }
        });
    });
});

function loadEditForm(id, type) {
    $('#userDetailsContent').html('<div class="p-5 text-center"><div class="spinner-border text-primary"></div></div>');
    $.ajax({
        url: 'fetch_edit_form.php',
        method: 'POST',
        data: { id: id, type: type },
        success: function(response) { $('#userDetailsContent').html(response); }
    });
}

$(document).on('submit', '#editUserForm', function(e) {
    e.preventDefault();
    const emailInput = $(this).find('input[name="email"]').val().toLowerCase();
    const domain = "@neu.edu.ph";

    if (!emailInput.endsWith(domain)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Email',
            text: 'Only @neu.edu.ph addresses are permitted.',
            confirmButtonColor: '#0038a8'
        });
        return false;
    }

    let formData = new FormData(this);
    if (!formData.has('csrf_token')) {
        formData.append('csrf_token', '<?php echo htmlspecialchars($csrfToken); ?>');
    }
    const submitBtn = $(this).find('button[type="submit"]');
    const originalBtnHtml = submitBtn.html();
    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

    $.ajax({
        url: 'update_user_process.php',
        method: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(res) {
            if(res.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Changes Saved!', showConfirmButton: false, timer: 2000, position: 'top-end', toast: true });
                viewUserDetails(formData.get('id'), formData.get('type'));
                fetchUsers(); 
            } else {
                Swal.fire({ icon: 'error', title: 'Update Failed', text: res });
                submitBtn.prop('disabled', false).html(originalBtnHtml);
            }
        },
        error: function() {
            alert("A system error occurred.");
            submitBtn.prop('disabled', false).html(originalBtnHtml);
        }
    });
});

function viewUserDetails(id, type) {
    $.ajax({
        url: 'fetch_user_details.php',
        method: 'POST',
        data: { id: id, type: type },
        success: function(response) { $('#userDetailsContent').html(response); }
    });
}
</script>
</body>
</html>
