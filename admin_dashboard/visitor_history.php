<?php
session_start();
require_once '../includes/db_connect.php'; 

// 1. SECURITY CHECK
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// --- DATA FETCHING LOGIC (Handles both AJAX and Initial Load) ---
$search = trim($_GET['search'] ?? '');
$filterDate = trim($_GET['filterDate'] ?? '');
$filterMonth = trim($_GET['filterMonth'] ?? '');
$filterYear = trim($_GET['filterYear'] ?? '');
$filterReason = trim($_GET['filterReason'] ?? '');
$filterDateRange = trim($_GET['filterDateRange'] ?? '');
$filterRole = trim($_GET['filterRole'] ?? '');
$filterDept = trim($_GET['filterDept'] ?? '');
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

$deptOptions = [];
$dept_result = $conn->query("SELECT departmentID, departmentName FROM departments ORDER BY departmentName ASC");
if ($dept_result) {
    while ($d = $dept_result->fetch_assoc()) {
        $deptOptions[] = $d;
    }
}

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
if ($filterDateRange === 'yesterday') {
    $where[] = "DATE(h.date) = DATE(CURDATE() - INTERVAL 1 DAY)";
}
if ($filterDateRange === 'lastweek') {
    $where[] = "DATE(h.date) >= DATE(CURDATE() - INTERVAL 7 DAY)";
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
if ($filterRole !== '') {
    $where[] = "h.user_type = ?";
    $params[] = $filterRole;
    $types .= "s";
}
if ($filterDept !== '') {
    $where[] = "(s.departmentID = ? OR e.departmentID = ?)";
    $params[] = $filterDept;
    $params[] = $filterDept;
    $types .= "ss";
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

// 1. EXPORT HANDLER: Return JSON for PDF/CSV export (all filtered rows)
if (isset($_GET['export']) && $_GET['export'] == '1') {
    $rows = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'id' => $row['user_identifier'],
                'name' => trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? '')),
                'role' => $row['user_type'],
                'department' => $row['deptName'] ?? 'N/A',
                'reason' => $row['reason'] ?? '',
                'date' => $row['date'] ?? '',
                'time' => $row['time'] ?? ''
            ];
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['rows' => $rows]);
    exit();
}

// 2. AJAX HANDLER: If request is AJAX, only return the table rows and stop execution
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()): ?>
            <tr>
                <td class="ps-4 fw-bold text-blue"><?php echo $row['user_identifier']; ?></td>
                <td class="fw-semibold"><?php echo htmlspecialchars($row['fName'] . ' ' . $row['lName']); ?></td>
                <td>
                    <span class="badge-role <?php echo ($row['user_type'] == 'Student') ? 'role-student' : 'role-employee'; ?>">
                        <?php echo strtoupper($row['user_type']); ?>
                    </span>
                </td>
                <td class="small text-muted"><?php echo htmlspecialchars($row['deptName'] ?? 'N/A'); ?></td>
                <td class="small"><i><?php echo htmlspecialchars($row['reason']); ?></i></td>
                <td class="fw-bold"><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                <td class="text-blue fw-bold"><?php echo date('h:i A', strtotime($row['time'])); ?></td>
            </tr>
        <?php endwhile;
    } else {
        echo "<tr><td colspan='7' class='text-center py-5 text-muted'><i class='bi bi-search fs-1 d-block mb-3 opacity-25'></i>No records found matching your filters.</td></tr>";
    }
    exit; // Stop further execution for AJAX requests
}

// --- NORMAL PAGE LOAD CONTINUES BELOW ---
$adminID = $_SESSION['emplID'];
$adminQuery = $conn->prepare("SELECT profile_image, firstName, lastName FROM employees WHERE emplID = ?");
$adminQuery->bind_param("s", $adminID);
$adminQuery->execute();
$adminData = $adminQuery->get_result()->fetch_assoc();

function getInitials($firstname, $lastname) {
    return strtoupper(substr($firstname ?? '', 0, 1) . substr($lastname ?? '', 0, 1));
}

$photoFilename = $adminData['profile_image'] ?? null;
$isRemoteAdminPhoto = (!empty($photoFilename) && preg_match('/^https?:\\/\\//i', $photoFilename));
$photoUrl = $isRemoteAdminPhoto ? $photoFilename : "../profilepictures/admin/" . $photoFilename;
$photoFilePath = __DIR__ . "/../profilepictures/admin/" . $photoFilename;
$hasPhoto = ($isRemoteAdminPhoto || (!empty($photoFilename) && is_file($photoFilePath)));
$adminInitials = getInitials($adminData['firstName'] ?? '', $adminData['lastName'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor History | NEU Library Admin</title>
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
        .btn-blue { background-color: var(--neu-blue); color: white; border-radius: 8px; transition: 0.3s; }
        .btn-blue:hover { background-color: var(--neu-hover); color: white; }
        .initials-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--neu-blue); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
        .filter-section { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .filter-grid { display: grid; grid-template-columns: repeat(7, minmax(110px, 1fr)); gap: 12px; }
        .filter-grid .filter-item { min-width: 0; }
        .filter-grid .form-select, .filter-grid .form-control { padding: 8px 10px; font-size: 0.85rem; }
        .filter-grid .form-label { font-size: 0.7rem; }
        .table-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: none; }
        .badge-role { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .role-student { background: #eef2ff; color: #4338ca; }
        .role-employee { background: #fff7ed; color: #c2410c; }
        
        /* Loading Overlay */
        #loadingOverlay { display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 10; align-items: center; justify-content: center; border-radius: 15px; }
          .pagination .page-link { color: var(--neu-blue); border: none; margin: 0 2px; border-radius: 5px; cursor: pointer; }
          .pagination .page-item.active .page-link { background-color: var(--neu-blue); color: white; }
          @media (max-width: 992px) {
              .navbar { padding: 0.5rem 1rem; }
              .filter-grid { grid-template-columns: repeat(3, minmax(120px, 1fr)); }
          }
          @media (max-width: 768px) {
              .filter-grid { grid-template-columns: repeat(2, minmax(120px, 1fr)); }
              .filter-section .input-group { width: 100%; }
              .table-toolbar { flex-direction: column; align-items: stretch; gap: 0.75rem; }
              .table-toolbar .d-flex.align-items-center.gap-2 { flex-wrap: wrap; justify-content: flex-start; }
          }
          @media (max-width: 576px) {
              .filter-grid { grid-template-columns: 1fr; }
              .table-toolbar .btn { width: 100%; }
              .table-toolbar select { width: 100% !important; }
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
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="visitor_history.php"><i class="bi bi-clock-history"></i> Visitor History Logs</a></li>
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

<div class="container-fluid px-4 px-md-5 py-4">
    <div class="mb-4">
        <h2 class="fw-bold mb-0 text-blue">Visitor History Logs</h2>
        <p class="text-muted">Real-time search and filter through all library access records.</p>
    </div>

    <div class="filter-section">
        <form id="filterForm">
            <input type="hidden" name="ajax" value="1">
            <div class="row g-3 mb-3">
                <div class="col-md-9">
                    <label class="form-label small fw-bold text-muted">Search User</label>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" id="searchInput" class="form-control border-start-0 border-end-0" placeholder="Name or ID..." autocomplete="off">
                        <button class="btn btn-blue" type="submit"><i class="bi bi-arrow-return-left me-1"></i> Search</button>
                    </div>
                </div>
                <div class="col-md-3 d-flex flex-column justify-content-end">
                    <button type="button" id="clearFiltersBtn" class="btn btn-outline-secondary w-100 shadow-sm"><i class="bi bi-x-circle"></i> Clear</button>
                </div>
            </div>
            <div class="filter-grid">
                <div class="filter-item">
                    <label class="form-label small fw-bold text-muted">Role</label>
                    <select name="filterRole" id="filterRole" class="form-select shadow-sm filter-input">
                        <option value="">All Roles</option>
                        <option value="Student">Student</option>
                        <option value="Employee">Employee</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label class="form-label small fw-bold text-muted">Department</label>
                    <select name="filterDept" id="filterDept" class="form-select shadow-sm filter-input">
                        <option value="">All Departments</option>
                        <?php foreach ($deptOptions as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['departmentID']); ?>">
                                <?php echo htmlspecialchars($dept['departmentName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label class="form-label small fw-bold text-muted">Reason</label>
                    <select name="filterReason" id="filterReason" class="form-select shadow-sm filter-input">
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
                <div class="filter-item">
                    <label class="form-label small fw-bold text-muted">Date Range</label>
                    <select name="filterDateRange" id="filterDateRange" class="form-select shadow-sm filter-input">
                        <option value="">All Time</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="lastweek">Last 7 Days</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label class="form-label small fw-bold text-muted">Date</label>
                    <input type="date" name="filterDate" class="form-control shadow-sm filter-input">
                </div>
                <div class="filter-item">
                    <label class="form-label small fw-bold text-muted">Month</label>
                    <select name="filterMonth" class="form-select shadow-sm filter-input">
                        <option value="">All Months</option>
                        <?php for ($m=1; $m<=12; $m++) echo "<option value='$m'>".date('F', mktime(0,0,0,$m,1))."</option>"; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label class="form-label small fw-bold text-muted">Year</label>
                    <select name="filterYear" class="form-select shadow-sm filter-input">
                        <option value="">All Years</option>
                        <?php for ($y=date('Y'); $y>=2023; $y--) echo "<option value='$y'>$y</option>"; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <div class="table-card position-relative">
        <div id="loadingOverlay">
            <div class="spinner-border text-primary" role="status"></div>
        </div>
          <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center table-toolbar">
            <h5 class="fw-bold mb-0 text-blue"><i class="bi bi-clock-history me-2"></i>Visitor History Records</h5>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="exportHistoryPDF()">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Save as PDF
                </button>
                <button type="button" class="btn btn-outline-success btn-sm" onclick="exportHistoryCSV()">
                    <i class="bi bi-filetype-csv me-1"></i> Save as Excel/CSV
                </button>
                <label class="small text-muted fw-bold">Rows:</label>
                <select id="rowsPerPage" class="form-select form-select-sm" style="width: auto;">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">ID Number</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Time In</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    </tbody>
            </table>
        </div>
        <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
            <div class="small text-muted" id="paginationInfo">Showing 0 to 0 of 0 entries</div>
            <nav><ul class="pagination pagination-sm mb-0" id="paginationControls"></ul></nav>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<script>
let currentPage = 1;
let rowsPerPage = 10;

function fetchExportData(onSuccess) {
    let formData = $('#filterForm').serialize();
    $.ajax({
        url: 'visitor_history.php',
        type: 'GET',
        dataType: 'json',
        data: formData + '&export=1',
        success: function(res) {
            onSuccess(res && res.rows ? res.rows : []);
        },
        error: function() {
            alert('Failed to export data. Please try again.');
        }
    });
}

function exportHistoryCSV() {
    fetchExportData(function(rows) {
        if (!rows.length) {
            alert('No rows to export.');
            return;
        }
        const headers = ['ID Number','Full Name','Role','Department','Reason','Date','Time'];
        const now = new Date();
        const exportStamp = now.toLocaleString('en-US');
        const csv = [
            `"Report Date/Time","${exportStamp}"`,
            headers.map(h => `"${h}"`).join(',')
        ];

        rows.forEach(r => {
            const cols = [
                r.id,
                r.name,
                r.role,
                r.department,
                r.reason,
                r.date,
                r.time
            ].map(v => `"${String(v ?? '').replace(/\s+/g, ' ').trim()}"`);
            csv.push(cols.join(','));
        });

        const fileStamp = now.toISOString().replace(/[:]/g, '-').slice(0,19);
        const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `NEU_Visitor_History_${fileStamp}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
}

function exportHistoryPDF() {
    if (typeof window.jspdf === 'undefined') {
        alert('PDF library not loaded. Please refresh and try again.');
        return;
    }
    fetchExportData(function(rows) {
        if (!rows.length) {
            alert('No rows to export.');
            return;
        }
        const headers = ['ID Number','Full Name','Role','Department','Reason','Date','Time'];
        const data = rows.map(r => [
            r.id,
            r.name,
            r.role,
            r.department,
            r.reason,
            r.date,
            r.time
        ]);

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape', 'pt', 'a4');
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: '2-digit' });
        const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

        doc.text('NEU Visitor History Logs', 40, 40);
        doc.setFontSize(11);
        doc.setTextColor(90);
        doc.text(`Report Date: ${dateStr}`, 40, 60);
        doc.text(`Report Time: ${timeStr}`, 40, 78);

        doc.autoTable({
            startY: 95,
            head: [headers],
            body: data
        });
        const fileStamp = now.toISOString().replace(/[:]/g, '-').slice(0,19);
        doc.save(`NEU_Visitor_History_${fileStamp}.pdf`);
    });
}

function applyPagination() {
    const $rows = $("#tableBody tr");
    const totalRows = $rows.length;
    rowsPerPage = parseInt($('#rowsPerPage').val(), 10) || 10;

    const totalPages = Math.max(Math.ceil(totalRows / rowsPerPage), 1);
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    $rows.hide();
    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    $rows.slice(start, end).show();

    updatePaginationUI(totalRows, totalPages);
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

function changePage(page) {
    currentPage = page;
    applyPagination();
}

$(document).ready(function() {
    function fetchLogs() {
        $('#loadingOverlay').css('display', 'flex');
        let formData = $('#filterForm').serialize();

        $.ajax({
            url: 'visitor_history.php',
            type: 'GET',
            data: formData,
            success: function(response) {
                $('#tableBody').html(response);
                $('#loadingOverlay').hide();
                currentPage = 1;
                applyPagination();
            }
        });
    }

    // Initial Load
    fetchLogs();

    // Submit Search (Enter Button)
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        fetchLogs();
    });

    // Auto-search on Dropdowns/Date Change
    $('.filter-input').change(function() {
        fetchLogs();
    });

    // Clear Filters
    $('#clearFiltersBtn').click(function() {
        $('#filterForm')[0].reset();
        fetchLogs();
    });

    // Live Search while typing (Optional - remove if you only want it on click)
    let typingTimer;
    $('#searchInput').on('keyup', function() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(fetchLogs, 500); // Wait 500ms after user stops typing
    });

    $('#rowsPerPage').on('change', function() {
        currentPage = 1;
        applyPagination();
    });
});
</script>
</body>
</html>
