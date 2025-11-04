<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// Check if user is admin
require_admin();

$user = get_logged_user();

// Get statistics
$db = new Database();
$conn = $db->connect();

// Total reservations by status
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM reservations");
$stmt->execute();
$stats = $stmt->fetch();

// Today's reservations
$stmt = $conn->prepare("SELECT COUNT(*) as today_count FROM reservations 
                        WHERE reservation_date = CURDATE() 
                        AND status = 'approved'");
$stmt->execute();
$today_stats = $stmt->fetch();

// Upcoming reservations
$stmt = $conn->prepare("SELECT r.*, u.name as user_name, u.email, u.contact_no,
                        b.bus_name, b.plate_no, d.name as driver_name 
                        FROM reservations r 
                        LEFT JOIN users u ON r.user_id = u.id
                        LEFT JOIN buses b ON r.bus_id = b.id 
                        LEFT JOIN drivers d ON r.driver_id = d.id 
                        WHERE r.reservation_date >= CURDATE() 
                        AND r.status = 'approved'
                        ORDER BY r.reservation_date ASC, r.reservation_time ASC 
                        LIMIT 5");
$stmt->execute();
$upcoming = $stmt->fetchAll();

// Pending approvals
$stmt = $conn->prepare("SELECT r.*, u.name as user_name, u.email, u.contact_no
                        FROM reservations r 
                        LEFT JOIN users u ON r.user_id = u.id
                        WHERE r.status = 'pending'
                        ORDER BY r.created_at DESC
                        LIMIT 5");
$stmt->execute();
$pending = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 4px solid var(--wmsu-maroon);
        }
        
        .stat-card.pending {
            border-top-color: var(--warning-yellow);
        }
        
        .stat-card.approved {
            border-top-color: var(--success-green);
        }
        
        .stat-card.rejected {
            border-top-color: var(--danger-red);
        }
        
        .stat-card h3 {
            font-size: 36px;
            color: var(--wmsu-maroon);
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
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
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="reservations.php">Reservations</a></li>
            <li><a href="buses.php">Buses</a></li>
            <li><a href="drivers.php">Drivers</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="users.php">Users</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h2 style="margin-bottom: 20px;">Admin Dashboard</h2>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total Reservations</p>
            </div>
            <div class="stat-card pending">
                <h3><?php echo $stats['pending']; ?></h3>
                <p>Pending Approval</p>
            </div>
            <div class="stat-card approved">
                <h3><?php echo $stats['approved']; ?></h3>
                <p>Approved</p>
            </div>
            <div class="stat-card rejected">
                <h3><?php echo $stats['rejected']; ?></h3>
                <p>Rejected</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $today_stats['today_count']; ?></h3>
                <p>Today's Trips</p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="reservations.php?status=pending" class="btn btn-warning" style="padding: 20px;">
                <strong>⏳ Pending Approvals (<?php echo $stats['pending']; ?>)</strong>
            </a>
            <a href="buses.php" class="btn btn-info" style="padding: 20px;">
                <strong>🚌 Manage Buses</strong>
            </a>
            <a href="drivers.php" class="btn btn-info" style="padding: 20px;">
                <strong>👨‍✈️ Manage Drivers</strong>
            </a>
            <a href="reports.php" class="btn btn-secondary" style="padding: 20px;">
                <strong>📊 Generate Reports</strong>
            </a>
        </div>
        
        <!-- Pending Approvals -->
        <?php if (count($pending) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h2>⏳ Pending Approvals</h2>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Requester</th>
                        <th>Date & Time</th>
                        <th>Purpose</th>
                        <th>Destination</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $res): ?>
                    <tr>
                        <td><?php echo $res['id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($res['user_name']); ?><br>
                            <small><?php echo htmlspecialchars($res['email']); ?></small>
                        </td>
                        <td>
                            <?php echo format_date($res['reservation_date']); ?><br>
                            <small><?php echo format_time($res['reservation_time']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars(substr($res['purpose'], 0, 40)) . '...'; ?></td>
                        <td>
                            📍 <?php echo htmlspecialchars($res['destination']); ?>
                        </td>
                        <td>
                            <a href="reservations.php?view=<?php echo $res['id']; ?>" class="btn btn-info" style="font-size: 12px; padding: 6px 12px;">Review</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="reservations.php?status=pending" class="btn btn-primary">View All Pending</a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Upcoming Trips -->
        <div class="card">
            <div class="card-header">
                <h2>📅 Upcoming Trips</h2>
            </div>
            
            <?php if (count($upcoming) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Requester</th>
                            <th>Purpose</th>
                            <th>Destination</th>
                            <th>Bus</th>
                            <th>Driver</th>
                            <th>Passengers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $res): ?>
                        <tr>
                            <td>
                                <?php echo format_date($res['reservation_date']); ?><br>
                                <small><?php echo format_time($res['reservation_time']); ?></small>
                                <?php if ($res['return_date']): ?>
                                    <br><small style="color: #666;">🔄 Return: <?php echo format_date($res['return_date']); ?> <?php echo format_time($res['return_time']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($res['user_name']); ?><br>
                                <small><?php echo htmlspecialchars($res['contact_no']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars(substr($res['purpose'], 0, 40)) . '...'; ?></td>
                            <td>📍 <?php echo htmlspecialchars($res['destination']); ?></td>
                            <td><?php echo htmlspecialchars($res['bus_name']); ?></td>
                            <td><?php echo htmlspecialchars($res['driver_name'] ?: 'Not assigned'); ?></td>
                            <td><?php echo $res['passenger_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="reservations.php?status=approved" class="btn btn-secondary">View All Approved</a>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>No upcoming trips scheduled</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>

    <script src="../js/main.js"></script>
</body>
</html>