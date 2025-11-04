<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect(SITE_URL . 'login.php');
}

// Redirect admin to admin dashboard
if (is_admin()) {
    redirect(SITE_URL . 'admin/dashboard.php');
}

$user = get_logged_user();
$success = '';
$error = '';

// Handle cancellation
if (isset($_POST['cancel_reservation'])) {
    $reservation_id = clean_input($_POST['reservation_id']);
    
    $db = new Database();
    $conn = $db->connect();
    
    // Verify reservation belongs to user
    $stmt = $conn->prepare("SELECT * FROM reservations WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $reservation_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $reservation = $stmt->fetch();
    
    if ($reservation && $reservation['status'] == 'pending') {
        $stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = :id");
        $stmt->bindParam(':id', $reservation_id);
        
        if ($stmt->execute()) {
            $success = 'Reservation cancelled successfully.';
            
            // Send email notification
            $email_message = "
                <h3>Reservation Cancelled</h3>
                <p>Your bus reservation has been cancelled.</p>
                <p><strong>Date:</strong> " . format_date($reservation['reservation_date']) . "</p>
                <p><strong>Time:</strong> " . format_time($reservation['reservation_time']) . "</p>
            ";
            send_email($user['email'], 'Reservation Cancelled', $email_message);
        } else {
            $error = 'Failed to cancel reservation.';
        }
    } else {
        $error = 'Cannot cancel this reservation.';
    }
}

// Get all user reservations
$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT r.*, b.bus_name, b.plate_no, d.name as driver_name 
                        FROM reservations r 
                        LEFT JOIN buses b ON r.bus_id = b.id 
                        LEFT JOIN drivers d ON r.driver_id = d.id 
                        WHERE r.user_id = :user_id 
                        ORDER BY r.reservation_date DESC, r.reservation_time DESC");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$reservations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - <?php echo SITE_NAME; ?></title>
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
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-tab.active {
            background: var(--wmsu-maroon);
            color: white;
        }
        
        .reservation-details {
            display: none;
            background: #f9f9f9;
            padding: 15px;
            margin-top: 10px;
            border-radius: 5px;
        }
        
        .reservation-details.show {
            display: block;
        }
        
        .detail-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .detail-label {
            font-weight: 600;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-content">
            <div class="logo-section">
                <img src="../images/wmsu.png" alt="WMSU Logo" onerror="this.style.display='none'">
                <h1><?php echo SITE_NAME; ?></h1>
            </div>
            <div class="user-info">
                <span class="user-name">Welcome, <?php echo htmlspecialchars($user['name']); ?>!</span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="reserve.php">New Reservation</a></li>
            <li><a href="my_reservations.php" class="active">My Reservations</a></li>
            <li><a href="profile.php">Profile</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>📋 My Reservations</h2>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab active" onclick="filterReservations('all')">All</button>
                <button class="filter-tab" onclick="filterReservations('pending')">Pending</button>
                <button class="filter-tab" onclick="filterReservations('approved')">Approved</button>
                <button class="filter-tab" onclick="filterReservations('rejected')">Rejected</button>
                <button class="filter-tab" onclick="filterReservations('cancelled')">Cancelled</button>
            </div>
            
            <?php if (count($reservations) > 0): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date & Time</th>
                            <th>Destination</th>
                            <th>Bus</th>
                            <th>Driver</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reservationsTable">
                        <?php foreach ($reservations as $res): ?>
                        <tr data-status="<?php echo $res['status']; ?>">
                            <td><?php echo $res['id']; ?></td>
                            <td>
                                <?php echo format_date($res['reservation_date']); ?><br>
                                <small><?php echo format_time($res['reservation_time']); ?></small>
                                <?php if ($res['return_date']): ?>
                                    <br><small style="color: #666;">↩ <?php echo format_date($res['return_date']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($res['destination'], 0, 40)); ?><?php echo strlen($res['destination']) > 40 ? '...' : ''; ?></td>
                            <td><?php echo htmlspecialchars($res['bus_name'] ?: 'Not assigned'); ?></td>
                            <td><?php echo htmlspecialchars($res['driver_name'] ?: 'Not assigned'); ?></td>
                            <td><?php echo get_status_badge($res['status']); ?></td>
                            <td>
                                <button onclick="toggleDetails(<?php echo $res['id']; ?>)" class="btn btn-info" style="font-size: 12px; padding: 6px 12px;">View</button>
                                
                                <?php if ($res['status'] == 'pending' && !is_past_date($res['reservation_date'])): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this reservation?');">
                                        <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                        <button type="submit" name="cancel_reservation" class="btn btn-danger" style="font-size: 12px; padding: 6px 12px;">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr id="details-<?php echo $res['id']; ?>" class="reservation-details">
                            <td colspan="7">
                                <h3 style="color: var(--wmsu-maroon); margin-bottom: 15px;">Reservation Details</h3>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Reservation ID:</div>
                                    <div><?php echo $res['id']; ?></div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Purpose:</div>
                                    <div><?php echo htmlspecialchars($res['purpose']); ?></div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Pickup Location:</div>
                                    <div>WMSU Campus, Normal Road, Baliwasan</div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Destination:</div>
                                    <div><?php echo htmlspecialchars($res['destination']); ?></div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Departure:</div>
                                    <div><?php echo format_date($res['reservation_date']); ?> at <?php echo format_time($res['reservation_time']); ?></div>
                                </div>
                                
                                <?php if ($res['return_date']): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Return:</div>
                                    <div><?php echo format_date($res['return_date']); ?> at <?php echo format_time($res['return_time']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Passenger Count:</div>
                                    <div><?php echo $res['passenger_count']; ?></div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Bus Assigned:</div>
                                    <div><?php echo $res['bus_name'] ? htmlspecialchars($res['bus_name']) . ' (' . htmlspecialchars($res['plate_no']) . ')' : 'Not yet assigned'; ?></div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Driver Assigned:</div>
                                    <div><?php echo $res['driver_name'] ? htmlspecialchars($res['driver_name']) : 'Not yet assigned'; ?></div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Status:</div>
                                    <div><?php echo get_status_badge($res['status']); ?></div>
                                </div>
                                
                                <?php if ($res['admin_remarks']): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Admin Remarks:</div>
                                    <div><?php echo htmlspecialchars($res['admin_remarks']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="detail-row">
                                    <div class="detail-label">Submitted On:</div>
                                    <div><?php echo date('F d, Y g:i A', strtotime($res['created_at'])); ?></div>
                                </div>
                                
                                <?php if ($res['approved_at']): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Approved/Rejected On:</div>
                                    <div><?php echo date('F d, Y g:i A', strtotime($res['approved_at'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>No reservations yet</p>
                    <a href="reserve.php" class="btn btn-primary" style="margin-top: 15px;">Make Your First Reservation</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>

    <script src="../js/main.js"></script>
    <script>
        function toggleDetails(id) {
            const detailsRow = document.getElementById('details-' + id);
            detailsRow.classList.toggle('show');
        }
        
        function filterReservations(status) {
            // Update active tab
            const tabs = document.querySelectorAll('.filter-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter table rows
            const rows = document.querySelectorAll('#reservationsTable tr[data-status]');
            rows.forEach(row => {
                const nextRow = row.nextElementSibling;
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                    if (nextRow && nextRow.classList.contains('reservation-details')) {
                        nextRow.style.display = '';
                    }
                } else {
                    row.style.display = 'none';
                    if (nextRow && nextRow.classList.contains('reservation-details')) {
                        nextRow.style.display = 'none';
                        nextRow.classList.remove('show');
                    }
                }
            });
        }
    </script>
</body>
</html>