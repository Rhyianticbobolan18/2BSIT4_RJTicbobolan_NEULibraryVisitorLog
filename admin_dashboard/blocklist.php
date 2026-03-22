<?php
session_start();
require_once '../includes/db_connect.php'; 
require_once '../includes/csrf.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// --- DATA FETCHING LOGIC ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Merge Students and Employees who are 'Blocked'
$sql = "
    SELECT * FROM (
        SELECT 
            s.studentID AS user_id, 
            s.firstName, 
            s.lastName, 
            'Student' AS user_type, 
            d.departmentName AS deptName,
            s.profile_image,
            s.block_reason,
            s.date_blocked
        FROM students s
        LEFT JOIN departments d ON s.departmentID = d.departmentID
        WHERE s.status = 'Blocked'
        
        UNION ALL
        
        SELECT 
            e.emplID AS user_id, 
            e.firstName, 
            e.lastName, 
            'Employee' AS user_type, 
            d.departmentName AS deptName,
            e.profile_image,
            e.block_reason,
            e.date_blocked
        FROM employees e
        LEFT JOIN departments d ON e.departmentID = d.departmentID
        WHERE e.status = 'Blocked'
    ) AS combined_blacklist
    WHERE 1=1
";

if (!empty($search)) {
    $sql .= " AND (user_id LIKE '%$search%' OR firstName LIKE '%$search%' OR lastName LIKE '%$search%')";
}

$params = [];
$types = "";
if (!empty($search)) {
    $sql .= " AND (user_id LIKE ? OR firstName LIKE ? OR lastName LIKE ?)";
    $searchLike = "%$search%";
    $params = [$searchLike, $searchLike, $searchLike];
    $types = "sss";
}

$sql .= " ORDER BY date_blocked DESC"; 

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// AJAX HANDLER: Return table rows only
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()): 
            $displayName = htmlspecialchars($row['firstName'] . ' ' . $row['lastName']);
            $imgFolder = ($row['user_type'] == 'Student') ? 'student' : 'admin';
            $profileFilename = $row['profile_image'] ?? '';
            $isRemotePhoto = (!empty($profileFilename) && preg_match('/^https?:\\/\\//i', $profileFilename));
            $imgUrl = $isRemotePhoto ? $profileFilename : "../profilepictures/$imgFolder/" . $profileFilename;
            $imgFsPath = __DIR__ . "/../profilepictures/$imgFolder/" . $profileFilename;
            
            if ($isRemotePhoto || (!empty($profileFilename) && $profileFilename !== 'default.png' && is_file($imgFsPath))) {
                $cacheBuster = (!$isRemotePhoto && is_file($imgFsPath)) ? ('?v=' . filemtime($imgFsPath)) : '';
                $imgSrc = htmlspecialchars($imgUrl . $cacheBuster);
            } else {
                $imgSrc = "https://ui-avatars.com/api/?name=" . urlencode($row['firstName'] . ' ' . $row['lastName']) . "&background=0038a8&color=fff";
            }
        ?>
            <tr>
                <td class="ps-4">
                    <div class="d-flex align-items-center">
                        <img src="<?php echo $imgSrc; ?>" class="rounded-circle me-3 border shadow-sm" style="width: 45px; height: 45px; object-fit: cover;">
                        <div>
                            <div class="fw-bold text-dark"><?php echo $row['user_id']; ?></div>
                            <div class="small text-muted"><?php echo $displayName; ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge-role <?php echo ($row['user_type'] == 'Student') ? 'role-student' : 'role-employee'; ?>">
                        <?php echo strtoupper($row['user_type']); ?>
                    </span>
                </td>
                <td class="small text-muted"><?php echo htmlspecialchars($row['deptName'] ?? 'N/A'); ?></td>
                <td>
                    <div class="d-flex flex-column">
                        <span class="text-danger fw-semibold small"><i class="bi bi-shield-lock me-1"></i>Access Restricted</span>
                        <span class="text-muted extra-small mt-1" style="font-size: 0.7rem;">
                            <i class="bi bi-calendar-event me-1"></i>
                            Blocked on: <?php echo !empty($row['date_blocked']) ? date('M d, Y | h:i A', strtotime($row['date_blocked'])) : 'N/A'; ?>
                        </span>
                        <span class="text-muted extra-small italic mt-1" style="font-size: 0.75rem; border-left: 2px solid #dc3545; padding-left: 5px;">
                            Reason: <?php echo !empty($row['block_reason']) ? htmlspecialchars($row['block_reason']) : 'No reason provided'; ?>
                        </span>
                    </div>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-success rounded-pill px-3 unblock-btn" 
                            data-id="<?php echo $row['user_id']; ?>"
                            data-type="<?php echo $row['user_type']; ?>"
                            data-name="<?php echo $displayName; ?>">
                        <i class="bi bi-unlock"></i> Restore Access
                    </button>
                </td>
            </tr>
        <?php endwhile;
    } else {
        // ADDED: no-data-row class so JS knows to skip paginating this row
        echo "<tr class='no-data-row'><td colspan='5' class='text-center py-5 text-muted'><i class='bi bi-shield-check fs-1 d-block mb-3 opacity-25'></i>No blocked users found.</td></tr>";
    }
    exit;
}

// Admin Profile Data
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
$photoFilePath = __DIR__ . "/../profilepictures/admin/" . $photoFilename;
$cacheBuster = (!$isRemoteAdminPhoto && !empty($photoFilename) && is_file($photoFilePath)) ? ('?v=' . filemtime($photoFilePath)) : '';
$photoUrl = $isRemoteAdminPhoto ? $photoFilename : "../profilepictures/admin/" . $photoFilename . $cacheBuster;
$hasPhoto = ($isRemoteAdminPhoto || (!empty($photoFilename) && is_file($photoFilePath)));
$adminInitials = getInitials($adminData['firstName'] ?? '', $adminData['lastName'] ?? '');
$csrfToken = csrf_token();

// --- REFINED STATS QUERIES ---
$statsQuery = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM students WHERE status = 'Blocked') AS student_count,
        (SELECT COUNT(*) FROM employees WHERE status = 'Blocked') AS employee_count,
        (SELECT COUNT(*) FROM students WHERE status = 'Blocked' AND DATE(date_blocked) = CURDATE()) + 
        (SELECT COUNT(*) FROM employees WHERE status = 'Blocked' AND DATE(date_blocked) = CURDATE()) AS blocked_today
");
$stats = $statsQuery->fetch_assoc();
$totalBlocked = $stats['student_count'] + $stats['employee_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocklist | NEU Library Admin</title>
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
        .initials-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--neu-blue); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
        .filter-section { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .table-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: none; }
        .badge-role { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .role-student { background: #eef2ff; color: #4338ca; }
        .role-employee { background: #fff7ed; color: #c2410c; }
        .extra-small { font-size: 0.75rem; }
        .italic { font-style: italic; }
        #loadingOverlay { display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 10; align-items: center; justify-content: center; border-radius: 15px; }
        
        /* New Stat Card Style */
          .stat-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-start: 5px solid; height: 100%; }
          @media (max-width: 992px) {
              .navbar { padding: 0.5rem 1rem; }
          }
          @media (max-width: 768px) {
              .filter-section .row { gap: 0.75rem; }
              .table-card .p-3 { flex-wrap: wrap; gap: 0.75rem; }
              .table-card .p-3 > nav { width: 100%; }
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
                <li class="nav-item"><a class="nav-link active" href="blocklist.php"><i class="bi bi-shield-slash"></i> Blocklist</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="stats.php"><i class="bi bi-graph-up"></i> Statistics</a></li>
                <li class="nav-item"><a class="nav-link" href="user_management.php"><i class="bi bi-people"></i> Users</a></li>
            </ul>
            <div class="vr ms-3 me-3"></div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3 d-none d-md-block">
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
        <h2 class="fw-bold mb-0 text-blue">Access Restrictions</h2>
        <p class="text-muted">Review blocked users, justifications, and restriction dates.</p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card border-danger">
                <h6 class="text-muted mb-2 small fw-bold text-uppercase">Total Blocked</h6>
                <h2 class="fw-bold text-danger mb-0"><?php echo $totalBlocked; ?></h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card border-warning">
                <h6 class="text-muted mb-2 small fw-bold text-uppercase">Recently Restricted (Today)</h6>
                <h2 class="fw-bold text-warning mb-0"><?php echo $stats['blocked_today']; ?></h2>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card border-primary">
                <h6 class="text-muted mb-2 small fw-bold text-uppercase">User Distribution</h6>
                <div class="d-flex align-items-center">
                    <div class="me-4">
                        <span class="fw-bold text-primary fs-3"><?php echo $stats['student_count']; ?></span>
                        <span class="text-muted small ms-1">Students</span>
                    </div>
                    <div class="border-start ps-4">
                        <span class="fw-bold text-dark fs-3"><?php echo $stats['employee_count']; ?></span>
                        <span class="text-muted small ms-1">Employees</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-section">
        <form id="filterForm" class="row g-3" onsubmit="return false;">
            <input type="hidden" name="ajax" value="1">
            <div class="col-md-10">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="searchInput" class="form-control border-start-0" placeholder="Search by ID or Name...">
                </div>
            </div>
            <div class="col-md-2">
                <button type="button" id="clearBtn" class="btn btn-outline-secondary w-100 shadow-sm"><i class="bi bi-x-circle me-1"></i> Clear</button>
            </div>
        </form>
    </div>

    <div class="table-card position-relative">
        <div id="loadingOverlay">
            <div class="spinner-border text-danger" role="status"></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Blocked User</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Restriction Details</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
        
        <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
            <div class="small text-muted" id="paginationInfo">Showing 0 to 0 of 0 entries</div>
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted fw-bold mb-0">Rows:</label>
                    <select id="rowsPerPage" class="form-select form-select-sm" style="width: auto;">
                        <option value="7" selected>7</option>
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="20">20</option>
                    </select>
                </div>
                <nav><ul class="pagination pagination-sm mb-0" id="paginationControls"></ul></nav>
            </div>
        </div>
        </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Restore Access</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div id="modalBodyText"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <a id="modalConfirmBtn" href="#" class="btn btn-success px-4 rounded-pill">Restore Now</a>
            </div>
        </div>
    </div>
</div>

<form id="unblockForm" method="POST" action="toggle_status.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    <input type="hidden" name="user_id" id="unblockUserId">
    <input type="hidden" name="user_type" id="unblockUserType">
    <input type="hidden" name="current_status" value="blocked">
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    let currentPage = 1;

    // --- ADDED: Pagination Logic ---
    function applyPagination() {
        const rows = $('#tableBody').find('tr:not(.no-data-row)');
        const totalRows = rows.length;
        
        if ($('#tableBody').find('.no-data-row').length > 0) {
            $('#paginationInfo').text('Showing 0 to 0 of 0 entries');
            $('#paginationControls').html('');
            return;
        }

        const rowsPerPage = parseInt($('#rowsPerPage').val());
        const totalPages = Math.max(Math.ceil(totalRows / rowsPerPage), 1);

        if (currentPage > totalPages) currentPage = totalPages;
        
        rows.hide().slice((currentPage - 1) * rowsPerPage, currentPage * rowsPerPage).show();

        const startIdx = totalRows > 0 ? (currentPage - 1) * rowsPerPage + 1 : 0;
        const endIdx = Math.min(currentPage * rowsPerPage, totalRows);
        
        $('#paginationInfo').text(`Showing ${startIdx} to ${endIdx} of ${totalRows} entries`);

        let controls = "";
        if (totalPages > 1) {
            controls += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="changePage(${currentPage - 1}, event)">Prev</a></li>`;
            for (let i = 1; i <= totalPages; i++) {
                controls += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" onclick="changePage(${i}, event)">${i}</a></li>`;
            }
            controls += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" onclick="changePage(${currentPage + 1}, event)">Next</a></li>`;
        }
        $('#paginationControls').html(controls);
    }

    // Exported to window so the inline onclick handlers can access it
    window.changePage = function(page, event) {
        if(event) event.preventDefault();
        currentPage = page;
        applyPagination();
    };

    $('#rowsPerPage').on('change', function() {
        currentPage = 1;
        applyPagination();
    });
    // --- END: Pagination Logic ---

    function fetchBlocklist() {
        $('#loadingOverlay').css('display', 'flex');
        $.ajax({
            url: 'blocklist.php',
            type: 'GET',
            data: $('#filterForm').serialize(),
            success: function(res) {
                $('#tableBody').html(res);
                applyPagination(); // Call pagination after table updates
                $('#loadingOverlay').hide();
            },
            error: function() {
                $('#loadingOverlay').hide();
            }
        });
    }

    fetchBlocklist();

    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        currentPage = 1; // Reset on search
        fetchBlocklist();
    });

    $('#searchInput').on('keyup', function() {
        currentPage = 1; // Reset on search
        fetchBlocklist();
    });

    $('#clearBtn').click(function() {
        $('#filterForm')[0].reset();
        currentPage = 1; // Reset on clear
        fetchBlocklist();
    });

    $(document).on('click', '.unblock-btn', function() {
        const userId = $(this).data('id');
        const userType = $(this).data('type');
        const userName = $(this).data('name');
        
        const modalBody = document.getElementById('modalBodyText');
        const confirmBtn = document.getElementById('modalConfirmBtn');
        
        modalBody.innerHTML = `Are you sure you want to <strong>restore library access</strong> for <b>${userName}</b> (ID: ${userId})? This will remove the restriction log.`;
        
        confirmBtn.onclick = function(e) {
            e.preventDefault();
            document.getElementById('unblockUserId').value = userId;
            document.getElementById('unblockUserType').value = userType.toLowerCase();
            document.getElementById('unblockForm').submit();
        };

        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    });
});
</script>
</body>
</html>
