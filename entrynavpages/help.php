<?php
session_start();
require_once '../includes/db_connect.php';

// Fetch today's visitor count (Matching index logic)
$count_query = $conn->query("SELECT COUNT(*) as total FROM history_logs WHERE DATE(date) = CURDATE()");
$visitor_data = $count_query->fetch_assoc();
$todays_count = $visitor_data['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support | NEU Library Visitor Log</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root { 
            --neu-blue: #0038a8; 
            --neu-red: #dc3545;
            --bg-body: #eeeeee; 
            --card-bg: #ffffff; 
            --border-color: #e2e8f0; 
        }
        
        body { 
            background-color: var(--bg-body); 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }

        /* Navbar Styling - Unified with Index */
        .navbar { 
            background: var(--card-bg); 
            border-bottom: 1px solid var(--border-color); 
            padding: 0.8rem 2rem; 
            position: sticky; 
            top: 0; 
            z-index: 1000; 
        }
        .nav-link { 
            color: #6c757d !important; 
            font-weight: 600; 
            transition: 0.2s; 
        }
        .nav-link:hover { color: var(--neu-blue) !important; }
        .nav-link.active { color: var(--neu-blue) !important; position: relative; }
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 1rem;
            right: 1rem;
            height: 3px;
        }
        .btn-admin { 
            background-color: var(--neu-blue) !important; 
            color: #fff !important; 
            border-radius: 8px; 
            font-weight: 600; 
            padding: 8px 20px; 
            text-decoration: none; 
            transition: 0.3s;
        }
        .btn-admin:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Typography */
        .fw-800 { font-weight: 800; }
        .fw-600 { font-weight: 600; }
        .text-primary { color: var(--neu-blue) !important; }

        /* Layout & Cards */
        .main-wrapper { flex: 1; padding: 40px 20px; display: flex; align-items: center; }
        .help-card { 
            background: var(--card-bg); 
            border-radius: 30px; 
            border: 1px solid var(--border-color); 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05); 
            overflow: hidden;
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
        }
        
        /* Form & Inputs */
        .form-label-custom {
            font-size: 0.75rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .form-control, .form-select {
            border-radius: 12px;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            font-size: 0.9rem;
            transition: 0.2s;
        }
        .form-control:focus {
            border-color: var(--neu-blue);
            box-shadow: none;
        }

        /* Sidebar Styling */
        .contact-sidebar { 
            background-color: #f8fafc; 
            border-left: 1px solid var(--border-color); 
            height: 100%; 
            padding: 45px; 
        }

        .accordion-item { border: 1px solid var(--border-color) !important; margin-bottom: 1rem; border-radius: 15px !important; }
        .accordion-button { border-radius: 15px !important; font-weight: 600; font-size: 0.95rem; }
        .accordion-button:not(.collapsed) { background-color: #f0f4ff; color: var(--neu-blue); }

        footer { padding: 25px; text-align: center; color: #6c757d; font-size: 0.85rem; border-top: 1px solid var(--border-color); background: #f8f9fa; }

        @media (max-width: 768px) {
            .navbar { padding: 0.7rem 1rem; }
            .btn-admin { width: 100%; text-align: center; margin-top: 10px; }
            .main-wrapper { padding: 24px 16px; }
            .contact-sidebar { padding: 24px; }
        }

        @media (max-width: 576px) {
            .help-card { border-radius: 20px; }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-md">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <img src="../assets/neu.png" alt="NEU" width="38">
                <span class="ms-2"><strong>NEU Library Visitor Log</strong></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#helpNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="helpNavbar">
                <div class="navbar-nav ms-auto align-items-center">
                    <a href="../index.php" class="nav-link px-3">Home</a>
                    <a href="qrmaker.php" class="nav-link px-3">My QR Code</a>
                    <a href="help.php" class="nav-link px-3 active">Help & Support</a>
                    <a href="../admin_dashboard/login.php" class="btn btn-admin ms-md-3">Admin Login</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-wrapper">
        <div class="help-card">
            <div class="row g-0">
                <div class="col-lg-7 p-4 p-md-5">
                    
                    <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                        <div class="alert alert-success border-0 d-flex align-items-center shadow-sm mb-4" style="border-radius: 15px;">
                            <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                            <div>
                                <strong class="d-block">Report Submitted</strong>
                                <span class="small">Our technical team has been notified.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <h2 class="fw-800 text-dark mb-1">Help Center</h2>
                    <p class="text-muted mb-4">Find answers or report technical difficulties below. <br> You could also ask the Faculty or Front Desk Staff for assistance.</p>

                    <div class="accordion accordion-flush mb-5" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                                    <i class="bi bi-qr-code-scan me-3 text-primary"></i>My QR Code won't scan
                                </button>
                            </h2>
                            <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted small">
                                    <p>Digital screens can sometimes cause glare. Try these steps:</p>
                                    <ul>
                                        <li>Set your phone brightness to <strong>100%</strong>.</li>
                                        <li>Hold your phone roughly 6 inches away from the camera.</li>
                                        <li><strong>Pro Tip:</strong> For the fastest entry, we highly recommend <strong>printing your QR code</strong> on a small card or sticking it to the back of your physical ID. Paper scans significantly faster than phone screens.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                    <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                                <i class="bi bi-person-x me-3 text-primary"></i>"No Record Found" Error
                                </button>
                            </h2>
                            <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted small">
                                    This usually means your ID number hasn't been synced to the Library Database yet. If you are a <strong>transferee or a first-year student</strong>, please allow 2 working days for your data to be processed. You may visit the Librarian's desk for manual verification in the meantime.
                                </div>
                            </div>
                        </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q3">
                <i class="bi bi-door-closed me-3 text-primary"></i>Can I enter multiple times a day?
            </button>
        </h2>
        <div id="q3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body text-muted small">
                Currently, the system is designed to log <strong>one primary entry per day</strong> for statistical purposes. If you leave the library for a short break and return, you do not need to scan again - simply show your previous entry confirmation or your ID to the guard on duty.
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q4">
                <i class="bi bi-question-circle me-3 text-primary"></i>What if I forgot my QR code?
            </button>
        </h2>
        <div id="q4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body text-muted small">
                No problem! You can still log in using the <strong>Manual Entry</strong> section on the Home page. Just type in your 7-digit Student/Employee ID or your Institutional Email address (`@neu.edu.ph`) to proceed.
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q5">
                <i class="bi bi-shield-lock me-3 text-primary"></i>Is my data kept private?
            </button>
        </h2>
        <div id="q5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body text-muted small">
                Yes. We only store your ID and the timestamp of your arrival. Your personal data is protected under the <strong>Data Privacy Act</strong> and is only used by the University to monitor library congestion and improve facility services.
            </div>
        </div>
    </div>
</div>

                    <div class="p-4 rounded-4 bg-light border border-2 border-dashed">
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-primary text-white rounded-3 p-2 me-3">
                                <i class="bi bi-tools"></i>
                            </div>
                            <h6 class="fw-800 mb-0">Report a Technical Issue</h6>
                        </div>

                        <form action="send_report.php" method="POST">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label-custom">Student / Employee ID</label>
                                    <input type="text" name="userID" class="form-control" placeholder="e.g. 2101234" required maxlength="7">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label-custom">Issue Category</label>
                                    <select name="issue_type" class="form-select">
                                        <option value="Scanner Issue">Scanner Not Responding</option>
                                        <option value="Database Error">Account Not Found</option>
                                        <option value="QR Issue">QR Generation Error</option>
                                        <option value="Other">Other Concerns</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label-custom">Detailed Description</label>
                                    <textarea name="message" class="form-control" rows="3" placeholder="Please describe what happened..." required></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-800 mt-4 py-3 shadow-sm" style="border-radius: 12px;">
                                SUBMIT SUPPORT TICKET
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="contact-sidebar">
                        <h5 class="fw-800 mb-4">Library Support</h5>
                        
                        <div class="mb-4">
                            <label class="form-label-custom d-block">Location</label>
                            <div class="d-flex align-items-start">
                                <i class="bi bi-geo-alt-fill text-primary me-3 mt-1"></i>
                                <span class="fw-600 small text-dark">Main Library, 
New Era University Main Campus, No. 9, Central Avenue, Barangay New Era, Quezon City, Philippines, 1107</span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-custom d-block">Official Email</label>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-envelope-at-fill text-primary me-3"></i>
                                <span class="fw-600 small text-dark">rhyianjoshua.ticbobolan@neu.edu.ph</span>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label-custom d-block">Service Hours</label>
                            <div class="d-flex align-items-start">
                                <i class="bi bi-clock-fill text-primary me-3 mt-1"></i>
                                <div>
                                    <div class="fw-600 small text-dark">Mon - Fri: 8:00 AM - 7:00 PM</div>
                                    <div class="text-muted" style="font-size: 0.7rem;">Strictly no entry during weekends.</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-white rounded-4 border shadow-sm">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                        <i class="bi bi-graph-up-arrow fs-5"></i>
                                    </div>
                                </div>
                                <div class="ms-3">
                                    <div class="text-muted x-small fw-800 text-uppercase">Today's Visitors</div>
                                    <div class="fw-800 h4 mb-0 text-primary"><?php echo $todays_count; ?> <span class="text-muted fs-6 fw-600">Visitors</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>&copy; <?php echo date('Y'); ?> NEU Library Visitor Log System. All rights reserved.</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
