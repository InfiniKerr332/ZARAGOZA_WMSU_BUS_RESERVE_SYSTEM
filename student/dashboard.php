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

// Check if there's an error message from redirect
$error_message = '';
if (isset($_GET['error']) && $_GET['error'] == 'only_teachers') {
    $error_message = 'Only teachers and employees are allowed to make bus reservations.';
}

// Get user's reservations statistics
$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM reservations WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->fetch();

// Get upcoming reservations
$stmt = $conn->prepare("SELECT r.*, b.bus_name, b.plate_no, d.name as driver_name 
                        FROM reservations r 
                        LEFT JOIN buses b ON r.bus_id = b.id 
                        LEFT JOIN drivers d ON r.driver_id = d.id 
                        WHERE r.user_id = :user_id 
                        AND r.reservation_date >= CURDATE() 
                        AND r.status IN ('pending', 'approved')
                        ORDER BY r.reservation_date ASC, r.reservation_time ASC 
                        LIMIT 5");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$upcoming = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
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
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            flex: 1;
            min-width: 200px;
        }
        
        .restricted-notice {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        
        .restricted-notice h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .restricted-notice p {
            color: #856404;
            margin: 5px 0;
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
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <?php if ($user['role'] == 'teacher' || $user['role'] == 'employee'): ?>
                <li><a href="reserve.php">New Reservation</a></li>
            <?php endif; ?>
            <li><a href="my_reservations.php">My Reservations</a></li>
            <li><a href="profile.php">Profile</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h2 style="margin-bottom: 20px;">Dashboard</h2>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
                <span class="alert-close">&times;</span>
            </div>
        <?php endif; ?>
        
        <!-- Restricted Notice for Students -->
        <?php if ($user['role'] == 'student'): ?>
        <div class="restricted-notice">
            <h3>ℹ️ Reservation Access</h3>
            <p><strong>Only teachers and employees can make bus reservations.</strong></p>
            <p>Students cannot reserve buses directly. Please contact your teacher or department head if you need bus transportation for official university activities.</p>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total Reservations</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['pending']; ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['approved']; ?></h3>
                <p>Approved</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['rejected']; ?></h3>
                <p>Rejected</p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <?php if ($user['role'] == 'teacher' || $user['role'] == 'employee'): ?>
                <a href="reserve.php" class="btn btn-primary action-btn">📅 New Reservation</a>
            <?php endif; ?>
            <a href="my_reservations.php" class="btn btn-secondary action-btn">📋 View All Reservations</a>
        </div>
        
        <!-- Upcoming Reservations -->
        <div class="card">
            <div class="card-header">
                <h2>Upcoming Reservations</h2>
            </div>
            
            <?php if (count($upcoming) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Purpose</th>
                            <th>Destination</th>
                            <th>Bus</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming as $res): ?>
                        <tr>
                            <td>
                                <?php echo format_date($res['reservation_date']); ?><br>
                                <small><?php echo format_time($res['reservation_time']); ?></small>
                                <?php if ($res['return_date']): ?>
                                    <br><small style="color: #666;">Return: <?php echo format_date($res['return_date']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($res['purpose'], 0, 50)) . '...'; ?></td>
                            <td><?php echo htmlspecialchars($res['destination']); ?></td>
                            <td><?php echo $res['bus_name'] ? htmlspecialchars($res['bus_name']) : 'Not assigned'; ?></td>
                            <td><?php echo get_status_badge($res['status']); ?></td>
                            <td>
                                <a href="my_reservations.php?view=<?php echo $res['id']; ?>" class="btn btn-info" style="font-size: 12px; padding: 6px 12px;">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="my_reservations.php" class="btn btn-secondary">View All Reservations</a>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>No upcoming reservations</p>
                    <?php if ($user['role'] == 'teacher' || $user['role'] == 'employee'): ?>
                        <a href="reserve.php" class="btn btn-primary" style="margin-top: 15px;">Make a Reservation</a>
                    <?php endif; ?>
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