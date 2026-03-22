<?php
session_start();
require_once '../includes/db_connect.php';

// Ensure only logged-in admins can access user details
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit('Unauthorized access');
}

if (!isset($_POST['id']) || !isset($_POST['type'])) {
    exit('Invalid Request');
}

$id = trim($_POST['id']);
$type = $_POST['type'];
if (!in_array($type, ['Student', 'Employee'], true)) {
    exit('Invalid Request');
}

// --- AJAX HANDLER FOR FULL LOGS ---
if (isset($_POST['full_logs']) && $_POST['full_logs'] == 'true') {
    $logSql = "SELECT * FROM history_logs WHERE user_identifier = ? AND user_type = ? ORDER BY date DESC, time DESC";
    $logStmt = $conn->prepare($logSql);
    $logStmt->bind_param("ss", $id, $type);
    $logStmt->execute();
    $logs = $logStmt->get_result();

    if ($logs->num_rows > 0) {
        while($l = $logs->fetch_assoc()) {
            $reasonLower = strtolower($l['reason'] ?? '');
            $dateValue = htmlspecialchars($l['date'] ?? '');
            echo '<div class="list-group-item log-item d-flex align-items-center justify-content-between py-3 border-0 bg-transparent" data-reason="'.$reasonLower.'" data-date="'.$dateValue.'">
                    <div class="d-flex align-items-center">
                        <div class="log-icon-box me-3"><i class="bi bi-book"></i></div>
                        <div>
                            <div class="fw-bold text-dark small">'.htmlspecialchars($l['reason']).'</div>
                            <div class="text-muted extra-small">Main Library</div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-dark small">'.date('M d, Y', strtotime($l['date'])).'</div>
                        <div class="text-muted extra-small">'.date('h:i A', strtotime($l['time'])).'</div>
                    </div>
                  </div>';
        }
    } else {
        echo '<div class="text-center py-4 text-muted small">No activity logs found.</div>';
    }
    exit;
}

// --- NORMAL MODAL LOAD ---
$table = ($type === 'Student') ? 'students' : 'employees';
$idCol = ($type === 'Student') ? 'studentID' : 'emplID';

$sql = "SELECT u.*, d.departmentName FROM $table u 
        LEFT JOIN departments d ON u.departmentID = d.departmentID 
        WHERE u.$idCol = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) { exit('User not found'); }

// Today's Presence
$today = date('Y-m-d');
$loginQuery = $conn->prepare("SELECT time FROM history_logs WHERE user_identifier = ? AND date = ? ORDER BY time DESC LIMIT 1");
$loginQuery->bind_param("ss", $id, $today);
$loginQuery->execute();
$todayLogin = $loginQuery->get_result()->fetch_assoc();

// Initial 2 Logs
$logSql = "SELECT * FROM history_logs WHERE user_identifier = ? AND user_type = ? ORDER BY date DESC, time DESC LIMIT 2";
$logStmt = $conn->prepare($logSql);
$logStmt->bind_param("ss", $id, $type);
$logStmt->execute();
$initialLogsHtml = "";
$logs = $logStmt->get_result();
if ($logs->num_rows > 0) {
    while($l = $logs->fetch_assoc()) {
        $reasonLower = strtolower($l['reason'] ?? '');
        $dateValue = htmlspecialchars($l['date'] ?? '');
        $initialLogsHtml .= '
        <div class="list-group-item log-item d-flex align-items-center justify-content-between py-3 border-0 bg-transparent" data-reason="'.$reasonLower.'" data-date="'.$dateValue.'">
            <div class="d-flex align-items-center">
                <div class="log-icon-box me-3"><i class="bi bi-book"></i></div>
                <div>
                    <div class="fw-bold text-dark small">'.htmlspecialchars($l['reason']).'</div>
                    <div class="text-muted extra-small">Main Library</div>
                </div>
            </div>
            <div class="text-end">
                <div class="fw-bold text-dark small">'.date('M d, Y', strtotime($l['date'])).'</div>
                <div class="text-muted extra-small">'.date('h:i A', strtotime($l['time'])).'</div>
            </div>
        </div>';
    }
} else {
    $initialLogsHtml = '<div class="text-center py-4 text-muted small">No recent activity.</div>';
}

$folder = ($type === 'Student') ? 'student' : 'admin';
$profileImage = $user['profile_image'] ?? '';
$isRemotePhoto = (!empty($profileImage) && preg_match('/^https?:\\/\\//i', $profileImage));
$localRelativePath = "../profilepictures/$folder/" . $profileImage;
$localFilePath = __DIR__ . "/../profilepictures/$folder/" . $profileImage;
$imagePath = $isRemotePhoto ? $profileImage : $localRelativePath;
$hasPhoto = ($isRemotePhoto || (!empty($profileImage) && $profileImage !== 'default.png' && is_file($localFilePath)));
$cacheBuster = (!$isRemotePhoto && $hasPhoto) ? ('?v=' . filemtime($localFilePath)) : '';
$initials = strtoupper(substr($user['firstName'], 0, 1) . substr($user['lastName'], 0, 1));
$statusColor = (strtolower($user['status']) === 'active') ? '#2ecc71' : '#e74c3c';
?>

<style>
    /* Blink Animation */
    @keyframes blink-presence {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.4; transform: scale(1.1); }
        100% { opacity: 1; transform: scale(1); }
    }

    .blinking-dot {
        font-size: 1.7rem !important; /* Made dot bigger */
        display: inline-block;
        animation: blink-presence 1.5s infinite ease-in-out;
        vertical-align: middle;
        margin-right: 4px;
    }

    .profile-header-container { padding: 20px 0; }
    .main-avatar-wrapper { position: relative; width: 120px; height: 120px; margin: 0 auto 15px; }
    .modal-profile-img-lg, .modal-initials-lg {
        width: 120px; height: 120px;
        border-radius: 50%;
        object-fit: cover;
        display: flex; align-items: center; justify-content: center;
        border: 4px solid #fff;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .modal-initials-lg { background: #002d72; color: white; font-size: 2.5rem; font-weight: 700; }
    .status-indicator-dot {
        position: absolute; bottom: 8px; right: 8px;
        width: 22px; height: 22px;
        background: <?= $statusColor ?>;
        border: 3px solid #fff; border-radius: 50%;
    }
    .user-name-title { color: #1a202c; font-weight: 800; font-size: 1.5rem; }
    .id-badge { background: #f1f5f9; color: #64748b; font-size: 0.75rem; padding: 4px 12px; border-radius: 20px; font-weight: 600; }
    .role-label { background: #e0e7ff; color: #4338ca; font-size: 0.65rem; font-weight: 800; letter-spacing: 0.5px; padding: 2px 10px; border-radius: 12px; }
    
    .section-title { font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
    .detail-card { background: #fff; border: 1px solid #f1f5f9; padding: 12px 15px; border-radius: 12px; height: 100%; }
    .detail-label { font-size: 0.65rem; color: #94a3b8; font-weight: 600; margin-bottom: 2px; }
    .detail-value { font-size: 0.8rem; font-weight: 700; color: #1e293b; display: block; }
    
    .log-icon-box { background: #e0f2fe; color: #0369a1; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
    .extra-small { font-size: 0.7rem; }
    .log-container { transition: all 0.3s ease; }
    
    .btn-action-outline { border: 2px solid #002d72; color: #002d72; font-weight: 700; font-size: 0.85rem; border-radius: 10px; padding: 10px; transition: 0.2s; }
    .btn-action-primary { background: #002d72; color: white; border: none; font-weight: 700; font-size: 0.85rem; border-radius: 10px; padding: 10px; }
    .btn-delete-permanent { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; width: 100%; font-weight: 700; padding: 12px; border-radius: 10px; margin-top: 10px; font-size: 0.85rem; }
    .btn-delete-permanent:hover { background: #fed7d7; }
</style>

<div class="modal-header border-0 pb-0 px-4">
    <div class="d-flex align-items-center">
        <i class="bi bi-person-circle text-primary me-2"></i>
        <span class="fw-bold text-dark small" style="letter-spacing: 0.5px;">User Profile</span>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body px-4 pt-0">
    <div class="text-center profile-header-container">
        <div class="main-avatar-wrapper">
            <?php if ($hasPhoto): ?>
                <img src="<?= htmlspecialchars($imagePath . $cacheBuster) ?>" class="modal-profile-img-lg">
            <?php else: ?>
                <div class="modal-initials-lg"><?= $initials ?></div>
            <?php endif; ?>
            <div class="status-indicator-dot"></div>
        </div>
        
        <h3 class="user-name-title mb-1"><?= htmlspecialchars($user['firstName'].' '.$user['lastName']) ?></h3>
        <div class="d-flex justify-content-center align-items-center gap-2">
            <span class="role-label"><?= strtoupper($type) ?></span>
            <span class="id-badge">ID: <?= htmlspecialchars($id) ?></span>
        </div>
    </div>

    <div class="mt-4">
        <h6 class="section-title">Key Details</h6>
        <div class="row g-3">
            <div class="col-6">
                <div class="detail-card">
                    <label class="detail-label">Email Address</label>
                    <span class="detail-value text-truncate"><?= htmlspecialchars($user['institutionalEmail']) ?></span>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-card">
                    <label class="detail-label">Department</label>
                    <span class="detail-value"><?= htmlspecialchars($user['departmentName'] ?? 'N/A') ?></span>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-card">
                    <label class="detail-label">Today's Presence</label>
                    <?php if ($todayLogin): ?>
                        <span class="detail-value text-success">
                            <i class="bi bi-dot blinking-dot"></i> In (<?= date('h:i A', strtotime($todayLogin['time'])) ?>)
                        </span>
                    <?php else: ?>
                        <span class="detail-value text-muted">Not In Today</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-card">
                    <label class="detail-label">Account Status</label>
                    <div class="detail-value"> <?php 
                            $isBlocked = (strtolower($user['status']) === 'blocked');
                            $statusText = $isBlocked ? 'BLOCKED' : 'ACTIVE';
                            $badgeClass = $isBlocked ? 'bg-danger' : 'bg-success';
                        ?>
                        <span class="badge <?= $badgeClass ?> opacity-75 extra-small">
                            <?= $statusText ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="section-title mb-0">Activity Logs</h6>
            <a href="javascript:void(0)" id="toggleLogsLink" class="text-primary fw-bold extra-small text-decoration-none" onclick="toggleActivityLogs('<?= $id ?>', '<?= $type ?>')">View All</a>
        </div>

        <div class="mb-2 d-flex gap-2 align-items-center">
            <select id="reasonFilterInput" class="form-select form-select-sm">
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
            <input type="date" id="dateFilterInput" class="form-control form-control-sm" style="max-width: 165px;">
            <button type="button" id="reasonFilterClear" class="btn btn-outline-secondary btn-sm">Clear</button>
        </div>
        
        <div id="logsWrapper" class="log-container">
            <div class="list-group list-group-flush" id="activityLogList">
                <?= $initialLogsHtml ?>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer border-0 p-4 pt-0">
    <div class="w-100">
        <div class="row g-2">
            <div class="col-6">
                <button class="btn btn-action-outline w-100 btn-toggle-status" 
                        data-id="<?= $id ?>" 
                        data-type="<?= $type ?>" 
                        data-name="<?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?>"
                        data-status="<?= $user['status'] ?>">
                    <i class="bi bi-shield-slash me-2"></i><?= ($user['status'] === 'Active') ? 'Block User' : 'Unblock' ?>
                </button>
            </div>
            <div class="col-6">
                <button class="btn btn-action-primary w-100" onclick="loadEditForm('<?= $id ?>', '<?= $type ?>')">
                    <i class="bi bi-pencil me-2"></i>Edit Profile
                </button>
            </div>
        </div>
        <button type="button" class="btn btn-delete-permanent" 
                onclick="showDeleteModal('<?= $id ?>', '<?= $type ?>', '<?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?>')">
            <i class="bi bi-trash3 me-2"></i> PERMANENTLY DELETE USER
        </button>
    </div>
</div>

<script>
$(document).off('click', '.btn-toggle-status');

var initialLogs = `<?= addslashes($initialLogsHtml) ?>`;
var logsExpanded = false;
var fullLogsCache = '';
var fullLogsLoaded = false;
const currentUserId = '<?= addslashes($id) ?>';
const currentUserType = '<?= addslashes($type) ?>';

function loadFullLogs(applyFilterAfter = false) {
    const list = $('#activityLogList');
    const link = $('#toggleLogsLink');

    $.ajax({
        url: 'fetch_user_details.php',
        method: 'POST',
        data: { id: currentUserId, type: currentUserType, full_logs: 'true' },
        success: function(response) {
            fullLogsCache = response;
            fullLogsLoaded = true;
            list.html(response);
            link.text('Minimize');
            logsExpanded = true;
            if (applyFilterAfter) {
                filterActivityLogs();
            }
        }
    });
}

function filterActivityLogs() {
    const term = ($('#reasonFilterInput').val() || '').toLowerCase();
    const dateTerm = ($('#dateFilterInput').val() || '').toLowerCase();
    const isFiltering = term !== '' || dateTerm !== '';
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
    const list = $('#activityLogList');
    const items = list.children('.list-group-item');
    let visible = 0;
    $('#logFilterEmpty').remove();

    if (isFiltering) {
        if (!fullLogsLoaded) {
            loadFullLogs(true);
            return;
        }
        if (fullLogsCache) {
            list.html(fullLogsCache);
        }
    } else if (!logsExpanded) {
        list.html(initialLogs);
    }

    const matchedHtml = [];
    list.children('.list-group-item').each(function() {
        const dataReason = ($(this).data('reason') || '').toString().toLowerCase().trim();
        const dataDate = ($(this).data('date') || '').toString().toLowerCase().trim();
        const textReason = ($(this).find('.fw-bold').first().text() || '').toString().toLowerCase().trim();
        const reasonText = dataReason || textReason;
        let show = term === '' || reasonText.indexOf(term) > -1 || term.indexOf(reasonText) > -1;
        const matchesDate = dateTerm === '' || dataDate === dateTerm;
        if (term === 'others') {
            show = reasonOptions.every(option => reasonText.indexOf(option) === -1);
        }
        const finalShow = show && matchesDate;
        if (finalShow) {
            matchedHtml.push($(this)[0].outerHTML);
            visible += 1;
        }
    });

    if (isFiltering) {
        if (visible > 0) {
            list.html(matchedHtml.join(''));
        } else {
            list.html('<div id="logFilterEmpty" class="text-center py-3 text-muted small">No logs match the selected filters.</div>');
        }
    }
}

function toggleActivityLogs(id, type) {
    const list = $('#activityLogList');
    const link = $('#toggleLogsLink');
    const term = ($('#reasonFilterInput').val() || '').toLowerCase();
    const dateTerm = ($('#dateFilterInput').val() || '').toLowerCase();
    const isFiltering = term !== '' || dateTerm !== '';

    if (!logsExpanded) {
        loadFullLogs(true);
    } else {
        if (isFiltering) {
            filterActivityLogs();
        } else {
            list.html(initialLogs);
            link.text('View All'); // Change text back to View All
            logsExpanded = false;
            filterActivityLogs();
        }
    }
}

$(document).on('click', '.btn-toggle-status', function(e) {
    e.preventDefault();
    const userId = $(this).data('id');
    const userType = $(this).data('type');
    const currentStatus = $(this).data('status');
    const userName = $(this).data('name');

    const action = currentStatus === 'Active' ? 'block' : 'unblock';

    if (typeof confirmStatusChange === 'function') {
        confirmStatusChange(userId, userType.toLowerCase(), currentStatus.toLowerCase(), action, userName);
    } else {
        window.location.reload();
    }
});

$(document).on('change', '#reasonFilterInput, #dateFilterInput', function() {
    filterActivityLogs();
});

$(document).on('click', '#reasonFilterClear', function() {
    $('#reasonFilterInput').val('');
    $('#dateFilterInput').val('');
    if (!logsExpanded) {
        $('#toggleLogsLink').text('View All');
    }
    filterActivityLogs();
});
</script>
