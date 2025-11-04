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

// Handle approval/rejection with driver assignment
if (isset($_POST['approve_reservation']) || isset($_POST['reject_reservation'])) {
    $reservation_id = clean_input($_POST['reservation_id']);
    $admin_remarks = clean_input($_POST['admin_remarks']);
    $driver_id = isset($_POST['driver_id']) ? clean_input($_POST['driver_id']) : null;
    $send_email = isset($_POST['send_email']);
    
    $new_status = isset($_POST['approve_reservation']) ? 'approved' : 'rejected';
    
    // Get reservation details
    $stmt = $conn->prepare("SELECT r.*, u.name as user_name, u.email, u.contact_no, b.bus_name, b.plate_no
                            FROM reservations r 
                            LEFT JOIN users u ON r.user_id = u.id 
                            LEFT JOIN buses b ON r.bus_id = b.id
                            WHERE r.id = :id");
    $stmt->bindParam(':id', $reservation_id);
    $stmt->execute();
    $reservation = $stmt->fetch();
    
    if ($reservation) {
        // For approval, driver is required
        if ($new_status == 'approved' && empty($driver_id)) {
            $errors[] = 'Please assign a driver before approving the reservation';
        } else {
            // CRITICAL: Double-check driver availability before approving
            if ($new_status == 'approved' && $driver_id) {
                $return_date = $reservation['return_date'] ?: $reservation['reservation_date'];
                
                $check_stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM reservations 
                    WHERE driver_id = :driver_id 
                    AND status IN ('pending', 'approved')
                    AND id != :exclude_id
                    AND (
                        -- Check if driver is busy on departure date
                        (reservation_date <= :end_date AND COALESCE(return_date, reservation_date) >= :start_date)
                    )
                ");
                $check_stmt->bindParam(':driver_id', $driver_id);
                $check_stmt->bindParam(':start_date', $reservation['reservation_date']);
                $check_stmt->bindParam(':end_date', $return_date);
                $check_stmt->bindParam(':exclude_id', $reservation_id);
                $check_stmt->execute();
                $driver_check = $check_stmt->fetch();
                
                if ($driver_check['count'] > 0) {
                    $errors[] = 'This driver is already assigned to another reservation on these dates. Please refresh the page and select another driver.';
                }
            }
            
            if (empty($errors)) {
                $approved_at = date('Y-m-d H:i:s');
                
                $stmt = $conn->prepare("UPDATE reservations 
                                        SET status = :status, 
                                            admin_remarks = :remarks, 
                                            driver_id = :driver_id,
                                            approved_by = :admin_id,
                                            approved_at = :approved_at
                                        WHERE id = :id");
                $stmt->bindParam(':status', $new_status);
                $stmt->bindParam(':remarks', $admin_remarks);
                $stmt->bindParam(':driver_id', $driver_id);
                $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $stmt->bindParam(':approved_at', $approved_at);
                $stmt->bindParam(':id', $reservation_id);
                
                if ($stmt->execute()) {
                    $success = 'Reservation ' . $new_status . ' successfully!';
                    
                    // Get driver name if assigned
                    $driver_name = 'TBA';
                    if ($driver_id) {
                        $stmt = $conn->prepare("SELECT name FROM drivers WHERE id = :id");
                        $stmt->bindParam(':id', $driver_id);
                        $stmt->execute();
                        $driver = $stmt->fetch();
                        $driver_name = $driver ? $driver['name'] : 'TBA';
                    }
                    
                    // Send notifications
                    if ($send_email) {
                        $status_text = ($new_status == 'approved') ? 'APPROVED' : 'REJECTED';
                        
                        $return_info = '';
                        if ($reservation['return_date']) {
                            $return_info = "<p><strong>Return:</strong> " . format_date($reservation['return_date']) . " at " . format_time($reservation['return_time']) . "</p>";
                        }
                        
                        $email_message = "
                            <h3>Reservation {$status_text}</h3>
                            <p>Dear {$reservation['user_name']},</p>
                            <p>Your bus reservation request has been <strong>{$status_text}</strong>.</p>
                            <h4>Reservation Details:</h4>
                            <p><strong>Departure:</strong> " . format_date($reservation['reservation_date']) . " at " . format_time($reservation['reservation_time']) . "</p>
                            {$return_info}
                            <p><strong>Destination:</strong> " . htmlspecialchars($reservation['destination']) . "</p>
                            <p><strong>Purpose:</strong> " . htmlspecialchars($reservation['purpose']) . "</p>
                            <p><strong>Bus:</strong> " . htmlspecialchars($reservation['bus_name']) . " (" . htmlspecialchars($reservation['plate_no']) . ")</p>
                            <p><strong>Driver:</strong> " . htmlspecialchars($driver_name) . "</p>
                        ";
                        
                        if (!empty($admin_remarks)) {
                            $email_message .= "<p><strong>Admin Remarks:</strong> " . htmlspecialchars($admin_remarks) . "</p>";
                        }
                        
                        send_email($reservation['email'], "Reservation {$status_text}", $email_message);
                    }
                } else {
                    $errors[] = 'Failed to update reservation';
                }
            }
        }
    }
}

// Get filter
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : 'all';

// Build query
$where = "1=1";
if ($status_filter != 'all') {
    $where .= " AND r.status = :status";
}

$stmt = $conn->prepare("SELECT r.*, u.name as user_name, u.email, u.contact_no,
                        b.bus_name, b.plate_no, d.name as driver_name 
                        FROM reservations r 
                        LEFT JOIN users u ON r.user_id = u.id
                        LEFT JOIN buses b ON r.bus_id = b.id 
                        LEFT JOIN drivers d ON r.driver_id = d.id 
                        WHERE {$where}
                        ORDER BY r.created_at DESC");

if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter);
}

$stmt->execute();
$reservations = $stmt->fetchAll();

// Get specific reservation for view
$view_reservation = null;
$available_drivers = [];
if (isset($_GET['view'])) {
    $view_id = clean_input($_GET['view']);
    $stmt = $conn->prepare("SELECT r.*, u.name as user_name, u.email, u.contact_no, u.department, u.position,
                            b.bus_name, b.plate_no, b.capacity, d.name as driver_name, d.contact_no as driver_contact,
                            admin.name as approved_by_name
                            FROM reservations r 
                            LEFT JOIN users u ON r.user_id = u.id
                            LEFT JOIN buses b ON r.bus_id = b.id 
                            LEFT JOIN drivers d ON r.driver_id = d.id 
                            LEFT JOIN users admin ON r.approved_by = admin.id
                            WHERE r.id = :id");
    $stmt->bindParam(':id', $view_id);
    $stmt->execute();
    $view_reservation = $stmt->fetch();
    
    // Get available drivers for this date range
    if ($view_reservation && $view_reservation['status'] == 'pending') {
        $departure_date = $view_reservation['reservation_date'];
        $return_date = $view_reservation['return_date'] ?: $departure_date;
        
        // FIXED: Get drivers who are NOT assigned to ANY reservation during this period
        $stmt = $conn->prepare("
            SELECT d.* 
            FROM drivers d 
            WHERE d.status = 'available' 
            AND (d.deleted = 0 OR d.deleted IS NULL)
            AND d.id NOT IN (
                SELECT driver_id 
                FROM reservations 
                WHERE driver_id IS NOT NULL
                AND status IN ('pending', 'approved')
                AND id != :exclude_id
                AND (
                    -- Check if driver is busy during the requested period
                    (reservation_date <= :end_date AND COALESCE(return_date, reservation_date) >= :start_date)
                )
            )
            ORDER BY d.name
        ");
        $stmt->bindParam(':start_date', $departure_date);
        $stmt->bindParam(':end_date', $return_date);
        $stmt->bindParam(':exclude_id', $view_id);
        $stmt->execute();
        $available_drivers = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reservations - <?php echo SITE_NAME; ?></title>
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
            max-width: 800px;
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
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .notification-options {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .driver-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            color: #856404;
        }
        
        .driver-conflict-warning {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            color: #721c24;
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
            <li><a href="reservations.php" class="active">Reservations</a></li>
            <li><a href="buses.php">Buses</a></li>
            <li><a href="drivers.php">Drivers</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="user_approvals.php">User Approvals</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>📋 Manage Reservations</h2>
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
                <a href="reservations.php?status=all" class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                <a href="reservations.php?status=pending" class="filter-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="reservations.php?status=approved" class="filter-tab <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">Approved</a>
                <a href="reservations.php?status=rejected" class="filter-tab <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">Rejected</a>
                <a href="reservations.php?status=cancelled" class="filter-tab <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
            </div>
            
            <?php if (count($reservations) > 0): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Requester</th>
                            <th>Date & Time</th>
                            <th>Destination</th>
                            <th>Bus</th>
                            <th>Driver</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $res): ?>
                        <tr>
                            <td><?php echo $res['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($res['user_name']); ?><br>
                                <small><?php echo htmlspecialchars($res['email']); ?></small>
                            </td>
                            <td>
                                <?php echo format_date($res['reservation_date']); ?><br>
                                <small><?php echo format_time($res['reservation_time']); ?></small>
                                <?php if ($res['return_date']): ?>
                                    <br><small style="color: #666;">↩ <?php echo format_date($res['return_date']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($res['destination'], 0, 30)) . '...'; ?></td>
                            <td><?php echo htmlspecialchars($res['bus_name'] ?: 'Not assigned'); ?></td>
                            <td><?php echo htmlspecialchars($res['driver_name'] ?: 'Not assigned'); ?></td>
                            <td><?php echo get_status_badge($res['status']); ?></td>
                            <td>
                                <a href="reservations.php?view=<?php echo $res['id']; ?>&status=<?php echo $status_filter; ?>" class="btn btn-info" style="font-size: 12px; padding: 6px 12px;">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>No reservations found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View/Approve Modal -->
    <?php if ($view_reservation): ?>
    <div class="modal show" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reservation Details #<?php echo $view_reservation['id']; ?></h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <h3 style="color: var(--wmsu-maroon); margin-bottom: 15px;">Requester Information</h3>
                
                <div class="detail-grid">
                    <div class="detail-label">Name:</div>
                    <div><?php echo htmlspecialchars($view_reservation['user_name']); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Email:</div>
                    <div><?php echo htmlspecialchars($view_reservation['email']); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Contact:</div>
                    <div><?php echo htmlspecialchars($view_reservation['contact_no']); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Department:</div>
                    <div><?php echo htmlspecialchars($view_reservation['department'] ?: 'N/A'); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Position:</div>
                    <div><?php echo htmlspecialchars($view_reservation['position'] ?: 'N/A'); ?></div>
                </div>
                
                <hr style="margin: 20px 0;">
                
                <h3 style="color: var(--wmsu-maroon); margin-bottom: 15px;">Trip Details</h3>
                
                <div class="detail-grid">
                    <div class="detail-label">Purpose:</div>
                    <div><?php echo htmlspecialchars($view_reservation['purpose']); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Pickup Location:</div>
                    <div>🏫 WMSU Campus, Normal Road, Baliwasan</div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Destination:</div>
                    <div>📍 <?php echo htmlspecialchars($view_reservation['destination']); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Departure:</div>
                    <div><?php echo format_date($view_reservation['reservation_date']); ?> at <?php echo format_time($view_reservation['reservation_time']); ?></div>
                </div>
                
                <?php if ($view_reservation['return_date']): ?>
                <div class="detail-grid">
                    <div class="detail-label">Return:</div>
                    <div><?php echo format_date($view_reservation['return_date']); ?> at <?php echo format_time($view_reservation['return_time']); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="detail-grid">
                    <div class="detail-label">Passengers:</div>
                    <div><?php echo $view_reservation['passenger_count']; ?> / <?php echo $view_reservation['capacity']; ?> capacity</div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Bus:</div>
                    <div><?php echo $view_reservation['bus_name'] ? htmlspecialchars($view_reservation['bus_name']) . ' (' . htmlspecialchars($view_reservation['plate_no']) . ')' : 'Not yet assigned'; ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Driver:</div>
                    <div><?php echo $view_reservation['driver_name'] ? htmlspecialchars($view_reservation['driver_name']) : 'Not yet assigned'; ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Status:</div>
                    <div><?php echo get_status_badge($view_reservation['status']); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Submitted On:</div>
                    <div><?php echo date('F d, Y g:i A', strtotime($view_reservation['created_at'])); ?></div>
                </div>
                
                <?php if ($view_reservation['approved_at']): ?>
                <div class="detail-grid">
                    <div class="detail-label">Processed On:</div>
                    <div><?php echo date('F d, Y g:i A', strtotime($view_reservation['approved_at'])); ?></div>
                </div>
                
                <div class="detail-grid">
                    <div class="detail-label">Processed By:</div>
                    <div><?php echo htmlspecialchars($view_reservation['approved_by_name'] ?: 'N/A'); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($view_reservation['admin_remarks']): ?>
                <div class="detail-grid">
                    <div class="detail-label">Admin Remarks:</div>
                    <div><?php echo htmlspecialchars($view_reservation['admin_remarks']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($view_reservation['status'] == 'pending'): ?>
                <hr style="margin: 20px 0;">
                
                <h3 style="color: var(--wmsu-maroon); margin-bottom: 15px;">Assign Driver & Approve/Reject</h3>
                
                <?php if (count($available_drivers) == 0): ?>
                <div class="driver-warning">
                    <strong>⚠️ No drivers available!</strong>
                    <p style="margin: 10px 0 0 0;">All drivers are either:</p>
                    <ul style="margin: 10px 0 0 20px;">
                        <li>Unavailable in system (marked as unavailable/deleted)</li>
                        <li>Already assigned to other trips on these dates</li>
                        <li>On sick leave or vacation</li>
                    </ul>
                    <p style="margin: 10px 0 0 0;"><strong>Action:</strong> Please reassign dates or check driver availability in the Drivers section.</p>
                </div>
                <?php else: ?>
                <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; color: #155724;">
                    <strong>✅ <?php echo count($available_drivers); ?> driver(s) available</strong> for these dates (<?php echo format_date($view_reservation['reservation_date']); ?> 
                    <?php if ($view_reservation['return_date'] && $view_reservation['return_date'] != $view_reservation['reservation_date']): ?>
                        to <?php echo format_date($view_reservation['return_date']); ?>
                    <?php endif; ?>)
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="approvalForm">
                    <input type="hidden" name="reservation_id" value="<?php echo $view_reservation['id']; ?>">
                    
                    <div class="form-group">
                        <label for="driver_id">Assign Driver <span class="required">*</span></label>
                        <select id="driver_id" name="driver_id" class="form-control" <?php echo count($available_drivers) == 0 ? 'disabled' : 'required'; ?>>
                            <option value="">-- Select Available Driver --</option>
                            <?php foreach ($available_drivers as $driver): ?>
                            <option value="<?php echo $driver['id']; ?>">
                                <?php echo htmlspecialchars($driver['name']); ?>
                                <?php if ($driver['license_no']): ?>
                                    (License: <?php echo htmlspecialchars($driver['license_no']); ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666;">⚠️ Driver must be free on ALL dates from departure to return</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_remarks">Remarks (Optional)</label>
                        <textarea id="admin_remarks" name="admin_remarks" class="form-control" rows="3" 
                                  placeholder="Enter any remarks or instructions"></textarea>
                    </div>
                    
                    <div class="notification-options">
                        <strong>Send Notification:</strong>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="send_email" value="1" checked> Email
                            </label>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" name="approve_reservation" class="btn btn-success" 
                                <?php echo count($available_drivers) == 0 ? 'disabled' : ''; ?>
                                onclick="return confirm('⚠️ CONFIRM APPROVAL\n\nDriver will be assigned to:\n• Bus: <?php echo addslashes($view_reservation['bus_name']); ?>\n• Dates: <?php echo format_date($view_reservation['reservation_date']); ?><?php if($view_reservation['return_date']) echo ' to ' . format_date($view_reservation['return_date']); ?>\n\nDriver will be UNAVAILABLE for other trips during this period.\n\nProceed?');">
                            ✅ Approve
                        </button>
                        <button type="submit" name="reject_reservation" class="btn btn-danger" 
                                onclick="return confirm('Are you sure you want to REJECT this reservation?');">
                            ❌ Reject
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
                <?php else: ?>
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function closeModal() {
            window.location.href = 'reservations.php<?php echo $status_filter != 'all' ? '?status=' . $status_filter : ''; ?>';
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