<?php
session_start();
require_once '../includes/db_connect.php';

// Restrict access to logged-in admins
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit('Unauthorized access');
}

if (!isset($_POST['id']) || !isset($_POST['type'])) exit('Invalid Request');

$id = trim($_POST['id']);
$type = $_POST['type'];
if (!in_array($type, ['Student', 'Employee'], true)) {
    exit('Invalid Request');
}
$table = ($type === 'Student') ? 'students' : 'employees';
$idCol = ($type === 'Student') ? 'studentID' : 'emplID';

$stmt = $conn->prepare("SELECT * FROM $table WHERE $idCol = ?");
$stmt->bind_param("s", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$depts = $conn->query("SELECT * FROM departments ORDER BY departmentName ASC");
?>

<div class="modal-header border-0 pb-0">
    <h5 class="fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit <?= $type ?> Profile</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <form id="editUserForm" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="type" value="<?= $type ?>">

        <div class="row g-3">
            <div class="col-12 text-center mb-3">
                <label class="info-label d-block mb-2">Update Profile Picture</label>
                <input type="file" name="profile_image" id="editProfilePic" class="form-control form-control-sm shadow-sm" accept="image/*">
            </div>

            <div class="col-md-6">
                <label class="info-label">First Name</label>
                <input type="text" name="firstName" class="form-control" value="<?= htmlspecialchars($user['firstName']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="info-label">Last Name</label>
                <input type="text" name="lastName" class="form-control" value="<?= htmlspecialchars($user['lastName']) ?>" required>
            </div>
            <div class="col-12">
                <label class="info-label">Email Address (@neu.edu.ph only)</label>
                <input type="email" 
                       name="email" 
                       id="editUserEmail"
                       class="form-control" 
                       value="<?= htmlspecialchars($user['institutionalEmail']) ?>" 
                       pattern=".+@neu\.edu\.ph$"
                       title="Please provide a valid @neu.edu.ph email address"
                       required>
            </div>
            <div class="col-12">
                <label class="info-label">Department</label>
                <select name="departmentID" class="form-select" required>
                    <option value="">Select Department</option>
                    <?php while($d = $depts->fetch_assoc()): ?>
                        <option value="<?= $d['departmentID'] ?>" <?= ($d['departmentID'] == $user['departmentID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['departmentName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <?php if ($type === 'Employee'): ?>
            <div class="col-12 mt-3 pt-3 border-top">
                <label class="info-label text-primary">Security: Change Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" id="saveChangesBtn" class="btn btn-success fw-bold flex-grow-1 py-2 shadow-sm" disabled>
                <i class="bi bi-check-lg me-1"></i> SAVE CHANGES
            </button>
            <button type="button" class="btn btn-light border flex-grow-1 py-2" onclick="viewUserDetails('<?= $id ?>', '<?= $type ?>')">
                CANCEL
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    const form = document.getElementById('editUserForm');
    const saveBtn = document.getElementById('saveChangesBtn');
    const fileInput = document.getElementById('editProfilePic');

    // Function to capture current form state (excluding file and password)
    const getFormState = () => {
        const formData = new FormData(form);
        const state = {};
        formData.forEach((value, key) => {
            // We ignore the file input here and handle it separately
            if (key !== 'profile_image' && key !== 'new_password') {
                state[key] = value;
            }
        });
        return JSON.stringify(state);
    };

    // Store the original state when the modal loads
    const initialState = getFormState();

    const checkChanges = () => {
        const currentState = getFormState();
        const hasFile = fileInput.files.length > 0;
        
        // Check if password field exists and has text
        const passwordField = form.querySelector('input[name="new_password"]');
        const hasPassword = passwordField ? passwordField.value.length > 0 : false;

        // Enable button if text data changed OR a file is picked OR a password is typed
        if (currentState !== initialState || hasFile || hasPassword) {
            saveBtn.disabled = false;
        } else {
            saveBtn.disabled = true;
        }
    };

    // Listen for any typing or selection changes
    form.addEventListener('input', checkChanges);
    form.addEventListener('change', checkChanges);
})();
</script>
