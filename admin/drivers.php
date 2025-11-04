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

// Handle add driver
if (isset($_POST['add_driver'])) {
    $name = clean_input($_POST['name']);
    $contact_no = clean_input($_POST['contact_no']);
    $license_no = clean_input($_POST['license_no']);
    $assigned_bus_id = !empty($_POST['assigned_bus_id']) ? clean_input($_POST['assigned_bus_id']) : null;
    $status = clean_input($_POST['status']);
    
    if (empty($name)) {
        $errors[] = 'Driver name is required';
    } else {
        $stmt = $conn->prepare("INSERT INTO drivers (name, contact_no, license_no, assigned_bus_id, status, deleted) 
                                VALUES (:name, :contact_no, :license_no, :assigned_bus_id, :status, 0)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':contact_no', $contact_no);
        $stmt->bindParam(':license_no', $license_no);
        $stmt->bindParam(':assigned_bus_id', $assigned_bus_id);
        $stmt->bindParam(':status', $status);
        
        if ($stmt->execute()) {
            $success = 'Driver added successfully!';
        } else {
            $errors[] = 'Failed to add driver';
        }
    }
}

// Handle edit driver
if (isset($_POST['edit_driver'])) {
    $driver_id = clean_input($_POST['driver_id']);
    $name = clean_input($_POST['name']);
    $contact_no = clean_input($_POST['contact_no']);
    $license_no = clean_input($_POST['license_no']);
    $assigned_bus_id = !empty($_POST['assigned_bus_id']) ? clean_input($_POST['assigned_bus_id']) : null;
    $status = clean_input($_POST['status']);
    
    if (empty($name)) {
        $errors[] = 'Driver name is required';
    } else {
        $stmt = $conn->prepare("UPDATE drivers SET name = :name, contact_no = :contact_no, license_no = :license_no, 
                                assigned_bus_id = :assigned_bus_id, status = :status WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':contact_no', $contact_no);
        $stmt->bindParam(':license_no', $license_no);
        $stmt->bindParam(':assigned_bus_id', $assigned_bus_id);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $driver_id);
        
        if ($stmt->execute()) {
            $success = 'Driver updated successfully!';
        } else {
            $errors[] = 'Failed to update driver';
        }
    }
}

// Handle soft delete driver
if (isset($_POST['delete_driver'])) {
    $driver_id = clean_input($_POST['driver_id']);
    
    // Check if driver has any active reservations
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE driver_id = :driver_id AND status IN ('pending', 'approved')");
    $stmt->bindParam(':driver_id', $driver_id);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        $errors[] = 'Cannot delete driver with active reservations';
    } else {
        // Soft delete - mark as deleted
        $stmt = $conn->prepare("UPDATE drivers SET deleted = 1, deleted_at = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $driver_id);
        
        if ($stmt->execute()) {
            $success = 'Driver deleted successfully! You can restore it from the Deleted Drivers section.';
        } else {
            $errors[] = 'Failed to delete driver';
        }
    }
}

// Handle restore driver
if (isset($_POST['restore_driver'])) {
    $driver_id = clean_input($_POST['driver_id']);
    
    $stmt = $conn->prepare("UPDATE drivers SET deleted = 0, deleted_at = NULL WHERE id = :id");
    $stmt->bindParam(':id', $driver_id);
    
    if ($stmt->execute()) {
        $success = 'Driver restored successfully!';
    } else {
        $errors[] = 'Failed to restore driver';
    }
}

// Get all active drivers
$stmt = $conn->prepare("SELECT d.*, b.bus_name, b.plate_no 
                        FROM drivers d 
                        LEFT JOIN buses b ON d.assigned_bus_id = b.id 
                        WHERE d.deleted = 0
                        ORDER BY d.name");
$stmt->execute();
$drivers = $stmt->fetchAll();

// Get all deleted drivers
$stmt = $conn->prepare("SELECT d.*, b.bus_name, b.plate_no 
                        FROM drivers d 
                        LEFT JOIN buses b ON d.assigned_bus_id = b.id 
                        WHERE d.deleted = 1
                        ORDER BY d.deleted_at DESC");
$stmt->execute();
$deleted_drivers = $stmt->fetchAll();

// Get all buses for dropdown
$stmt = $conn->prepare("SELECT * FROM buses WHERE deleted = 0 ORDER BY bus_name");
$stmt->execute();
$buses = $stmt->fetchAll();

// Get edit driver if specified
$edit_driver = null;
if (isset($_GET['edit'])) {
    $edit_id = clean_input($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM drivers WHERE id = :id");
    $stmt->bindParam(':id', $edit_id);
    $stmt->execute();
    $edit_driver = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Drivers - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-indicator.available {
            background: var(--success-green);
        }
        
        .status-indicator.unavailable {
            background: var(--danger-red);
        }
        
        .deleted-section {
            margin-top: 30px;
            padding: 20px;
            background: #fff3cd;
            border-radius: 8px;
            border: 2px solid #ffc107;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-content">
            <div class="logo-section">
                <img src="../images/wmsu.png" alt="WMSU Logo" onerror="this.style.display='none'">
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
            <li><a href="drivers.php" class="active">Drivers</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="users.php">Users</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="container">
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
        
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
            <!-- Add/Edit Driver Form -->
            <div class="card">
                <div class="card-header">
                    <h2><?php echo $edit_driver ? '✏️ Edit Driver' : '➕ Add New Driver'; ?></h2>
                </div>
                
                <form method="POST" action="">
                    <?php if ($edit_driver): ?>
                        <input type="hidden" name="driver_id" value="<?php echo $edit_driver['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Driver Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo $edit_driver ? htmlspecialchars($edit_driver['name']) : ''; ?>" 
                               placeholder="e.g., Juan Dela Cruz" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_no">Contact Number</label>
                        <input type="text" id="contact_no" name="contact_no" class="form-control" 
                               value="<?php echo $edit_driver ? htmlspecialchars($edit_driver['contact_no']) : ''; ?>" 
                               placeholder="09XXXXXXXXX">
                    </div>
                    
                    <div class="form-group">
                        <label for="license_no">License Number</label>
                        <input type="text" id="license_no" name="license_no" class="form-control" 
                               value="<?php echo $edit_driver ? htmlspecialchars($edit_driver['license_no']) : ''; ?>" 
                               placeholder="e.g., N01-12-123456">
                    </div>
                    
                    <div class="form-group">
                        <label for="assigned_bus_id">Assign to Bus (Optional)</label>
                        <select id="assigned_bus_id" name="assigned_bus_id" class="form-control">
                            <option value="">-- No Bus Assigned --</option>
                            <?php foreach ($buses as $bus): ?>
                                <option value="<?php echo $bus['id']; ?>" 
                                        <?php echo ($edit_driver && $edit_driver['assigned_bus_id'] == $bus['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bus['bus_name']) . ' (' . htmlspecialchars($bus['plate_no']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="available" <?php echo ($edit_driver && $edit_driver['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="unavailable" <?php echo ($edit_driver && $edit_driver['status'] == 'unavailable') ? 'selected' : ''; ?>>Unavailable</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <?php if ($edit_driver): ?>
                            <button type="submit" name="edit_driver" class="btn btn-primary" 
                                    onclick="return confirm('Are you sure you want to update this driver?');">
                                Update Driver
                            </button>
                            <a href="drivers.php" class="btn btn-secondary">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_driver" class="btn btn-primary" 
                                    onclick="return confirm('Are you sure you want to add this driver?');">
                                Add Driver
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Drivers List -->
            <div class="card">
                <div class="card-header">
                    <h2>👨‍✈️ Drivers List</h2>
                </div>
                
                <?php if (count($drivers) > 0): ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>License No.</th>
                                <th>Assigned Bus</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drivers as $driver): ?>
                            <tr>
                                <td><?php echo $driver['id']; ?></td>
                                <td><?php echo htmlspecialchars($driver['name']); ?></td>
                                <td><?php echo htmlspecialchars($driver['contact_no'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($driver['license_no'] ?: 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    if ($driver['bus_name']) {
                                        echo htmlspecialchars($driver['bus_name']) . '<br><small>' . htmlspecialchars($driver['plate_no']) . '</small>';
                                    } else {
                                        echo '<em>Not assigned</em>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="status-indicator <?php echo $driver['status']; ?>"></span>
                                    <?php echo ucfirst($driver['status']); ?>
                                </td>
                                <td>
                                    <a href="drivers.php?edit=<?php echo $driver['id']; ?>" class="btn btn-info" style="font-size: 12px; padding: 6px 12px;">Edit</a>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this driver? You can restore it later if needed.');">
                                        <input type="hidden" name="driver_id" value="<?php echo $driver['id']; ?>">
                                        <button type="submit" name="delete_driver" class="btn btn-danger" style="font-size: 12px; padding: 6px 12px;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p>No drivers added yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Deleted Drivers Section -->
        <?php if (count($deleted_drivers) > 0): ?>
        <div class="deleted-section">
            <h3 style="color: #856404; margin-bottom: 15px;">🗑️ Deleted Drivers (Can be Restored)</h3>
            <p style="color: #856404; margin-bottom: 15px;">These drivers have been deleted but can be restored with one click.</p>
            
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>License No.</th>
                        <th>Deleted On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deleted_drivers as $driver): ?>
                    <tr>
                        <td><?php echo $driver['id']; ?></td>
                        <td><?php echo htmlspecialchars($driver['name']); ?></td>
                        <td><?php echo htmlspecialchars($driver['contact_no'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($driver['license_no'] ?: 'N/A'); ?></td>
                        <td><?php echo date('M d, Y g:i A', strtotime($driver['deleted_at'])); ?></td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to restore this driver?');">
                                <input type="hidden" name="driver_id" value="<?php echo $driver['id']; ?>">
                                <button type="submit" name="restore_driver" class="btn btn-success" style="font-size: 12px; padding: 6px 12px;">↩️ Restore</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>

    <script src="../js/main.js"></script>
</body>
</html>