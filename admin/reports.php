<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// Check if user is admin
require_admin();

$user = get_logged_user();

$db = new Database();
$conn = $db->connect();

// Get filter parameters
$date_from = isset($_GET['date_from']) ? clean_input($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? clean_input($_GET['date_to']) : date('Y-m-d');
$bus_filter = isset($_GET['bus_id']) ? clean_input($_GET['bus_id']) : 'all';
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : 'all';

// Build query
$where = "r.reservation_date BETWEEN :date_from AND :date_to";

if ($bus_filter != 'all') {
    $where .= " AND r.bus_id = :bus_id";
}

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
                        ORDER BY r.reservation_date DESC, r.reservation_time DESC");

$stmt->bindParam(':date_from', $date_from);
$stmt->bindParam(':date_to', $date_to);

if ($bus_filter != 'all') {
    $stmt->bindParam(':bus_id', $bus_filter);
}

if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter);
}

$stmt->execute();
$reservations = $stmt->fetchAll();

// Get all buses for filter
$stmt = $conn->prepare("SELECT * FROM buses WHERE (deleted = 0 OR deleted IS NULL) ORDER BY bus_name");
$stmt->execute();
$buses = $stmt->fetchAll();

// Calculate statistics
$total_trips = count($reservations);
$approved_trips = count(array_filter($reservations, function($r) { return $r['status'] == 'approved'; }));
$pending_trips = count(array_filter($reservations, function($r) { return $r['status'] == 'pending'; }));
$total_passengers = array_sum(array_column($reservations, 'passenger_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: var(--wmsu-gray);
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        
        .stat-box h3 {
            font-size: 28px;
            color: var(--wmsu-maroon);
            margin-bottom: 5px;
        }
        
        .stat-box p {
            color: #666;
            font-size: 13px;
        }
        
        @media print {
            header, nav, .filter-section, .no-print {
                display: none !important;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            body {
                background: white;
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
            <li><a href="reports.php" class="active">Reports</a></li>
            <li><a href="users.php">Users</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <!-- Filter Section -->
        <div class="filter-section no-print">
            <h3 style="color: var(--wmsu-maroon); margin-bottom: 15px;">📊 Generate Report</h3>
            
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" class="form-control" 
                               value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" class="form-control" 
                               value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bus_id">Bus</label>
                        <select id="bus_id" name="bus_id" class="form-control">
                            <option value="all">All Buses</option>
                            <?php foreach ($buses as $bus): ?>
                                <option value="<?php echo $bus['id']; ?>" <?php echo $bus_filter == $bus['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bus['bus_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary">🖨️ Print Report</button>
                    <button type="button" onclick="exportToCSV()" class="btn btn-info">📥 Export CSV</button>
                </div>
            </form>
        </div>
        
        <!-- Report Content -->
        <div class="card">
            <div class="card-header">
                <h2>Trip Report</h2>
                <p style="color: #666; font-size: 14px; margin-top: 5px;">
                    Period: <?php echo format_date($date_from) . ' - ' . format_date($date_to); ?>
                </p>
            </div>
            
            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-box">
                    <h3><?php echo $total_trips; ?></h3>
                    <p>Total Reservations</p>
                </div>
                
                <div class="stat-box">
                    <h3><?php echo $approved_trips; ?></h3>
                    <p>Approved Trips</p>
                </div>
                
                <div class="stat-box">
                    <h3><?php echo $pending_trips; ?></h3>
                    <p>Pending Trips</p>
                </div>
                
                <div class="stat-box">
                    <h3><?php echo $total_passengers; ?></h3>
                    <p>Total Passengers</p>
                </div>
            </div>
            
            <?php if (count($reservations) > 0): ?>
                <table class="table table-striped" id="reportTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Requester</th>
                            <th>Purpose</th>
                            <th>Destination</th>
                            <th>Bus</th>
                            <th>Driver</th>
                            <th>Passengers</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $res): ?>
                        <tr>
                            <td><?php echo format_date($res['reservation_date']); ?></td>
                            <td><?php echo format_time($res['reservation_time']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($res['user_name']); ?><br>
                                <small><?php echo htmlspecialchars($res['email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars(substr($res['purpose'], 0, 30)) . '...'; ?></td>
                            <td>
                                📍 <?php echo htmlspecialchars($res['destination']); ?>
                                <?php if ($res['return_date']): ?>
                                    <br><small style="color: #666;">Return: <?php echo format_date($res['return_date']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $res['bus_name'] ? htmlspecialchars($res['bus_name']) : 'N/A'; ?></td>
                            <td><?php echo $res['driver_name'] ? htmlspecialchars($res['driver_name']) : 'N/A'; ?></td>
                            <td><?php echo $res['passenger_count']; ?></td>
                            <td><?php echo get_status_badge($res['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">
                    <p style="text-align: center; color: #666; font-size: 13px;">
                        Report generated on <?php echo date('F d, Y g:i A'); ?> by <?php echo htmlspecialchars($user['name']); ?>
                    </p>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <p>No reservations found for the selected criteria</p>
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
        function exportToCSV() {
            const table = document.getElementById('reportTable');
            if (!table) {
                alert('No data to export');
                return;
            }
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [];
                const cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    let text = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + text + '"');
                }
                
                csv.push(row.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'WMSU_Bus_Report_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>