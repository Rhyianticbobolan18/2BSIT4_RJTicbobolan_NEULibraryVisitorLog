<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$adminID = $_SESSION['emplID'];
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
$photoUrl = $isRemoteAdminPhoto ? $photoFilename : "../profilepictures/admin/" . $photoFilename;
$photoFilePath = __DIR__ . "/../profilepictures/admin/" . $photoFilename;
$hasPhoto = ($isRemoteAdminPhoto || (!empty($photoFilename) && is_file($photoFilePath)));
$adminInitials = getInitials($adminData['firstName'] ?? '', $adminData['lastName'] ?? '');

$departments = $conn->query("SELECT departmentID, departmentName FROM departments ORDER BY departmentName");
$reasons = $conn->query("SELECT DISTINCT reason FROM history_logs ORDER BY reason");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics | NEU Library Admin</title>
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
        .initials-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--neu-blue); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }

        .filter-section { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .filter-grid { display: grid; grid-template-columns: repeat(8, minmax(110px, 1fr)); gap: 12px; align-items: end; }
        .filter-grid .form-select, .filter-grid .form-control { padding: 8px 10px; font-size: 0.85rem; }
        .filter-grid .btn { height: 38px; }

        .stat-card { background: white; border-radius: 14px; padding: 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border: 1px solid #eee; height: 100%; }
          .stats-card-grid { display: grid; grid-template-columns: repeat(5, minmax(160px, 1fr)); gap: 16px; }
          @media (max-width: 1200px) { .stats-card-grid { grid-template-columns: repeat(3, minmax(160px, 1fr)); } }
          @media (max-width: 768px) { .stats-card-grid { grid-template-columns: repeat(2, minmax(160px, 1fr)); } }
          @media (max-width: 540px) { .stats-card-grid { grid-template-columns: 1fr; } }
        .stat-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.6px; color: #8e8e8e; font-weight: 700; }
        .stat-value { font-size: 1.7rem; font-weight: 800; margin-top: 6px; }
        .stat-sub { font-size: 0.75rem; color: #666; }
        .chart-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); height: 100%; }
        .section-title { font-weight: 700; color: var(--neu-blue); }
        .top-table th { font-size: 0.8rem; text-transform: uppercase; color: #7a7a7a; }
        .top-table td { font-size: 0.9rem; }
        .badge-soft { background: #eef2ff; color: #4338ca; font-weight: 700; border-radius: 20px; padding: 6px 12px; font-size: 0.75rem; }
          .export-group { display: flex; gap: 8px; justify-content: flex-end; }
          @media (max-width: 992px) {
              .navbar { padding: 0.5rem 1rem; }
              .filter-grid { grid-template-columns: repeat(3, minmax(140px, 1fr)); }
          }
          @media (max-width: 768px) {
              .filter-grid { grid-template-columns: repeat(2, minmax(140px, 1fr)); }
              .export-group { width: 100%; justify-content: flex-start; flex-wrap: wrap; }
              .export-group .btn { width: 100%; }
          }
          @media (max-width: 576px) {
              .filter-grid { grid-template-columns: 1fr; }
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
                <li class="nav-item"><a class="nav-link" href="visitor_history.php"><i class="bi bi-clock-history"></i> Visitor History Logs</a></li>
                <li class="nav-item"><a class="nav-link" href="blocklist.php"><i class="bi bi-shield-slash"></i> Blocklist</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></li>
                <li class="nav-item"><a class="nav-link active" href="stats.php"><i class="bi bi-graph-up"></i> Statistics</a></li>
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
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="fw-bold mb-0 text-blue">Statistics Reports</h2>
            <p class="text-muted mb-0">Monitor trends, visitor behavior, and activity patterns.</p>
        </div>
        <div class="export-group">
            <button class="btn btn-outline-danger rounded-pill px-4" onclick="exportStatsPDF()"><i class="bi bi-file-earmark-pdf me-2"></i>Save as PDF</button>
            <button class="btn btn-outline-success rounded-pill px-4" onclick="exportStatsCSV()"><i class="bi bi-filetype-csv me-2"></i>Save as Excel/CSV</button>
        </div>
    </div>

    <div class="filter-section mb-4">
        <div class="filter-grid">
            <div>
                <label class="form-label small fw-bold text-muted">Date Preset</label>
                <select id="presetRange" class="form-select">
                    <option value="30d" selected>Last 30 Days</option>
                    <option value="7d">Last 7 Days</option>
                    <option value="90d">Last 90 Days</option>
                    <option value="year">Year to Date</option>
                    <option value="all">All Time</option>
                </select>
            </div>
            <div>
                <label class="form-label small fw-bold text-muted">From</label>
                <input type="date" id="rangeFrom" class="form-control">
            </div>
            <div>
                <label class="form-label small fw-bold text-muted">To</label>
                <input type="date" id="rangeTo" class="form-control">
            </div>
            <div>
                <label class="form-label small fw-bold text-muted">Department</label>
                <select id="filterDept" class="form-select">
                    <option value="">All Departments</option>
                    <?php while ($d = $departments->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($d['departmentID']); ?>"><?php echo htmlspecialchars($d['departmentName']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="form-label small fw-bold text-muted">Reason</label>
                <select id="filterReason" class="form-select">
                    <option value="">All Reasons</option>
                    <?php while ($r = $reasons->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($r['reason']); ?>"><?php echo htmlspecialchars($r['reason']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="form-label small fw-bold text-muted">User Type</label>
                <select id="filterType" class="form-select">
                    <option value="">All</option>
                    <option value="Student">Student</option>
                    <option value="Employee">Faculty/Admin</option>
                </select>
            </div>
            <div>
                <button class="btn btn-blue w-100" id="applyFilters"><i class="bi bi-filter"></i> Apply</button>
            </div>
            <div>
                <button class="btn btn-light w-100 border" id="clearFilters"><i class="bi bi-x-circle"></i> Clear</button>
            </div>
        </div>
    </div>

    <div class="stats-card-grid mb-4">
        <div class="stat-card">
            <div class="stat-label">Total Visits</div>
            <div class="stat-value text-blue" id="statTotal">0</div>
            <div class="stat-sub" id="statRange">Last 30 days</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Unique Visitors</div>
            <div class="stat-value" style="color:#0f766e;" id="statUnique">0</div>
            <div class="stat-sub">Distinct people</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Student Visits</div>
            <div class="stat-value" style="color:#4338ca;" id="statStudents">0</div>
            <div class="stat-sub">Student entries</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Faculty/Admin Visits</div>
            <div class="stat-value" style="color:#c2410c;" id="statEmployees">0</div>
            <div class="stat-sub">Employee entries</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Blocked Visits</div>
            <div class="stat-value" style="color:#dc2626;" id="statBlocked">0</div>
            <div class="stat-sub">Blocked accounts activity</div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="chart-card h-100">
                <h6 class="section-title mb-3"><i class="bi bi-graph-up me-2"></i>Visitor Trend</h6>
                <canvas id="trendChart" height="130"></canvas>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="chart-card h-100">
                <h6 class="section-title mb-3"><i class="bi bi-pie-chart me-2"></i>User Type Split</h6>
                <canvas id="typeChart" height="160"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card h-100">
                <h6 class="section-title mb-3"><i class="bi bi-list-check me-2"></i>Reasons Breakdown</h6>
                <canvas id="reasonChart" height="150"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card h-100">
                <h6 class="section-title mb-3"><i class="bi bi-building me-2"></i>Department Distribution</h6>
                <canvas id="deptChart" height="150"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card h-100">
                <h6 class="section-title mb-3"><i class="bi bi-clock-history me-2"></i>Peak Hours</h6>
                <canvas id="hourChart" height="150"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card h-100">
                <h6 class="section-title mb-3"><i class="bi bi-shield-exclamation me-2"></i>Blocked Activity Trend</h6>
                <canvas id="blockedChart" height="150"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="chart-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="section-title mb-0"><i class="bi bi-award me-2"></i>Top Visitors</h6>
                    <span class="badge-soft" id="topCountBadge">Top 10</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 top-table">
                        <thead>
                            <tr>
                                <th>Visitor</th>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Visits</th>
                            </tr>
                        </thead>
                        <tbody id="topVisitorsBody">
                            <tr>
                                <td colspan="4" class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script>
    let charts = {};
    let latestStats = null;

    function fetchStats() {
        const payload = {
            preset: $('#presetRange').val(),
            from: $('#rangeFrom').val(),
            to: $('#rangeTo').val(),
            dept: $('#filterDept').val(),
            reason: $('#filterReason').val(),
            user_type: $('#filterType').val()
        };

        $.getJSON('fetch_stats.php', payload, function(data) {
            latestStats = data;
            const summary = data.summary || {};
            $('#statTotal').text((summary.totalVisits || 0).toLocaleString());
            $('#statUnique').text((summary.uniqueVisitors || 0).toLocaleString());
            $('#statStudents').text((summary.studentVisits || 0).toLocaleString());
            $('#statEmployees').text((summary.employeeVisits || 0).toLocaleString());
            $('#statBlocked').text((summary.blockedVisits || 0).toLocaleString());
            $('#statRange').text(summary.rangeLabel || '');

            renderTrend(data.trend || {labels: [], counts: []});
            renderType(data.types || []);
            renderReasons(data.reasons || []);
            renderDepartments(data.departments || []);
            renderHours(data.hours || []);
            renderBlocked(data.blockedTrend || {labels: [], counts: []});
            renderTopVisitors(data.topVisitors || []);
        });
    }

    function renderTrend(trend) {
        const ctx = document.getElementById('trendChart').getContext('2d');
        if (charts.trend) charts.trend.destroy();
        charts.trend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [{
                    label: 'Visits',
                    data: trend.counts,
                    borderColor: '#0038a8',
                    backgroundColor: 'rgba(0,56,168,0.12)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    function renderType(types) {
        const ctx = document.getElementById('typeChart').getContext('2d');
        if (charts.type) charts.type.destroy();
        charts.type = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: types.map(t => t.label),
                datasets: [{ data: types.map(t => t.value), backgroundColor: ['#4338ca', '#f97316', '#94a3b8'] }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    }

    function renderReasons(reasons) {
        const ctx = document.getElementById('reasonChart').getContext('2d');
        if (charts.reason) charts.reason.destroy();
        charts.reason = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: reasons.map(r => r.label),
                datasets: [{ label: 'Visits', data: reasons.map(r => r.value), backgroundColor: '#0ea5e9' }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    function renderDepartments(depts) {
        const ctx = document.getElementById('deptChart').getContext('2d');
        if (charts.dept) charts.dept.destroy();
        charts.dept = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: depts.map(d => d.label),
                datasets: [{ label: 'Visits', data: depts.map(d => d.value), backgroundColor: '#10b981' }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    function renderHours(hours) {
        const ctx = document.getElementById('hourChart').getContext('2d');
        if (charts.hours) charts.hours.destroy();
        const labels = hours.map((_, idx) => `${String(idx).padStart(2, '0')}:00`);
        charts.hours = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{ label: 'Visits', data: hours, backgroundColor: '#6366f1' }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    function renderBlocked(blockedTrend) {
        const ctx = document.getElementById('blockedChart').getContext('2d');
        if (charts.blocked) charts.blocked.destroy();
        charts.blocked = new Chart(ctx, {
            type: 'line',
            data: {
                labels: blockedTrend.labels,
                datasets: [{
                    label: 'Blocked Visits',
                    data: blockedTrend.counts,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220,38,38,0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    function renderTopVisitors(topVisitors) {
        const body = $('#topVisitorsBody');
        body.empty();
        if (!topVisitors.length) {
            body.append('<tr><td colspan="4" class="text-center text-muted">No records for this range.</td></tr>');
            return;
        }
        topVisitors.forEach(row => {
            body.append(`
                <tr>
                    <td>${row.full_name}</td>
                    <td>${row.user_identifier}</td>
                    <td>${row.user_type}</td>
                    <td>${Number(row.total).toLocaleString()}</td>
                </tr>
            `);
        });
    }

    function exportStatsPDF() {
        if (!latestStats || typeof window.jspdf === 'undefined') return;
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('portrait', 'pt', 'a4');
        const now = new Date();
        const displayDate = now.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
        const fileStamp = now.toISOString().slice(0, 19).replace(/[-:T]/g, '');

        doc.text("NEU Library Statistics Report", 40, 40);
        doc.setFontSize(11);
        doc.setTextColor(90);
        doc.text(`Generated: ${displayDate}`, 40, 60);
        doc.text(`Range: ${latestStats.summary.rangeLabel || ''}`, 40, 76);

        const summaryRows = [
            ['Total Visits', latestStats.summary.totalVisits],
            ['Unique Visitors', latestStats.summary.uniqueVisitors],
            ['Student Visits', latestStats.summary.studentVisits],
            ['Faculty/Admin Visits', latestStats.summary.employeeVisits],
            ['Blocked Visits', latestStats.summary.blockedVisits]
        ];

        doc.autoTable({
            startY: 96,
            head: [['Metric', 'Value']],
            body: summaryRows
        });

        const topRows = (latestStats.topVisitors || []).map(row => [
            row.full_name,
            row.user_identifier,
            row.user_type,
            row.total
        ]);
        const topStart = doc.lastAutoTable.finalY + 20;
        doc.text("Top Visitors", 40, topStart);
        doc.autoTable({
            startY: topStart + 10,
            head: [['Name', 'ID', 'Type', 'Visits']],
            body: topRows.length ? topRows : [['No records', '', '', '']]
        });

        doc.save(`NEU_Statistics_${fileStamp}.pdf`);
    }

    function exportStatsCSV() {
        if (!latestStats) return;
        const now = new Date();
        const fileStamp = now.toISOString().slice(0, 19).replace(/[-:T]/g, '');
        let csv = [];
        csv.push("NEU Library Statistics Report");
        csv.push(`Generated,${now.toLocaleString()}`);
        csv.push(`Range,${latestStats.summary.rangeLabel || ''}`);
        csv.push("");
        csv.push("Metric,Value");
        csv.push(`Total Visits,${latestStats.summary.totalVisits}`);
        csv.push(`Unique Visitors,${latestStats.summary.uniqueVisitors}`);
        csv.push(`Student Visits,${latestStats.summary.studentVisits}`);
        csv.push(`Faculty/Admin Visits,${latestStats.summary.employeeVisits}`);
        csv.push(`Blocked Visits,${latestStats.summary.blockedVisits}`);
        csv.push("");
        csv.push("Top Visitors");
        csv.push("Name,ID,Type,Visits");
        if (latestStats.topVisitors && latestStats.topVisitors.length) {
            latestStats.topVisitors.forEach(row => {
                csv.push(`"${row.full_name}",${row.user_identifier},${row.user_type},${row.total}`);
            });
        } else {
            csv.push("No records,,,");
        }

        const csvString = csv.join("\n");
        const link = document.createElement("a");
        link.style.display = 'none';
        link.setAttribute("target", "_blank");
        link.setAttribute("href", "data:text/csv;charset=utf-8," + encodeURIComponent(csvString));
        link.setAttribute("download", `NEU_Statistics_${fileStamp}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    $('#applyFilters').on('click', function() {
        fetchStats();
    });

    $('#clearFilters').on('click', function() {
        $('#presetRange').val('30d');
        $('#rangeFrom').val('');
        $('#rangeTo').val('');
        $('#filterDept').val('');
        $('#filterReason').val('');
        $('#filterType').val('');
        fetchStats();
    });

    $('#presetRange').on('change', function() {
        if (this.value !== 'all') {
            $('#rangeFrom').val('');
            $('#rangeTo').val('');
        }
    });

    $(document).ready(function() {
        fetchStats();
    });
</script>
</body>
</html>
