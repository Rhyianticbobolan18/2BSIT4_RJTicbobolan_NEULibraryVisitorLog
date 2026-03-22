<?php
session_start();
require_once '../includes/db_connect.php'; 

$error = "";
$entered_identifier = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']); 
    $password = $_POST['password'];
    
    // Keep identifier for field persistence
    $entered_identifier = htmlspecialchars($identifier); 

    // 1. Check if account exists in employees table
    $sql = "SELECT emplID, firstName, lastName, password, role, status, is_admin_approved FROM employees 
            WHERE institutionalEmail = ? OR emplID = ? LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        
        // 2. STATUS CHECK: Block access if status is 'Blocked'
        if (strcasecmp($user['status'], 'Blocked') === 0) {
            $error = "Your account is currently blocked. Please contact the Super Admin.";
            $entered_identifier = ""; 
        } 
        // 3. Role Check (Admin-only)
        else if (strcasecmp($user['role'], 'Faculty/Admin') === 0) {
            
            // 4. Password Check (PLAIN TEXT comparison)
            if ($password === $user['password']) {
                if (empty($user['is_admin_approved'])) {
                    $error = "Admin access pending approval. Please contact the Super Admin.";
                    $entered_identifier = "";
                } else {
                session_regenerate_id(true);
                
                // Set Session Variables
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['emplID'] = $user['emplID']; 
                $_SESSION['admin_name'] = $user['firstName'] . ' ' . $user['lastName'];
                
                // --- THE ONE-TIME WELCOME FLAG ---
                $_SESSION['show_welcome'] = true; 
                
                // Redirect to dashboard
                header("Location: index.php"); 
                exit();
                }
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "Access Denied. This portal is for Administrators only.";
            $entered_identifier = ""; 
        }
    } else {
        // 5. Check if student (to provide helpful error message)
        $checkStudent = "SELECT studentID FROM students WHERE institutionalEmail = ? OR studentID = ? LIMIT 1";
        $stmt2 = $conn->prepare($checkStudent);
        $stmt2->bind_param("ss", $identifier, $identifier);
        $stmt2->execute();
        $res2 = $stmt2->get_result();

        if ($res2->num_rows > 0) {
            $error = "Access Denied. Student accounts cannot access the Admin Portal.";
            $entered_identifier = ""; 
        } else {
            $error = "Account not recognized. Please check your ID or Email.";
            $entered_identifier = ""; 
        }
    }
}

if (isset($_GET['google_error'])) {
    $error = urldecode($_GET['google_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | NEU Library</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --neu-blue: #0038a8; --neu-hover: #002a80; }
        .background-radial-gradient {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background-color: hsl(218, 41%, 15%);
            background-image: radial-gradient(650px circle at 0% 0%, hsl(218, 41%, 35%) 15%, hsl(218, 41%, 30%) 35%, hsl(218, 41%, 20%) 75%, hsl(218, 41%, 19%) 80%, transparent 100%),
                             radial-gradient(1250px circle at 100% 100%, hsl(218, 41%, 45%) 15%, hsl(218, 41%, 30%) 35%, hsl(218, 41%, 20%) 75%, hsl(218, 41%, 19%) 80%, transparent 100%);
            overflow: hidden; position: relative; padding: 20px;
        }
        .login-wrapper {
            width: 100%; max-width: 850px; display: flex; background: hsla(0, 0%, 100%, 0.9) !important;
            backdrop-filter: saturate(200%) blur(25px); border-radius: 20px; overflow: hidden; z-index: 10;
        }
        .brand-side-img { flex: 1; background-image: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.2)), url('../assets/banner.png'); background-size: cover; background-position: center; }
        .form-side { flex: 1; padding: 40px 50px; display: flex; flex-direction: column; justify-content: center; }
        .logo-box { background-color: #fff; width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .header-title { color: var(--neu-blue); font-weight: 800; font-size: 1.75rem; }
        .input-group { background-color: #f1f5f9; border-radius: 10px; padding: 2px 10px; border: 2px solid transparent; transition: 0.2s; }
        .input-group:focus-within { border-color: var(--neu-blue); background-color: #fff; }
        .form-control { background: transparent; border: none; padding: 10px; font-weight: 500; }
        .form-control:focus { box-shadow: none; background: transparent; }
        .btn-login { background-color: var(--neu-blue); color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; transition: 0.3s; margin-top: 10px; }
        .btn-login:hover { background-color: var(--neu-hover); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0, 56, 168, 0.3); }
        .toggle-password { border: none; background: transparent; color: #64748b; }
        #radius-shape-1 { height: 220px; width: 220px; top: -60px; left: -130px; background: radial-gradient(#44006b, #ad1fff); position: absolute; border-radius: 50%; }
        #radius-shape-2 { border-radius: 38% 62% 63% 37% / 70% 33% 67% 30%; bottom: -60px; right: -110px; width: 300px; height: 300px; background: radial-gradient(#44006b, #ad1fff); position: absolute; }
        .google-section { transition: margin-top 0.2s ease; }
        .manual-open .google-section { margin-top: 8px; }
        .or-divider { margin: 8px 0; }
        .or-divider { display: none; }
        .manual-open .or-divider--open { display: block; }
        .manual-open .or-divider--closed { display: none; }
          .or-divider--closed { display: block; }
          @media (max-width: 768px) {
              .login-wrapper { max-width: 560px; }
              .form-side { padding: 32px 24px; }
          }
          @media (max-width: 480px) {
              .form-side { padding: 24px 18px; }
              .logo-box { width: 60px; height: 60px; }
              .header-title { font-size: 1.5rem; }
          }
      </style>
</head>
<body>

<section class="background-radial-gradient">
    <div id="radius-shape-1"></div>
    <div id="radius-shape-2"></div>

    <div class="login-wrapper shadow-lg">
        <div class="brand-side-img d-none d-md-flex"></div>

        <div class="form-side text-center" id="loginPane">
            <div class="logo-box">
                <img src="../assets/neu.png" alt="NEU Logo" style="height: 45px;">
            </div>

            <div class="mb-4">
                <h2 class="header-title">Admin Portal</h2>
                <p class="text-muted small">Library Management System</p>
            </div>

            <?php if(!empty($error)): ?>
                <div class="alert alert-danger py-2 border-0 small mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div id="googleBlock">
                <div class="text-center my-3 google-section or-divider or-divider--open" id="googleSectionOpen">
                    <div class="small text-muted">or</div>
                </div>
                <div class="d-grid gap-2">
                    <a href="google_login.php" class="btn btn-outline-primary w-100 fw-bold google-section">
                        <i class="bi bi-google me-2"></i> Sign in with Google
                    </a>
                </div>
                <div class="text-center my-3 google-section or-divider or-divider--closed" id="googleSection">
                    <div class="small text-muted">or</div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="button" class="btn btn-outline-secondary w-100 fw-bold" id="manualToggleBtn">
                    <i class="bi bi-person-lock me-2"></i> Or manually log in
                </button>
            </div>

            <form method="POST" action="login.php" class="text-start mt-3" id="manualLoginForm" style="display: none;">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase" style="color: #4a5568;">Admin ID / Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0"><i class="bi bi-person-fill text-primary"></i></span>
                        <input type="text" name="identifier" class="form-control" placeholder="Institutional ID" value="<?php echo $entered_identifier; ?>" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase" style="color: #4a5568;">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0"><i class="bi bi-lock-fill text-primary"></i></span>
                        <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter Your Password" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                            <i class="bi bi-eye-slash" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login w-100">
                    Sign In <i class="bi bi-box-arrow-in-right ms-2"></i>
                </button>
            </form>

            <a href="../index.php" class="text-decoration-none mt-4 small fw-bold text-muted">
                <i class="bi bi-arrow-left me-1"></i> Return to Main Page
            </a>
        </div>
    </div>
</section>

<script>
    function togglePasswordVisibility() {
        const passwordField = document.getElementById('passwordField');
        const toggleIcon = document.getElementById('toggleIcon');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.classList.replace('bi-eye-slash', 'bi-eye');
        } else {
            passwordField.type = 'password';
            toggleIcon.classList.replace('bi-eye', 'bi-eye-slash');
        }
    }

    const manualToggleBtn = document.getElementById('manualToggleBtn');
    const manualLoginForm = document.getElementById('manualLoginForm');
    const loginPane = document.getElementById('loginPane');
    const googleBlock = document.getElementById('googleBlock');
    if (manualToggleBtn && manualLoginForm && loginPane && googleBlock) {
        manualToggleBtn.addEventListener('click', () => {
            const isHidden = manualLoginForm.style.display === 'none';
            manualLoginForm.style.display = isHidden ? 'block' : 'none';
            if (loginPane) loginPane.classList.toggle('manual-open', isHidden);
            manualToggleBtn.innerHTML = isHidden
                ? '<i class="bi bi-x-circle me-2"></i> Hide manual login'
                : '<i class="bi bi-person-lock me-2"></i> Or manually log in';
            if (isHidden) {
                loginPane.insertBefore(googleBlock, manualLoginForm.nextSibling);
            } else {
                loginPane.insertBefore(googleBlock, manualToggleBtn.parentElement);
            }
        });
    }
</script>

</body>
</html>
