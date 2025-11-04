<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// Check if user is admin
require_admin();

$user = get_logged_user();
$success = '';
$errors = [];

$db = new Database();
$conn = $db->connect();

// Handle approval
if (isset($_POST['approve_account'])) {
    $user_id = clean_input($_POST['user_id']);
    
    $stmt = $conn->prepare("UPDATE users SET account_status = 'approved', approved_by_admin = :admin_id, approved_at = NOW() WHERE id = :user_id");
    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        // Get user details for email
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $approved_user = $stmt->fetch();
        
        // Send approval email
        $email_message = "
            <h3>Account Approved</h3>
            <p>Dear " . htmlspecialchars($approved_user['name']) . ",</p>
            <p>Your account has been approved! You can now login to the WMSU Bus Reserve System.</p>
            <p><strong>Email:</strong> " . htmlspecialchars($approved_user['email']) . "</p>
            <p><a href='" . SITE_URL . "login.php'>Login Now</a></p>
        ";
        
        send_email($approved_user['email'], 'Account Approved - WMSU Bus Reserve System', $email_message);
        
        $success = 'Account approved successfully!';
    } else {
        $errors[] = 'Failed to approve account';
    }
}

// Handle rejection
if (isset($_POST['reject_account'])) {
    $user_id = clean_input($_POST['user_id']);
    $rejection_reason = clean_input($_POST['rejection_reason']);
    
    $stmt = $conn->prepare("UPDATE users SET account_status = 'rejected', rejection_reason = :reason, approved_by_admin = :admin_id, approved_at = NOW() WHERE id = :user_id");
    $stmt->bindParam(':reason', $rejection_reason);
    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        // Get user details for email
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $rejected_user = $stmt->fetch();
        
        // Send rejection email
        $email_message = "
            <h3>Account Registration Rejected</h3>
            <p>Dear " . htmlspecialchars($rejected_user['name']) . ",</p>
            <p>Unfortunately, your account registration has been rejected.</p>
            <p><strong>Reason:</strong> " . htmlspecialchars($rejection_reason) . "</p>
            <p>If you believe this is an error, please contact the administrator.</p>
        ";
        
        send_email($rejected_user['email'], 'Account Rejected - WMSU Bus Reserve System', $email_message);
        
        $success = 'Account rejected successfully!';
    } else {
        $errors[] = 'Failed to reject account';
    }
}

// Handle delete user
if (isset($_POST['delete_user'])) {
    $user_id = clean_input($_POST['user_id']);
    
    // Prevent admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        $errors[] = 'You cannot delete your own account';
    } else {
        // Check if user has reservations
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $errors[] = 'Cannot delete user with existing reservations';
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $user_id);
            
            if ($stmt->execute()) {
                $success = 'User deleted successfully!';
            } else {
                $errors[] = 'Failed to delete user';
            }
        }
    }
}

// Handle change user role
if (isset($_POST['change_role'])) {
    $user_id = clean_input($_POST['user_id']);
    $new_role = clean_input($_POST['new_role']);
    
    $stmt = $conn->prepare("UPDATE users SET role = :role WHERE id = :id");
    $stmt->bindParam(':role', $new_role);
    $stmt->bindParam(':id', $user_id);
    
    if ($stmt->execute()) {
        $success = 'User role updated successfully!';
    } else {
        $errors[] = 'Failed to update user role';
    }
}

// Get filter
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : 'pending';

// Build query based on status
$where = "account_status = :status";
if ($status_filter == 'all') {
    $where = "role != 'admin'";
}

$stmt = $conn->prepare("SELECT * FROM users WHERE {$where} ORDER BY created_at DESC");
if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter);
}
$stmt->execute();
$users = $stmt->fetchAll();

// Get counts
$stmt = $conn->prepare("SELECT 
    SUM(CASE WHEN account_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN account_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN account_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM users WHERE role != 'admin'");
$stmt->execute();
$counts = $stmt->fetch();

// Get specific user for viewing
$view_user = null;
if (isset($_GET['view'])) {
    $view_id = clean_input($_GET['view']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $view_id);
    $stmt->execute();
    $view_user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 10px 20px;
            background: white;
            border: 2px solid var(--wmsu-maroon);
            color: var(--wmsu-maroon);
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .filter-tab.active {
            background: var(--wmsu-maroon);
            color: white;
        }
        
        .filter-tab:hover {
            background: var(--wmsu-maroon-light);
            color: white;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .role-badge.admin {
            background: var(--danger-red);
            color: white;
        }
        
        .role-badge.teacher {
            background: var(--info-blue);
            color: white;
        }
        
        .role-badge.employee {
            background: var(--warning-yellow);
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.pending {
            background: var(--warning-yellow);
            color: #856404;
        }
        
        .status-badge.approved {
            background: var(--success-green);
            color: white;
        }
        
        .status-badge.rejected {
            background: var(--danger-red);
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        
        .modal.show {
            display: block;
        }
        
        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 0;
            border-radius: 8px;
            max-width: 900px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            background: var(--wmsu-maroon);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
        }
        
        .close:hover {
            opacity: 0.7;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        
        .id-images {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .id-image-container {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            background: #f9f9f9;
        }
        
        .id-image-container h4 {
            color: var(--wmsu-maroon);
            margin-bottom: 10px;
            font-size: 14px;
            text-align: center;
        }
        
        .id-image-container img {
            width: 100%;
            height: auto;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .id-image-container img:hover {
            transform: scale(1.05);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .id-images {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-content">
            <div class="logo-section">
                <img src="../images/wmsu.png" alt="WMSU Logo">
                <h1><?php echo SITE_NAME; ?> - Admin</h1>
            </div>
            <div class="user-info">
                <span class="user-name">Admin: <?php echo htmlspecialchars($user['name']); ?></span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="reservations.php">Reservations</a></li>
            <li><a href="buses.php">Buses</a></li>
            <li><a href="drivers.php">Drivers</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="users.php" class="active">Users</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>👥 Manage Users & Approvals</h2>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="users.php?status=pending" class="filter-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $counts['pending_count']; ?>)
                </a>
                <a href="users.php?status=approved" class="filter-tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">
                    Approved (<?php echo $counts['approved_count']; ?>)
                </a>
                <a href="users.php?status=rejected" class="filter-tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">
                    Rejected (<?php echo $counts['rejected_count']; ?>)
                </a>
                <a href="users.php?status=all" class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                    All Users
                </a>
            </div>
            
            <?php if (count($users) > 0): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['contact_no'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($u['department'] ?: 'N/A'); ?></td>
                            <td>
                                <span class="role-badge <?php echo $u['role']; ?>">
                                    <?php echo ucfirst($u['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $u['account_status']; ?>">
                                    <?php echo ucfirst($u['account_status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <a href="users.php?view=<?php echo $u['id']; ?>&status=<?php echo $status_filter; ?>" class="btn btn-info" style="font-size: 12px; padding: 6px 12px;">
                                    <?php echo $u['account_status'] == 'pending' ? 'Review' : 'View'; ?>
                                </a>
                                
                                <?php if ($u['id'] != $_SESSION['user_id'] && $u['account_status'] != 'pending'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger" style="font-size: 12px; padding: 6px 12px;">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>No users found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View/Review User Modal -->
    <?php if ($view_user): ?>
    <div class="modal show" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php echo $view_user['account_status'] == 'pending' ? 'Review Account Registration' : 'User Details'; ?></h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <h3 style="color: var(--wmsu-maroon); margin-bottom: 15px;">User Information</h3>
                
                <div class="detail-grid">
                    <div class="detail-label">Full Name:</div>
                    <div><?php echo htmlspecialchars($view_user['name']); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Email:</div>
                    <div><?php echo htmlspecialchars($view_user['email']); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Contact Number:</div>
                    <div><?php echo htmlspecialchars($view_user['contact_no']); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Department:</div>
                    <div><?php echo htmlspecialchars($view_user['department']); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Role:</div>
                    <div>
                        <span class="role-badge <?php echo $view_user['role']; ?>">
                            <?php echo ucfirst($view_user['role']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Account Status:</div>
                    <div>
                        <span class="status-badge <?php echo $view_user['account_status']; ?>">
                            <?php echo ucfirst($view_user['account_status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Registered On:</div>
                    <div><?php echo date('F d, Y g:i A', strtotime($view_user['created_at'])); ?></div>
                </div>
                
                <?php if ($view_user['rejection_reason']): ?>
                <div class="detail-grid">
                    <div class="detail-label">Rejection Reason:</div>
                    <div><?php echo htmlspecialchars($view_user['rejection_reason']); ?></div>
                </div>
                <?php endif; ?>
                
                <hr style="margin: 20px 0;">
                
                <h3 style="color: var(--wmsu-maroon); margin-bottom: 15px;">Employee/Teacher ID Verification</h3>
                
                <div class="id-images">
                    <div class="id-image-container">
                        <h4>📄 FRONT SIDE</h4>
                        <?php if ($view_user['employee_id_image']): ?>
                            <img src="../<?php echo htmlspecialchars($view_user['employee_id_image']); ?>" 
                                 alt="Employee ID Front" 
                                 onclick="window.open('../<?php echo htmlspecialchars($view_user['employee_id_image']); ?>', '_blank')">
                            <p style="text-align: center; margin-top: 10px; font-size: 12px; color: #666;">
                                Click to view full size
                            </p>
                        <?php else: ?>
                            <p style="text-align: center; color: #999;">No image uploaded</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="id-image-container">
                        <h4>📄 BACK SIDE</h4>
                        <?php if ($view_user['employee_id_back_image']): ?>
                            <img src="../<?php echo htmlspecialchars($view_user['employee_id_back_image']); ?>" 
                                 alt="Employee ID Back" 
                                 onclick="window.open('../<?php echo htmlspecialchars($view_user['employee_id_back_image']); ?>', '_blank')">
                            <p style="text-align: center; margin-top: 10px; font-size: 12px; color: #666;">
                                Click to view full size
                            </p>
                        <?php else: ?>
                            <p style="text-align: center; color: #999;">No image uploaded</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($view_user['account_status'] == 'pending'): ?>
                <hr style="margin: 20px 0;">
                
                <h3 style="color: var(--wmsu-maroon); margin-bottom: 15px;">Approve or Reject</h3>
                
                <div class="action-buttons">
                    <form method="POST" action="" style="flex: 1;">
                        <input type="hidden" name="user_id" value="<?php echo $view_user['id']; ?>">
                        <button type="submit" name="approve_account" class="btn btn-success btn-block" 
                                onclick="return confirm('Are you sure you want to APPROVE this account?');">
                            ✅ Approve Account
                        </button>
                    </form>
                    
                    <button type="button" class="btn btn-danger" style="flex: 1;" onclick="showRejectForm()">
                        ❌ Reject Account
                    </button>
                </div>
                
                <div id="rejectForm" style="display: none; margin-top: 20px; padding: 20px; background: #fff3cd; border-radius: 8px;">
                    <form method="POST" action="">
                        <input type="hidden" name="user_id" value="<?php echo $view_user['id']; ?>">
                        <div class="form-group">
                            <label for="rejection_reason">Rejection Reason <span class="required">*</span></label>
                            <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="3" 
                                      placeholder="Please provide a reason for rejection" required></textarea>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="reject_account" class="btn btn-danger" 
                                    onclick="return confirm('Are you sure you want to REJECT this account?');">
                                Confirm Rejection
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="hideRejectForm()">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary btn-block" onclick="closeModal()">Close</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function closeModal() {
            window.location.href = 'users.php?status=<?php echo $status_filter; ?>';
        }
        
        function showRejectForm() {
            document.getElementById('rejectForm').style.display = 'block';
        }
        
        function hideRejectForm() {
            document.getElementById('rejectForm').style.display = 'none';
        }
    </script>
    <?php endif; ?>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>

    <script src="../js/main.js"></script>
</body>
</html>