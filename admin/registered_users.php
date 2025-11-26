<?php 
session_start();

require_once '../config/admin_access.php';

if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["isAdmin"]) || $_SESSION["isAdmin"] !== true) {
    header("Location: index.php");
    exit();
}

// Prevent doctors and nurses from accessing this page
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['doctor', 'nurse'])) {
    $_SESSION['error_message'] = "You do not have access to this page.";
    if ($_SESSION['role'] === 'doctor') {
        header("Location: patient_history.php");
    } else {
        header("Location: admin.php");
    }
    exit();
}


require_once '../config/database.php';
require_once '../config/functions.php';
?>
    <?php require_once '../includes/header.php'; outputHeader('Registered Users'); ?>
    <style>
    
        .page-title { text-align:center; color:#003366; margin:24px 0 12px; font-size:2rem }
        .controls { max-width:1200px; margin:16px auto; padding:0 20px; display:flex; gap:10px; align-items:center }
        .search-input { flex:1; padding:8px 12px; border:1px solid #ccc; border-radius:4px }
        .users-grid { display:grid; grid-template-columns: repeat(auto-fill,minmax(320px,1fr)); gap:16px; padding:16px; max-width:1400px; margin:0 auto }
       
        .user-card { display:flex; flex-direction:column; background: linear-gradient(180deg,#ffffff 0%, #f8fbff 100%); border-radius:10px; padding:16px; box-shadow:0 6px 18px rgba(3,45,85,0.06); transition: transform 0.12s, box-shadow 0.12s; overflow:hidden }
        .user-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(3,45,85,0.10) }
        .user-header { display:flex; gap:12px; align-items:flex-start; margin-bottom:12px; }
        .user-avatar { flex:0 0 48px; width:48px; height:48px; border-radius:10px; background:linear-gradient(135deg,#0077cc,#008fa3); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px }
        .user-info { flex:1; min-width:0 }
        .user-name { font-size:0.95rem; font-weight:700; color:#003366; margin-bottom:2px; text-transform:lowercase }
        .user-role { display:block; font-size:0.75rem; color:#0b6b6b; font-weight:600; }
        .user-details { display:block; color:#334155; font-size:0.85rem; margin-bottom:12px; }
        .detail-item { display:flex; gap:6px; margin-bottom:3px; color:#374151 }
        .stat-label { font-weight:600; color:#003366; min-width:70px; }
        .user-stats { display:flex; flex-direction:row; gap:8px; margin-bottom:12px; justify-content:space-between; }
        .stat-item { background:#f0f9ff; border:1px solid #bfdbfe; padding:8px 12px; border-radius:6px; text-align:center; flex:1; }
        .stat-value { font-weight:700; color:#003366; font-size:0.95rem; }
        .stat-item-label { font-size:0.7rem; color:#0b6b6b; font-weight:600; }
        .last-visit { padding-top:12px; border-top:1px solid #e5e7eb; margin-bottom:12px; }
        .action-buttons { display:flex; flex-direction:column; gap:8px; align-items:stretch; }
        .view-history-btn { background:linear-gradient(90deg,#0aa3a3,#0077cc); color:white; padding:10px 12px; border-radius:8px; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px; font-weight:700; font-size:0.9rem; border:none; cursor:pointer; }
        .view-history-btn:hover { filter:brightness(0.97) }
        .reset-password-btn { background:linear-gradient(90deg,#6b7280,#374151); color:white; padding:10px 12px; border-radius:8px; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px; font-weight:700; font-size:0.9rem; border:none; cursor:pointer; }
        .reset-password-btn:hover { filter:brightness(0.97) }
        .unlock-btn { background: linear-gradient(90deg,#ff6b6b,#ff3b3b); }
        .edit-btn { background:linear-gradient(90deg,#f59e0b,#f97316); color:white; padding:10px 12px; border-radius:8px; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:8px; font-weight:700; font-size:0.9rem; border:none; cursor:pointer; }
        .edit-btn:hover { filter:brightness(0.97) }
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5); }
        .modal.show { display:flex; align-items:center; justify-content:center; }
        .modal-content { background-color:#fff; padding:24px; border-radius:10px; box-shadow:0 10px 40px rgba(0,0,0,0.2); width:90%; max-width:600px; max-height:90vh; overflow-y:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e5e7eb; padding-bottom:12px; }
        .modal-header h2 { margin:0; color:#003366; }
        .modal-close { background:none; border:none; font-size:24px; cursor:pointer; color:#6b7280; }
        .modal-close:hover { color:#003366; }
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-weight:600; color:#003366; margin-bottom:6px; font-size:0.9rem; }
        .form-group input, .form-group select { width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; font-size:0.9rem; }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:#0077cc; box-shadow:0 0 0 3px rgba(0,119,204,0.1); }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .modal-buttons { display:flex; gap:12px; justify-content:flex-end; margin-top:24px; padding-top:16px; border-top:1px solid #e5e7eb; }
        .modal-buttons button { padding:10px 16px; border-radius:6px; font-weight:600; border:none; cursor:pointer; }
        .cancel-btn { background:#e5e7eb; color:#374151; }
        .cancel-btn:hover { background:#d1d5db; }
        .save-btn { background:linear-gradient(90deg,#0077cc,#0aa3a3); color:white; }
        .save-btn:hover { filter:brightness(0.97); }
        @media (max-width:720px) { .users-grid { grid-template-columns: 1fr; } .user-header { flex-direction:row; } .user-stats { flex-direction:row; } .stat-item { padding:6px 8px; font-size:0.8rem; } .form-row { grid-template-columns:1fr; } .modal-content { width:95%; } }
    </style>

<?php
// Populate $users so the page doesn't warn; include aggregates used in the UI
$users = [];
$error_message = null;
$query = "SELECT 
    u.*, u.failed_login_count, u.last_failed_login, u.locked_until, u.is_locked,
    COUNT(DISTINCT a.id) as total_appointments,
    COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_appointments,
    COUNT(DISTINCT CASE WHEN a.status = 'approved' THEN a.id END) as approved_appointments,
    COUNT(DISTINCT CASE WHEN a.status = 'cancelled' THEN a.id END) as cancelled_appointments,
    MAX(a.appointment_date) as last_appointment,
    COUNT(DISTINCT pr.id) as has_medical_record,
    CASE WHEN u.birthday IS NOT NULL THEN TIMESTAMPDIFF(YEAR, u.birthday, CURDATE()) ELSE NULL END as calculated_age
    FROM users u
    LEFT JOIN appointments a ON u.id = a.user_id
    LEFT JOIN patient_records pr ON u.id = pr.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC";

$res = mysqli_query($conn, $query);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $users[] = $row;
    }
} else {
    $error_message = 'Error fetching users: ' . mysqli_error($conn);
}
?>
    </head>
    <body>
    <?php include '../includes/tailwind_nav.php'; ?>

    <h1 class="page-title">Registered Users</h1>

   
    <div class="controls">
        <input type="text" id="searchInput" class="search-input" placeholder="Search users...">
    </div>

    <?php if (isset($error_message)): ?>
        <div class="error-message"><?= htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

  
    <div class="users-grid">
        <?php foreach ($users as $user): 
            $dname = strtolower(trim($user['full_name'] ?? ''));
            $demail = strtolower(trim($user['email'] ?? ''));
            $dphone = strtolower(trim($user['phone'] ?? ''));
            $daddr = strtolower(trim($user['address'] ?? ''));
            $ddept = strtolower(trim($user['department'] ?? ''));
        ?>
            <div class="user-card" data-name="<?= htmlspecialchars($dname) ?>" data-email="<?= htmlspecialchars($demail) ?>" data-phone="<?= htmlspecialchars($dphone) ?>" data-address="<?= htmlspecialchars($daddr) ?>" data-department="<?= htmlspecialchars($ddept) ?>">
                <div class="user-left">
                    <div class="user-header">
                        <div class="user-avatar"><?= strtoupper(substr($user['full_name'] ?? '', 0, 1)); ?></div>
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($user['full_name'] ?? ''); ?></div>
                            <?php $role = strtolower($user['role'] ?? '');
                            if (in_array($role, ['student','employee'])): ?>
                                <span class="user-role"><?= htmlspecialchars(ucfirst($role)); ?></span>
                            <?php else: ?>
                                <span class="user-role"><?= htmlspecialchars(ucfirst($user['role'] ?? 'User')); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="user-details">
                        <div class="detail-item">
                            <span class="stat-label">Gmail: </span>
                            <?= htmlspecialchars($user['email'] ?? ''); ?>
                        </div>
                        <div class="detail-item">
                            <span class="stat-label">Phone:</span>
                            <?= htmlspecialchars($user['phone'] ?? 'Not specified'); ?>
                        </div>
                        <div class="detail-item">
                            <span class="stat-label">Address: </span>
                            <?= htmlspecialchars($user['address'] ?? 'Not specified'); ?>
                        </div>
                        <div class="detail-item">
                            <span class="stat-label">Dept:</span>
                            <?= htmlspecialchars($user['department'] ?? 'N/A'); ?>
                        </div>
                        <div class="detail-item">
                            <span class="stat-label">Gender: </span>
                            <?= htmlspecialchars($user['sex'] ?? 'Not specified'); ?>
                        </div>
                        <div class="detail-item">
                            <span class="stat-label">Birthday: </span>
                            <?= ($user['birthday'] && $user['birthday'] != '0000-00-00') ? date('M j, Y', strtotime($user['birthday'])) . ' (Age: ' . $user['calculated_age'] . ')' : 'Not specified'; ?>
                        </div>
                        <div class="detail-item">
                            <span class="stat-label">Special Status: </span>
                            <?= isset($user['special_status']) && $user['special_status'] !== 'none' ? strtoupper(htmlspecialchars($user['special_status'])) : 'None'; ?>
                        </div>
                        <div class="detail-item">
                            <span class="stat-label">Student ID: </span>
                            <?= htmlspecialchars($user['student_number'] ?? 'Not specified'); ?>
                        </div>
                    </div>
                </div>

                <?php if (in_array(strtolower($user['role'] ?? ''), ['student','employee'])): ?>
                    <div class="user-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= $user['total_appointments']; ?></div>
                            <div class="stat-item-label">Total</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $user['completed_appointments']; ?></div>
                            <div class="stat-item-label">Completed</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $user['approved_appointments']; ?></div>
                            <div class="stat-item-label">Approved</div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <?php
                        $is_locked = (!empty($user['is_locked']) && $user['is_locked']) || (!empty($user['locked_until']) && strtotime($user['locked_until']) > time());
                        if ($is_locked):
                    ?>
                        <form method="post" action="admin_unlock_user.php" style="display:flex;width:100%;">
                            <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                            <button type="submit" class="view-history-btn unlock-btn">Unlock Account</button>
                        </form>
                    <?php else: ?>
                        <a href="patient_history.php?user_id=<?= $user['id']; ?>" class="view-history-btn">View History</a>
                        <a href="reset_user_password.php?user_id=<?= $user['id']; ?>" class="reset-password-btn">Reset Password</a>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <button type="button" class="edit-btn" data-edit-id="<?= $user['id']; ?>" data-edit-first="<?= htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES); ?>" data-edit-last="<?= htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES); ?>" data-edit-email="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES); ?>" data-edit-phone="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES); ?>" data-edit-sex="<?= htmlspecialchars($user['sex'] ?? '', ENT_QUOTES); ?>" data-edit-birthday="<?= htmlspecialchars($user['birthday'] ?? '', ENT_QUOTES); ?>" data-edit-dept="<?= htmlspecialchars($user['department'] ?? '', ENT_QUOTES); ?>" data-edit-addr="<?= htmlspecialchars($user['address'] ?? '', ENT_QUOTES); ?>" data-edit-student="<?= htmlspecialchars($user['student_number'] ?? '', ENT_QUOTES); ?>" data-edit-status="<?= htmlspecialchars($user['special_status'] ?? 'none', ENT_QUOTES); ?>">Edit Info</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Edit Patient Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Patient Information</h2>
                <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editForm" method="POST" action="edit_patient_info.php">
                <input type="hidden" name="user_id" id="editUserId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editFirstName">First Name</label>
                        <input type="text" id="editFirstName" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editLastName">Last Name</label>
                        <input type="text" id="editLastName" name="last_name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input type="email" id="editEmail" name="email" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="editPhone">Phone</label>
                        <input type="tel" id="editPhone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="editGender">Gender</label>
                        <select id="editGender" name="sex">
                            <option value="">Not specified</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="editBirthday">Birthday</label>
                        <input type="date" id="editBirthday" name="birthday">
                    </div>
                    <div class="form-group">
                        <label for="editDepartment">Department</label>
                        <input type="text" id="editDepartment" name="department">
                    </div>
                </div>

                <div class="form-group">
                    <label for="editAddress">Address</label>
                    <input type="text" id="editAddress" name="address">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="editStudentNumber">Student ID</label>
                        <input type="text" id="editStudentNumber" name="student_number">
                    </div>
                    <div class="form-group">
                        <label for="editSpecialStatus">Special Status</label>
                        <select id="editSpecialStatus" name="special_status">
                            <option value="none">None</option>
                            <option value="pwd">PWD</option>
                            <option value="senior">Senior</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="save-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(userId, userData) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editFirstName').value = userData.first_name || '';
            document.getElementById('editLastName').value = userData.last_name || '';
            document.getElementById('editEmail').value = userData.email || '';
            document.getElementById('editPhone').value = userData.phone || '';
            document.getElementById('editGender').value = userData.sex || '';
            document.getElementById('editBirthday').value = userData.birthday || '';
            document.getElementById('editDepartment').value = userData.department || '';
            document.getElementById('editAddress').value = userData.address || '';
            document.getElementById('editStudentNumber').value = userData.student_number || '';
            document.getElementById('editSpecialStatus').value = userData.special_status || 'none';
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }

        // Event delegation for edit buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-btn')) {
                const btn = e.target;
                const userData = {
                    first_name: btn.dataset.editFirst,
                    last_name: btn.dataset.editLast,
                    email: btn.dataset.editEmail,
                    phone: btn.dataset.editPhone,
                    sex: btn.dataset.editSex,
                    birthday: btn.dataset.editBirthday,
                    department: btn.dataset.editDept,
                    address: btn.dataset.editAddr,
                    student_number: btn.dataset.editStudent,
                    special_status: btn.dataset.editStatus
                };
                openEditModal(btn.dataset.editId, userData);
            }
        });

        // Setup on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Setup form submission
            const editForm = document.getElementById('editForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    fetch('edit_patient_info.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Patient information updated successfully!');
                            closeEditModal();
                            location.reload();
                        } else {
                            alert('Error: ' + (data.message || 'Failed to update patient information'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while updating patient information');
                    });
                });
            }

            // Debounced search
            const searchInput = document.getElementById('searchInput');
            const getCards = () => Array.from(document.querySelectorAll('.user-card'));

            function matchesCard(card, term) {
                if (!term) return true;
                term = term.toLowerCase();
                return (
                    (card.dataset.name || '').includes(term) ||
                    (card.dataset.email || '').includes(term) ||
                    (card.dataset.phone || '').includes(term) ||
                    (card.dataset.address || '').includes(term) ||
                    (card.dataset.department || '').includes(term)
                );
            }

            function applyFilter() {
                const term = searchInput.value.trim().toLowerCase();
                getCards().forEach(card => {
                    const searchMatches = matchesCard(card, term);
                    card.style.display = searchMatches ? '' : 'none';
                });
            }

            // simple debounce
            let timer = null;
            function debounceApply() {
                clearTimeout(timer);
                timer = setTimeout(applyFilter, 180);
            }

            searchInput.addEventListener('input', debounceApply);

            // support Enter key to run immediately
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); applyFilter(); }
                if (e.key === 'Escape') { searchInput.value = ''; applyFilter(); }
            });
        });
    </script>
</body>
</html>