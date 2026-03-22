<?php
session_start();
// STEP 1: Fix path to db_connect
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['pending_google_user'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$pending_user = $_SESSION['pending_google_user'] ?? null;

// Default values if user is not found at all
$full_name = "User Not Found";
$dept_display = "N/A";
$role_display = "N/A";
$base64_img = ""; 

$departments = [];
$dept_result = $conn->query("SELECT departmentID, departmentName FROM departments ORDER BY departmentName ASC");
if ($dept_result) {
    while ($d = $dept_result->fetch_assoc()) {
        $departments[] = $d;
    }
}

function strip_middle_initials_local($name) {
    $parts = preg_split('/\s+/', trim($name));
    $filtered = [];
    foreach ($parts as $part) {
        $clean = trim($part);
        if ($clean === '') continue;
        if (preg_match('/^[A-Za-z]\.?$/', $clean)) {
            continue;
        }
        $filtered[] = $clean;
    }
    return !empty($filtered) ? implode(' ', $filtered) : trim($name);
}

function guess_role_from_email_local($email) {
    $email = strtolower(trim($email));
    if ($email === '' || !str_contains($email, '@')) {
        return 'Student';
    }
    $local = explode('@', $email)[0] ?? '';
    if (str_contains($local, '.')) {
        return 'Student';
    }
    if (preg_match('/^[a-z]{1,3}[a-z]+$/', $local)) {
        return 'Faculty/Admin';
    }
    return 'Student';
}

if ($user_id !== null) {
    $sql = "SELECT firstName, lastName, full_name, deptName as dept, p_img, role FROM (
                SELECT s.firstName, s.lastName, CONCAT(s.firstName, ' ', s.lastName) as full_name, 
                       d.departmentName as deptName, 
                       s.profile_image as p_img, 
                       s.studentID as id, 
                       'Student' as role 
                FROM students s
                LEFT JOIN departments d ON s.departmentID = d.departmentID
                UNION 
                SELECT e.firstName, e.lastName, CONCAT(e.firstName, ' ', e.lastName) as full_name, 
                       d.departmentName as deptName, 
                       e.profile_image as p_img, 
                       e.emplID as id, 
                       e.role as role 
                FROM employees e
                LEFT JOIN departments d ON e.departmentID = d.departmentID
            ) AS users WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // FIX: Using ?? "" inside trim() prevents the Deprecated error in PHP 8.1+
        $full_name = !empty(trim($row['full_name'] ?? "")) ? $row['full_name'] : "N/A";
        $dept_display = !empty(trim($row['dept'] ?? "")) ? $row['dept'] : "N/A";
        $role_display = !empty(trim($row['role'] ?? "")) ? $row['role'] : "N/A";

        // 2. Folder Mapping
        $role_lower = strtolower($role_display);
        if ($role_lower === 'student') {
            $folder = 'student';
        } else {
            // Employees map to the admin folder (no "employee" folder exists).
            $folder = 'admin';
        }

        // 3. Resolve Image Path
        $localPath = __DIR__ . "/../profilepictures/" . $folder . "/" . ($row['p_img'] ?? "");
        
        if (!empty($row['p_img']) && file_exists($localPath)) {
            $type = pathinfo($localPath, PATHINFO_EXTENSION);
            $data = @file_get_contents($localPath);
            $base64_img = 'data:image/' . $type . ';base64,' . base64_encode($data);
        } else if (!empty($row['p_img']) && preg_match('/^https?:\\/\\//i', $row['p_img'])) {
            $base64_img = $row['p_img'];
        } else {
            // Initials fallback if no image exists or file is missing
            $fName = $row['firstName'] ?? "U";
            $lName = $row['lastName'] ?? "N";
            $initialsName = urlencode($fName . ' ' . $lName);
            $base64_img = 'https://ui-avatars.com/api/?name=' . $initialsName . '&background=0038a8&color=fff&bold=true';
        }
    }
} elseif ($pending_user) {
    $prefill_first = strip_middle_initials_local($pending_user['first_name'] ?? '');
    $prefill_last = strip_middle_initials_local($pending_user['last_name'] ?? '');
    $role_guess = guess_role_from_email_local($pending_user['email'] ?? '');
    $full_name = trim($prefill_first . ' ' . $prefill_last);
    $full_name = $full_name !== '' ? $full_name : 'New User';
    $dept_display = "Not set";
    $role_display = "Not set";
    if (!empty($pending_user['picture'])) {
        $base64_img = $pending_user['picture'];
    } else {
        $initialsName = urlencode($full_name);
        $base64_img = 'https://ui-avatars.com/api/?name=' . $initialsName . '&background=0038a8&color=fff&bold=true';
    }
}

header("Cache-Control: no-cache, no-store, must-revalidate");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Entry Form - NEU Library</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --neu-blue: #0038a8; --bg-gray: #f0f2f5; --text-dark: #1a1a1a; }
        body { background-color: var(--bg-gray); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-dark); margin: 0; }
        .navbar { background: white; border-bottom: 2px solid var(--neu-blue); padding: 0.6rem 2rem; }
        .logo-img { height: 45px; width: auto; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .avatar-circle { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #e2e8f0; overflow: hidden; background: #eee; }
        .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .btn-logout { background: #f0f4ff; color: var(--neu-blue); border: 1px solid #dbeafe; border-radius: 8px; font-weight: 700; padding: 6px 16px; font-size: 0.85rem; text-decoration: none; transition: 0.2s; }
        .btn-logout:hover { background: var(--neu-blue); color: white; }
        .main-container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .section-card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; }
        .card-header-custom { background: #f8faff; padding: 15px 24px; border-bottom: 1px solid #edf2f7; display: flex; align-items: center; gap: 10px; }
        .card-header-custom i { color: var(--neu-blue); }
        .label-title { font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px; }
        .data-text { font-size: 1.1rem; font-weight: 700; color: #0f172a; }
        .form-select, .form-control { border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px; transition: 0.3s; }
        .submit-btn { background: var(--neu-blue); color: white; border: none; border-radius: 12px; padding: 16px 32px; font-weight: 700; display: inline-flex; align-items: center; gap: 10px; transition: 0.3s; width: 100%; justify-content: center; }
        footer { text-align: center; color: #94a3b8; font-size: 0.8rem; margin-top: 40px; padding-bottom: 30px; }

        @media (max-width: 768px) {
            .navbar { padding: 0.6rem 1rem; }
            .main-container { margin: 24px auto; }
            .card-header-custom { padding: 12px 16px; }
            .section-card .card-body { padding: 20px !important; }
            .submit-btn { padding: 14px 16px; }
        }

        @media (max-width: 576px) {
            .main-container { padding: 0 14px; }
            .logo-img { height: 38px; }
            .user-profile { gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
            .btn-logout { padding: 6px 12px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <img src="../assets/neu.png" alt="NEU Logo" class="logo-img">
                <span class="fw-bold d-none d-sm-inline" style="color: var(--neu-blue); letter-spacing: -0.5px;">Visitor Entry Form</span>
            </div>
            <div class="user-profile">
                <div class="text-end d-none d-md-block">
                    <div class="fw-bold lh-1" style="font-size: 0.9rem;">
                        <?php echo htmlspecialchars($full_name ?: 'N/A'); ?>
                    </div>
                    <small class="text-muted" style="font-size: 0.75rem;">Active Session</small>
                </div>
                <div class="avatar-circle">
                    <img src="<?php echo $base64_img; ?>" alt="Profile">
                </div>
                <a href="../index.php" class="btn btn-logout">Cancel</a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <h2 class="fw-800 mb-2" style="font-size: 1.8rem; color: var(--neu-blue);">Entry Confirmation</h2>
        <p class="text-muted mb-4">Confirm your identity and the purpose of your visit.</p>

        <form action="process_entry.php" method="POST">
            <div class="section-card">
                <div class="card-header-custom">
                    <i class="bi bi-person-badge-fill"></i>
                    <span class="fw-bold small text-uppercase">Profile Information</span>
                </div>
                <div class="card-body p-4">
                    <?php if ($pending_user): ?>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="label-title d-block">First Name</label>
                            <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($prefill_first ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="label-title d-block">Last Name</label>
                            <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($prefill_last ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="label-title d-block">Role</label>
                                <select name="role_choice" class="form-select" required>
                                    <option value="" disabled selected>Select role...</option>
                                    <option value="Student" <?php echo ($role_guess === 'Student') ? 'selected' : ''; ?>>Student</option>
                                    <option value="Faculty/Admin" <?php echo ($role_guess === 'Faculty/Admin') ? 'selected' : ''; ?>>Faculty/Admin</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="label-title d-block">Department</label>
                                <select name="department_id" class="form-select" required>
                                    <option value="" disabled selected>Select department...</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept['departmentID']); ?>">
                                            <?php echo htmlspecialchars($dept['departmentName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="label-title">Full Name</div>
                                <div class="data-text"><?php echo htmlspecialchars($full_name ?: 'N/A'); ?></div>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="label-title">Role</div>
                                <div class="data-text"><?php echo htmlspecialchars($role_display ?: 'N/A'); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="label-title">Department</div>
                                <div class="data-text"><?php echo htmlspecialchars($dept_display ?: 'N/A'); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-card p-4">
                <div class="mb-4">
                    <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-ui-checks text-primary"></i> Visit Purpose
                    </label>
                    <select name="reason" id="reasonSelect" class="form-select" required>
                        <option value="" selected disabled>Select a reason...</option>
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

                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <label class="fw-bold d-flex align-items-center gap-2">
                            <i class="bi bi-chat-left-text text-primary"></i> Other Reason
                        </label>
                        <span class="text-muted small" id="counterWrapper" style="display:none;">
                            <span id="charCount" class="fw-bold">255</span> left
                        </span>
                    </div>
                    <textarea name="specific_reason" id="specificReason" class="form-control" rows="3" 
                              placeholder="Type your specific reason here..." 
                              maxlength="255" disabled></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    Confirm & Enter Library <i class="bi bi-check2-circle"></i>
                </button>
            </div>
        </form>

        <footer>&copy; 2026 New Era University Library.</footer>
    </div>

    <script>
        const reasonSelect = document.getElementById('reasonSelect');
        const specificReason = document.getElementById('specificReason');
        const counterWrapper = document.getElementById('counterWrapper');

        reasonSelect.addEventListener('change', function() {
            if (this.value === 'Others') {
                specificReason.disabled = false;
                specificReason.required = true;
                counterWrapper.style.display = 'inline';
                specificReason.focus();
            } else {
                specificReason.disabled = true;
                specificReason.required = false;
                specificReason.value = '';
                counterWrapper.style.display = 'none';
            }
        });
    </script>
</body>
</html>
