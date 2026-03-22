<?php
session_start();
require_once 'includes/db_connect.php';

// Logic Fix: Only clear sessions if there is NO error and NO login attempt
if (!isset($_POST['check_user']) && !isset($_GET['error'])) {
    unset($_SESSION['user_id']);
    unset($_SESSION['role']);
}

$show_error_modal = false;
$error_type = null;
if (isset($_SESSION['error_type'])) {
    $error_type = $_SESSION['error_type'];
    unset($_SESSION['error_type']);
    $show_error_modal = true;
}
if (isset($_GET['error'])) {
    $error_type = $error_type ?? $_GET['error'];
    $show_error_modal = $show_error_modal || in_array($error_type, ['1', 'invalid_domain'], true);
}

if (isset($_POST['check_user'])) {
    $identifier = trim($_POST['user_identifier'] ?? '');
    if ($identifier === '') {
        $fallback_id = trim($_POST['user_id'] ?? '');
        $fallback_email = trim($_POST['user_email'] ?? '');
        $identifier = $fallback_id !== '' ? $fallback_id : $fallback_email;
    }
    if ($identifier === '') {
        header("Location: index.php?error=1");
        exit();
    }

    if (strpos($identifier, '@') !== false && !preg_match('/@neu\.edu\.ph$/i', $identifier)) {
        $_SESSION['error_type'] = 'invalid_domain';
        header("Location: index.php?error=1");
        exit();
    }
    
    // 1. First, find the valid ID and user details from Students or Employees
    $found_id = null;
    $user_data = null;

    // Search Students - Added block_reason
    $stmt = $conn->prepare("SELECT studentID, firstName, lastName, profile_image, departmentID, status, block_reason FROM students WHERE studentID = ? OR institutionalEmail = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { 
        $found_id = $row['studentID']; 
        $user_data = $row;
        $user_data['type'] = 'student';
    }

    if (!$found_id) {
        // Search Employees - Added block_reason
        $stmt = $conn->prepare("SELECT emplID, firstName, lastName, profile_image, departmentID, status, block_reason FROM employees WHERE emplID = ? OR institutionalEmail = ?");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) { 
            $found_id = $row['emplID']; 
            $user_data = $row;
            $user_data['type'] = 'admin'; 
        }
    }

    // 2. CHECK IF BLOCKED BEFORE PROCEEDING
    if ($found_id) {
        if (strtolower($user_data['status']) === 'blocked') {
            $_SESSION['blocked_user'] = $user_data;
            header("Location: index.php?error=blocked");
            exit();
        }

        // 3. If NOT blocked, check if they already logged in TODAY
        $check_log = $conn->prepare("SELECT logID FROM history_logs WHERE user_identifier = ? AND date = CURDATE()");
        $check_log->bind_param("s", $found_id);
        $check_log->execute();
        if ($check_log->get_result()->num_rows > 0) {
            header("Location: index.php?error=already_logged");
            exit();
        } else {
            $_SESSION['user_id'] = $found_id;
            header("Location: entrynavpages/VisitorEntryForm.php");
            exit();
        }
    } else {
        header("Location: index.php?error=1");
        exit();
    }
}

$count_query = $conn->query("SELECT COUNT(*) as total FROM history_logs WHERE DATE(date) = CURDATE()");
$visitor_data = $count_query->fetch_assoc();
$todays_count = $visitor_data['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>NEU Library Visitor Log System</title>
    <link rel="icon" type="image/png" href="assets/neu.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        :root { --neu-blue: #0038a8; --neu-red: #dc3545; --bg-body: #eeeeee; --card-bg: #ffffff; --border-color: #e2e8f0; }
        html, body { height: 100%; margin: 0; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; display: flex; flex-direction: column; }
        .navbar { background: var(--card-bg); border-bottom: 1px solid var(--border-color); padding: 0.8rem 2rem; position: sticky; top: 0; z-index: 1000; flex-shrink: 0; }
        .nav-link { color: #6c757d !important; font-weight: 600; transition: 0.2s; }
        .nav-link:hover { color: var(--neu-blue) !important; }
        .nav-link.active { color: var(--neu-blue) !important; opacity: 1; }
        .btn-admin { background-color: var(--neu-blue) !important; color: #fff !important; border-radius: 8px; font-weight: 600; padding: 8px 20px; text-decoration: none; }
        .main-wrapper { flex: 1 0 auto; display: flex; justify-content: center; align-items: center; padding: 40px 20px; }
        .split-container { display: flex; gap: 30px; width: 1150px; }
        .toast-container { position: fixed; top: 90px; right: 20px; z-index: 9999; }
        .custom-toast { background: #fff; border-left: 5px solid var(--neu-red); padding: 15px 20px; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; min-width: 320px; transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .custom-toast.show { transform: translateX(0); }
        .banner-section { width: 700px; flex-shrink: 0; }
        .slider-wrapper { position: relative; height: 500px; border-radius: 30px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); }
        .slides-container { display: flex; height: 100%; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
        .slide { min-width: 100%; height: 100%; background-size: cover; background-position: center; display: flex; flex-direction: column; justify-content: flex-end; padding: 40px; position: relative; }
        .slide::after { content: ''; position: absolute; inset: 0; background: linear-gradient(transparent, rgba(0,0,0,0.8)); }
        .slide-content { position: relative; z-index: 2; color: white; }
        .arrow { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.2); border: none; color: white; border-radius: 50%; width: 45px; height: 45px; z-index: 10; cursor: pointer; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); transition: 0.3s; }
        .arrow-left { left: 20px; } .arrow-right { right: 20px; }
        .dots { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 10; }
        .dot { width: 10px; height: 10px; background: rgba(255,255,255,0.4); border-radius: 50%; border: none; cursor: pointer; padding: 0; }
        .dot.active { background: white; transform: scale(1.3); }
        .divcardmain { width: 420px; flex-shrink: 0; background: var(--card-bg); border-radius: 30px; padding: 35px; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05); }
        .scanner-instruction { text-align: center; font-size: 0.75rem; font-weight: 800; color: var(--neu-blue); letter-spacing: 0.5px; margin-bottom: 10px; text-transform: uppercase; }
        .scanner-viewport { width: 100%; height: 260px; background: #000; border-radius: 20px; margin: 0 auto 20px auto; position: relative; overflow: hidden; }
        #reader { width: 100% !important; height: 100% !important; }
        #reader video { width: 100% !important; height: 100% !important; object-fit: cover !important; }
        .scanner-mask { position: absolute; inset: 0; background: rgba(0, 0, 0, 0.3); z-index: 5; pointer-events: none; }
        .scanner-frame { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 180px; height: 180px; z-index: 10; box-shadow: 0 0 0 1000px rgba(0, 0, 0, 0.2); }
        .bracket { position: absolute; width: 25px; height: 25px; border: 4px solid #fff; }
        .tl { top: 0; left: 0; border-right: none; border-bottom: none; } .tr { top: 0; right: 0; border-left: none; border-bottom: none; }
        .bl { bottom: 0; left: 0; border-right: none; border-top: none; } .br { bottom: 0; right: 0; border-left: none; border-top: none; }
        .scanning-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; font-size: 0.75rem; font-weight: 800; letter-spacing: 2px; z-index: 11; text-shadow: 0 2px 4px rgba(0,0,0,0.5); animation: blink 1.5s infinite; }
        @keyframes blink { 0%, 100% { opacity: 0.4; } 50% { opacity: 1; } }
        .laser { position: absolute; width: 100%; height: 2px; background: var(--neu-blue); box-shadow: 0 0 10px var(--neu-blue); top: 0; z-index: 12; animation: scanline 2.5s infinite ease-in-out alternate; }
        @keyframes scanline { 0% { top: 0%; } 100% { top: 100%; } }
        #cam-switch-btn { position: absolute; bottom: 12px; right: 12px; z-index: 25; background: rgba(255, 255, 255, 0.95); border: none; padding: 6px 10px; border-radius: 8px; color: #333; display: none; align-items: center; gap: 6px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); cursor: pointer; }
        #cam-switch-btn span { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
          @media (max-width: 768px) { 
              #cam-switch-btn { display: flex; } 
              .navbar { padding: 0.8rem 1rem; }
              .btn-admin { margin-top: 10px; width: 100%; text-align: center; }
          }
        .divider { display: flex; align-items: center; text-align: center; margin: 20px 0; color: #6c757d; font-size: 0.7rem; font-weight: 800; }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid var(--border-color); }
        .divider::before { margin-right: 15px; } .divider::after { margin-left: 15px; }
          footer { flex-shrink: 0; padding: 25px; text-align: center; color: #6c757d; font-size: 0.85rem; border-top: 1px solid var(--border-color); background: #f8f9fa; }
          @media (max-width: 1150px) { .split-container { flex-direction: column; align-items: center; width: 100%; } .banner-section { display: none; } .divcardmain { width: 100%; max-width: 450px; } }
          @media (max-width: 768px) {
              .main-wrapper { padding: 24px 16px; }
              .divcardmain { padding: 24px; }
              .scanner-viewport { height: 220px; }
          }
          @media (max-width: 480px) {
              .scanner-viewport { height: 200px; }
              .divcardmain { padding: 20px; }
          }
          
          /* Fixed: Oval/Badge styles */
          .italic { font-style: italic; }
      </style>
</head>
<body>

    <audio id="scanSound" src="assets/beep.mp3" preload="auto"></audio>

    <div class="toast-container" id="toastContainer">
        <div class="custom-toast" id="errorToast">
            <i class="bi bi-exclamation-circle-fill text-danger fs-5 me-2"></i>
            <span id="toastMsg" style="font-weight:600; font-size:0.9rem; color:#334155;">Error</span>
        </div>
    </div>

    <nav class="navbar navbar-expand-md navbar-light">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/neu.png" alt="NEU" width="38" onerror="this.style.display='none'">
                <span class="ms-2"><strong>NEU Library Visitor Log</strong></span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto align-items-center">
                    <a href="index.php" class="nav-link active px-3 text-decoration-none">Home</a>
                    <a href="entrynavpages/qrmaker.php" class="nav-link px-3 text-decoration-none">My QR Code</a>
                    <a href="entrynavpages/help.php" class="nav-link px-3 text-decoration-none">Help & Support</a>
                    <a href="admin_dashboard/login.php" class="btn btn-admin ms-md-3">Admin Login</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-wrapper">
        <div class="split-container">
            <div class="banner-section">
                <div class="slider-wrapper">
                    <button class="arrow arrow-left" onclick="plusSlides(-1)"><i class="bi bi-chevron-left"></i></button>
                    <button class="arrow arrow-right" onclick="plusSlides(1)"><i class="bi bi-chevron-right"></i></button>
                    <div class="slides-container" id="slides">
                        <div class="slide" style="background-image: url('assets/slideshow images/library1.jpg'); background-color: #333;"><div class="slide-content"><h1>Welcome to NEU</h1><p>Excellence in academic research.</p></div></div>
                        <div class="slide" style="background-image: url('assets/slideshow images/library2.jpg'); background-color: #444;"><div class="slide-content"><h1>Quiet Study</h1><p>Focused spaces for your growth.</p></div></div>
                        <div class="slide" style="background-image: url('assets/slideshow images/library3.jpg'); background-color: #555;"><div class="slide-content"><h1>Digital Hub</h1><p>Connect to global knowledge.</p></div></div>
                        <div class="slide" style="background-image: url('assets/slideshow images/library4.jpg'); background-color: #666;"><div class="slide-content"><h1>Resources</h1><p>Access thousands of archives.</p></div></div>
                        <div class="slide" style="background-image: url('assets/slideshow images/library5.jpg'); background-color: #777;"><div class="slide-content"><h1>Innovation</h1><p>Modern tools for modern students.</p></div></div>
                    </div>
                    <div class="dots" id="dots"></div>
                </div>
                <div class="mt-4 px-2 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold">Library Hours: 08:00 AM - 7:00 PM</div>
                        <div class="text-danger small fw-bold mt-1"><i class="bi bi-qr-code-scan me-2"></i>Please Have Your ID or QR Code Ready For Scanning</div>
                    </div>
                    <div class="bg-white border rounded-pill px-4 py-2 shadow-sm small fw-bold text-primary">TODAY'S VISITORS: <?php echo $todays_count; ?></div>
                </div>
            </div>

            <div class="divcardmain">
                <?php if (isset($_GET['error']) && $_GET['error'] == 'already_logged'): ?>
                    <div class="alert alert-warning alert-dismissible fade show p-2 mb-3 shadow-sm" role="alert" style="border-radius: 12px; border-left: 4px solid #ffc107;">
                        <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5 me-2"></i>
                            <div style="font-size: 0.75rem; line-height: 1.2;">
                            <strong class="d-block">ACCESS DENIED</strong>
                                You have already recorded an entry for today.
                            </div>
                        <button type="button" class="btn-close small" data-bs-dismiss="alert" style="padding: 0.8rem;"></button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php 
                if (isset($_GET['status']) && $_GET['status'] == 'success'): 
                    $stmt = $conn->prepare("
                        SELECT h.date, h.time, h.reason,
                               COALESCE(s.firstName, e.firstName) as fName, 
                               COALESCE(s.lastName, e.lastName) as lName,
                               COALESCE(s.departmentID, e.departmentID) as dept,
                               COALESCE(s.profile_image, e.profile_image) as img,
                               CASE WHEN s.studentID IS NOT NULL THEN 'Student' ELSE 'Faculty/Staff' END as display_role,
                               CASE WHEN s.studentID IS NOT NULL THEN 'student' ELSE 'admin' END as user_type
                        FROM history_logs h
                        LEFT JOIN students s ON h.user_identifier = s.studentID
                        LEFT JOIN employees e ON h.user_identifier = e.emplID
                        ORDER BY h.logID DESC LIMIT 1
                    ");
                    $stmt->execute();
                    $logData = $stmt->get_result()->fetch_assoc();
                ?>
                <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="true" data-bs-keyboard="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content text-center border-0 shadow-lg" style="border-radius: 30px; overflow: hidden; position: relative;">
                            <div class="modal-body p-5">
                                <div class="mb-4 mt-2">
                                    <img src="assets/neu.png" alt="NEU Logo" class="mb-2" style="height: 70px;">
                                    <div class="fw-bold mb-3" style="color: black; font-size: 1.1rem; letter-spacing: 0.5px;">
                                        NEW ERA UNIVERSITY LIBRARY
                                    </div>
                                    
                                    <div class="mx-auto bg-success d-flex align-items-center justify-content-center shadow" style="width: 90px; height: 90px; border-radius: 50%;">
                                        <i class="bi bi-check-lg text-white" style="font-size: 3rem;"></i>
                                    </div>
                                    <h2 class="fw-bold mt-3 mb-0" style="color: #0038a8;">LOGGED SUCCESSFULLY</h2>
                                    <p class="text-muted fw-semibold">Welcome to the NEU Library!</p>
                                </div>
                                <div class="row align-items-center text-start bg-secondary-subtle rounded-4 p-4 mx-md-2">
                                    <div class="col-md-4 text-center mb-3 mb-md-0">
                                        <?php
                                            $imgValue = $logData['img'] ?? '';
                                            $isRemoteImg = !empty($imgValue) && preg_match('/^https?:\\/\\//i', $imgValue);
                                            $imgSrc = $isRemoteImg ? $imgValue : "profilepictures/" . $logData['user_type'] . "/" . $imgValue;
                                        ?>
                                        <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                                            class="rounded-circle border border-5 border-white shadow-sm" 
                                            style="width: 160px; height: 160px; object-fit: cover;"
                                            onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($logData['fName'] . ' ' . $logData['lName']); ?>&background=0038a8&color=fff&bold=true'">
                                    </div>
                                    <div class="col-md-8 ps-md-4">
                                        <div class="mb-3">
                                            <label class="small text-uppercase fw-bold text-muted">Name</label>
                                            <div class="h3 fw-bold text-dark mb-0"><?php echo $logData['fName'] . ' ' . $logData['lName']; ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-4">
                                                <label class="small text-uppercase fw-bold text-muted">Role</label>
                                                <div class="h6 text-dark mb-0 fw-bold"><?php echo $logData['display_role']; ?></div>
                                            </div>
                                            <div class="col-4">
                                                <label class="small text-uppercase fw-bold text-muted">Department</label>
                                                <div class="h6 text-dark fw-bold mb-0"><?php echo $logData['dept']; ?></div>
                                            </div>
                                            <div class="col-4">
                                                <label class="small text-uppercase fw-bold text-muted">Reason of Visit</label>
                                                <div class="h6 text-primary mb-0 fw-bold"><?php echo strtoupper($logData['reason']); ?></div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-4 pt-2 border-top">
                                            <div><label class="small text-uppercase fw-bold text-muted">Date</label><div class="fw-bold text-dark"><?php echo date("M d, Y", strtotime($logData['date'])); ?></div></div>
                                            <div><label class="small text-uppercase fw-bold text-muted">Time In</label><div class="fw-bold text-dark"><?php echo date("h:i A", strtotime($logData['time'])); ?></div></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 fs-5 text-muted">Closing in <span id="timerText" class="fw-bold" style="color: #0038a8;">30</span>...</div>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() { 
                        const successModalElement = document.getElementById('successModal');
                        const successModal = new bootstrap.Modal(successModalElement);
                        const timerText = document.getElementById('timerText');
                        let timeLeft = 30;
                        successModal.show();
                        const countdown = setInterval(() => {
                            timeLeft--;
                            if(timeLeft >= 0) timerText.innerText = timeLeft;
                            if(timeLeft <= 5) timerText.style.color = '#dc3545';
                            if (timeLeft <= 0) { clearInterval(countdown); successModal.hide(); }
                        }, 1000);
                        successModalElement.addEventListener('hidden.bs.modal', () => {
                            clearInterval(countdown);
                            window.history.replaceState(null, null, window.location.pathname);
                        });
                    });
                </script>
                <?php endif; ?>

                <h2 class="fw-800 h4 mb-1">Library Access Entry</h2>
                <p class="text-muted small mb-4">Please verify your identity to proceed</p>

                <div class="scanner-instruction"><i class="bi bi-qr-code-scan me-1"></i>Please scan your QR code below</div>
                <div class="scanner-viewport">
                    <div id="reader"></div>
                    <div class="scanner-mask"></div>
                    <div class="scanner-frame">
                        <div class="bracket tl"></div><div class="bracket tr"></div>
                        <div class="bracket bl"></div><div class="bracket br"></div>
                        <div class="laser"></div>
                    </div>
                    <div class="scanning-text">SCANNING QR...</div>
                    <button id="cam-switch-btn" onclick="toggleCamera()">
                        <i class="bi bi-camera-rotate-fill"></i>
                        <span>Switch Cam</span>
                    </button>
                </div>

                <div class="divider">LOGIN OPTIONS</div>
                <a href="entrynavpages/google_login.php" class="btn btn-outline-primary w-100 py-3 fw-bold shadow-sm mb-3" style="border-radius:12px;">
                    <i class="bi bi-google me-2"></i> Sign in with Google
                </a>
                <button type="button" class="btn btn-outline-secondary w-100 py-3 fw-bold shadow-sm" id="manualToggleBtn" style="border-radius:12px;">
                    <i class="bi bi-person-lock me-2"></i> Manual Login
                </button>

                <div id="manualLoginPanel" style="display:none;">
                    <form method="POST" id="loginForm" class="mt-3">
                        <input type="hidden" name="check_user" value="1">
                        <div class="mb-4">
                            <label class="small fw-bold text-muted mb-1 d-block">Student/Employee ID or Institutional Email</label>
                            <input type="text" name="user_identifier" id="user_identifier" class="form-control border-2" placeholder="Enter Credentials....." style="border-radius: 10px;">
                        </div>
                        <button type="submit" name="check_user" class="btn btn-primary w-100 py-3 fw-bold shadow-sm" style="border-radius:12px;">SUBMIT</button>
                    </form>
                </div>

                <div class="text-center pt-3 border-top mt-4">
                    <div id="clock-date" class="small fw-bold text-muted mb-1">---</div>
                    <div id="clock-time" class="h4 fw-bold text-primary mb-0" style="font-family: 'JetBrains Mono';">00:00:00</div>
                </div>
            </div>
        </div>
    </div>

    <footer>&copy; 2026 NEU Library Visitor Log System. All rights reserved.</footer>

    <?php
        $errorTitle = 'No Record Found';
        $errorMessage = 'The ID or Email you entered does not exist in our System.';
        if ($error_type === 'invalid_domain') {
            $errorTitle = 'Access Denied';
            $errorMessage = 'Only NEU institutional emails are allowed to enter the library.';
        }
    ?>
    <div class="modal fade" id="noRecordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius: 20px;">
                <div class="modal-body text-center p-5">
                    <i class="bi bi-person-x-fill text-danger" style="font-size: 4rem;"></i>
                    <h3 class="fw-800 mt-3"><?php echo $errorTitle; ?></h3>
                    <p class="text-muted"><?php echo $errorMessage; ?></p>
                    <button type="button" class="btn btn-danger w-100 py-3 fw-bold mt-3" data-bs-dismiss="modal" style="border-radius:12px;">TRY AGAIN</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] == 'blocked' && isset($_SESSION['blocked_user'])): 
        $blocked = $_SESSION['blocked_user'];
        $fullName = $blocked['firstName'] . ' ' . $blocked['lastName'];
        $blockedImg = $blocked['profile_image'] ?? '';
        $picPath = (!empty($blockedImg) && preg_match('/^https?:\\/\\//i', $blockedImg))
            ? $blockedImg
            : "profilepictures/" . $blocked['type'] . "/" . $blockedImg;
    ?>
    <div class="modal fade" id="blockedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 30px; overflow: hidden;">
                <div class="modal-header bg-danger text-white border-0 py-3 justify-content-center">
                    <h5 class="modal-title fw-800"><i class="bi bi-shield-lock-fill me-2"></i>ACCESS RESTRICTED</h5>
                </div>
                <div class="modal-body text-center p-5">
                    <div class="position-relative d-inline-block mb-4">
                        <img src="<?php echo $picPath; ?>" 
                             class="rounded-circle border border-4 border-danger shadow" 
                             style="width: 140px; height: 140px; object-fit: cover;"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($fullName); ?>&background=dc3545&color=fff'">
                        
                        <span class="position-absolute bottom-0 end-0 bg-danger rounded-circle d-flex align-items-center justify-content-center border border-4 border-white" 
                            style="width: 42px; height: 42px;">
                            <i class="bi bi-x-lg text-white" style="font-size: 1rem;"></i>
                        </span>
                    </div>
                    <h3 class="fw-800 mb-1"><?php echo strtoupper($fullName); ?></h3>
                    <p class="text-muted small fw-bold mb-3">ID: <?php echo ($blocked['studentID'] ?? $blocked['emplID']); ?></p>

                    <div class="mb-4">
                        <label class="small text-danger fw-800 d-block text-uppercase mb-1" style="letter-spacing: 1px;">Reason for Restriction</label>
                        <div class="p-3 border border-danger border-opacity-25 rounded-4 bg-danger bg-opacity-10">
                            <span class="fw-bold text-dark italic">
                                "<?php echo !empty($blocked['block_reason']) ? htmlspecialchars($blocked['block_reason']) : 'No specific reason provided.'; ?>"
                            </span>
                        </div>
                    </div>

                    <div class="bg-light rounded-4 p-3 mb-4">
                        <div class="row g-0">
                            <div class="col-6 border-end">
                                <label class="small text-muted fw-bold d-block text-uppercase">Department</label>
                                <span class="fw-bold"><?php echo $blocked['departmentID']; ?></span>
                            </div>
                            <div class="col-6">
                                <label class="small text-muted fw-bold d-block text-uppercase">Status</label>
                                <span class="text-danger fw-800">BLOCKED</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-secondary mb-4">Your library access has been suspended. Please coordinate with the <strong>Library Administration</strong> to resolve this issue.</p>
                    <button type="button" class="btn btn-dark w-100 py-3 fw-bold shadow-sm" data-bs-dismiss="modal" style="border-radius:12px;">CLOSE WINDOW</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myBlockedModal = new bootstrap.Modal(document.getElementById('blockedModal'));
            myBlockedModal.show();
            document.getElementById('blockedModal').addEventListener('hidden.bs.modal', function () {
                window.history.replaceState(null, null, window.location.pathname);
            });
        });
    </script>
    <?php unset($_SESSION['blocked_user']); endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showError(msg) {
            const toast = document.getElementById('errorToast');
            document.getElementById('toastMsg').innerText = msg;
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 4000);
        }

        let slideIndex = 0;
        const slides = document.querySelectorAll('.slide');
        const container = document.getElementById('slides');
        const dotsBox = document.getElementById('dots');
        slides.forEach((_, i) => {
            const d = document.createElement('button'); d.className = 'dot'; d.onclick = () => showSlide(i); dotsBox.appendChild(d);
        });
        function showSlide(n) {
            slideIndex = (n + slides.length) % slides.length;
            container.style.transform = `translateX(-${slideIndex * 100}%)`;
            document.querySelectorAll('.dot').forEach((d, i) => d.classList.toggle('active', i === slideIndex));
        }
        function plusSlides(n) { showSlide(slideIndex + n); }
        setInterval(() => plusSlides(1), 5000); showSlide(0);

        function updateClock() {
            const now = new Date();
            document.getElementById('clock-date').innerText = now.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: '2-digit', year: 'numeric' }).toUpperCase();
            document.getElementById('clock-time').innerText = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true });
        }
        setInterval(updateClock, 1000); updateClock();

        const html5QrCode = new Html5Qrcode("reader");
        let availableCameras = [];
        let currentCamIdx = 0;

        // MODIFIED: Added sound trigger logic
        function onScanSuccess(decodedText) {
            if (/^\d{7}$/.test(decodedText)) {
                // Play sound
                const beep = document.getElementById('scanSound');
                if (beep) {
                    beep.currentTime = 0;
                    beep.play().catch(e => console.log("Audio play blocked:", e));
                }

                const manualPanel = document.getElementById('manualLoginPanel');
                const manualToggleBtn = document.getElementById('manualToggleBtn');
                if (manualPanel && manualPanel.style.display !== 'block') {
                    manualPanel.style.display = 'block';
                    if (manualToggleBtn) {
                        manualToggleBtn.innerHTML = '<i class="bi bi-eye-slash me-2"></i> Hide Manual Login';
                    }
                }

                const identifierInput = document.getElementById('user_identifier');
                if (identifierInput) {
                    identifierInput.value = decodedText;
                    identifierInput.style.borderColor = "var(--neu-blue)";
                }
                window.history.replaceState(null, null, window.location.href);
                
                // Submit after a tiny delay so the sound can be heard
                setTimeout(() => {
                    document.getElementById('loginForm').submit();
                }, 200);
            } else { showError("Invalid QR Code format."); }
        }

        async function startScanner() {
            try {
                availableCameras = await Html5Qrcode.getCameras();
                if (availableCameras.length > 0) {
                    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
                    currentCamIdx = isMobile ? availableCameras.length - 1 : 0; 
                    await html5QrCode.start(availableCameras[currentCamIdx].id, { fps: 20, qrbox: 180 }, onScanSuccess);
                }
            } catch (err) {
                console.error(err);
                showError("Camera Error: Please check permissions.");
            }
        }

        async function toggleCamera() {
            if (availableCameras.length < 2) return;
            await html5QrCode.stop();
            currentCamIdx = (currentCamIdx + 1) % availableCameras.length;
            await html5QrCode.start(availableCameras[currentCamIdx].id, { fps: 20, qrbox: 180 }, onScanSuccess);
        }

        window.addEventListener('load', startScanner);

        const identifierInput = document.getElementById('user_identifier');
        const loginForm = document.getElementById('loginForm');
        const manualPanel = document.getElementById('manualLoginPanel');
        const manualToggleBtn = document.getElementById('manualToggleBtn');

        if (manualToggleBtn && manualPanel) {
            manualToggleBtn.addEventListener('click', () => {
                const isOpen = manualPanel.style.display === 'block';
                manualPanel.style.display = isOpen ? 'none' : 'block';
                manualToggleBtn.innerHTML = isOpen 
                    ? '<i class="bi bi-person-lock me-2"></i> Manual Login'
                    : '<i class="bi bi-eye-slash me-2"></i> Hide Manual Login';
                if (!isOpen && identifierInput) {
                    setTimeout(() => identifierInput.focus(), 50);
                }
            });
        }

        if (identifierInput && loginForm) {
            identifierInput.addEventListener('input', function() {
                const value = this.value.trim();
                if (value && /^\d+$/.test(value)) {
                    this.value = value.replace(/[^0-9]/g, '');
                }
            });

            loginForm.addEventListener('submit', function(e) {
                const value = identifierInput.value.trim();
                if (!value) {
                    e.preventDefault();
                    showError("Action required: Enter ID or Email.");
                    return;
                }
                if (/^\d+$/.test(value)) {
                    if (value.length !== 7) {
                        e.preventDefault();
                        showError("Format Error: ID must be 7 digits.");
                    }
                } else if (value.includes("@") && !value.endsWith("@neu.edu.ph")) {
                    e.preventDefault();
                    showError("Invalid Domain: Use @neu.edu.ph only.");
                }
            });
        }

        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('noRecordModal'));
            myModal.show();
        });
        <?php endif; ?>
    </script>
</body>
</html>
