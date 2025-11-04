<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// Check if user is admin
require_admin();

$user = get_logged_user();

// Check if user data was retrieved successfully
if (!$user || !is_array($user)) {
    die("Error: Unable to retrieve user data. Please log in again.");
}

$success = '';
$errors = [];

$db = new Database();
$conn = $db->connect();

// Handle add bus
if (isset($_POST['add_bus'])) {
    $bus_name = clean_input($_POST['bus_name']);
    $plate_no = clean_input($_POST['plate_no']);
    $capacity = clean_input($_POST['capacity']);
    $status = clean_input($_POST['status']);
    
    if (empty($bus_name) || empty($plate_no)) {
        $errors[] = 'Bus name and plate number are required';
    } else {
        $stmt = $conn->prepare("INSERT INTO buses (bus_name, plate_no, capacity, status, deleted) VALUES (:bus_name, :plate_no, :capacity, :status, 0)");
        $stmt->bindParam(':bus_name', $bus_name);
        $stmt->bindParam(':plate_no', $plate_no);
        $stmt->bindParam(':capacity', $capacity);
        $stmt->bindParam(':status', $status);
        
        if ($stmt->execute()) {
            $success = 'Bus added successfully!';
        } else {
            $errors[] = 'Failed to add bus';
        }
    }
}

// Handle edit bus
if (isset($_POST['edit_bus'])) {
    $bus_id = clean_input($_POST['bus_id']);
    $bus_name = clean_input($_POST['bus_name']);
    $plate_no = clean_input($_POST['plate_no']);
    $capacity = clean_input($_POST['capacity']);
    $status = clean_input($_POST['status']);
    
    if (empty($bus_name) || empty($plate_no)) {
        $errors[] = 'Bus name and plate number are required';
    } else {
        $stmt = $conn->prepare("UPDATE buses SET bus_name = :bus_name, plate_no = :plate_no, capacity = :capacity, status = :status WHERE id = :id");
        $stmt->bindParam(':bus_name', $bus_name);
        $stmt->bindParam(':plate_no', $plate_no);
        $stmt->bindParam(':capacity', $capacity);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $bus_id);
        
        if ($stmt->execute()) {
            $success = 'Bus updated successfully!';
        } else {
            $errors[] = 'Failed to update bus';
        }
    }
}

// Handle soft delete bus
if (isset($_POST['delete_bus'])) {
    $bus_id = clean_input($_POST['bus_id']);
    
    // Check if bus has any active reservations
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE bus_id = :bus_id AND status IN ('pending', 'approved')");
    $stmt->bindParam(':bus_id', $bus_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['count'] > 0) {
        $errors[] = 'Cannot delete bus with active reservations';
    } else {
        // Soft delete - mark as deleted
        $stmt = $conn->prepare("UPDATE buses SET deleted = 1, deleted_at = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $bus_id);
        
        if ($stmt->execute()) {
            $success = 'Bus deleted successfully! You can restore it from the Deleted Buses section.';
        } else {
            $errors[] = 'Failed to delete bus';
        }
    }
}

// Handle restore bus
if (isset($_POST['restore_bus'])) {
    $bus_id = clean_input($_POST['bus_id']);
    
    $stmt = $conn->prepare("UPDATE buses SET deleted = 0, deleted_at = NULL WHERE id = :id");
    $stmt->bindParam(':id', $bus_id);
    
    if ($stmt->execute()) {
        $success = 'Bus restored successfully!';
    } else {
        $errors[] = 'Failed to restore bus';
    }
}

// Get all active buses with proper error handling
$buses = [];
try {
    $stmt = $conn->prepare("SELECT * FROM buses WHERE deleted = 0 ORDER BY bus_name");
    $stmt->execute();
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Error fetching buses: ' . $e->getMessage();
}

// Get all deleted buses
$deleted_buses = [];
try {
    $stmt = $conn->prepare("SELECT * FROM buses WHERE deleted = 1 ORDER BY deleted_at DESC");
    $stmt->execute();
    $deleted_buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Error fetching deleted buses: ' . $e->getMessage();
}

// Get edit bus if specified
$edit_bus = null;
if (isset($_GET['edit'])) {
    $edit_id = clean_input($_GET['edit']);
    try {
        $stmt = $conn->prepare("SELECT * FROM buses WHERE id = :id");
        $stmt->bindParam(':id', $edit_id);
        $stmt->execute();
        $edit_bus = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors[] = 'Error fetching bus details: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Buses - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
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
            <li><a href="buses.php" class="active">Buses</a></li>
            <li><a href="drivers.php">Drivers</a></li>
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
            <!-- Add/Edit Bus Form -->
            <div class="card">
                <div class="card-header">
                    <h2><?php echo ($edit_bus && is_array($edit_bus)) ? '✏️ Edit Bus' : '➕ Add New Bus'; ?></h2>
                </div>
                
                <form method="POST" action="">
                    <?php if ($edit_bus && is_array($edit_bus)): ?>
                        <input type="hidden" name="bus_id" value="<?php echo $edit_bus['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="bus_name">Bus Name <span class="required">*</span></label>
                        <input type="text" id="bus_name" name="bus_name" class="form-control" 
                               value="<?php echo ($edit_bus && is_array($edit_bus)) ? htmlspecialchars($edit_bus['bus_name']) : ''; ?>" 
                               placeholder="e.g., WMSU Bus 1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="plate_no">Plate Number <span class="required">*</span></label>
                        <input type="text" id="plate_no" name="plate_no" class="form-control" 
                               value="<?php echo ($edit_bus && is_array($edit_bus)) ? htmlspecialchars($edit_bus['plate_no']) : ''; ?>" 
                               placeholder="e.g., ABC-1234" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="capacity">Capacity</label>
                        <input type="number" id="capacity" name="capacity" class="form-control" 
                               min="1" max="60" value="<?php echo ($edit_bus && is_array($edit_bus)) ? $edit_bus['capacity'] : '30'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="available" <?php echo ($edit_bus && is_array($edit_bus) && $edit_bus['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="unavailable" <?php echo ($edit_bus && is_array($edit_bus) && $edit_bus['status'] == 'unavailable') ? 'selected' : ''; ?>>Unavailable</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <?php if ($edit_bus && is_array($edit_bus)): ?>
                            <button type="submit" name="edit_bus" class="btn btn-primary" 
                                    onclick="return confirm('Are you sure you want to update this bus?');">
                                Update Bus
                            </button>
                            <a href="buses.php" class="btn btn-secondary">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_bus" class="btn btn-primary" 
                                    onclick="return confirm('Are you sure you want to add this bus?');">
                                Add Bus
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Buses List -->
            <div class="card">
                <div class="card-header">
                    <h2>🚌 Buses List</h2>
                </div>
                
                <?php if (is_array($buses) && count($buses) > 0): ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Bus Name</th>
                                <th>Plate Number</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buses as $bus): ?>
                                <?php if (is_array($bus)): ?>
                                <tr>
                                    <td><?php echo isset($bus['id']) ? htmlspecialchars($bus['id']) : 'N/A'; ?></td>
                                    <td><?php echo isset($bus['bus_name']) ? htmlspecialchars($bus['bus_name']) : 'N/A'; ?></td>
                                    <td><?php echo isset($bus['plate_no']) ? htmlspecialchars($bus['plate_no']) : 'N/A'; ?></td>
                                    <td><?php echo isset($bus['capacity']) ? htmlspecialchars($bus['capacity']) : '0'; ?> passengers</td>
                                    <td>
                                        <span class="status-indicator <?php echo isset($bus['status']) ? $bus['status'] : 'unavailable'; ?>"></span>
                                        <?php echo isset($bus['status']) ? ucfirst($bus['status']) : 'Unknown'; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($bus['id'])): ?>
                                            <a href="buses.php?edit=<?php echo $bus['id']; ?>" class="btn btn-info" style="font-size: 12px; padding: 6px 12px;">Edit</a>
                                            
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this bus? You can restore it later if needed.');">
                                                <input type="hidden" name="bus_id" value="<?php echo $bus['id']; ?>">
                                                <button type="submit" name="delete_bus" class="btn btn-danger" style="font-size: 12px; padding: 6px 12px;">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p>No buses added yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Deleted Buses Section -->
        <?php if (is_array($deleted_buses) && count($deleted_buses) > 0): ?>
        <div class="deleted-section">
            <h3 style="color: #856404; margin-bottom: 15px;">🗑️ Deleted Buses (Can be Restored)</h3>
            <p style="color: #856404; margin-bottom: 15px;">These buses have been deleted but can be restored with one click.</p>
            
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bus Name</th>
                        <th>Plate Number</th>
                        <th>Capacity</th>
                        <th>Deleted On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deleted_buses as $bus): ?>
                        <?php if (is_array($bus)): ?>
                        <tr>
                            <td><?php echo isset($bus['id']) ? htmlspecialchars($bus['id']) : 'N/A'; ?></td>
                            <td><?php echo isset($bus['bus_name']) ? htmlspecialchars($bus['bus_name']) : 'N/A'; ?></td>
                            <td><?php echo isset($bus['plate_no']) ? htmlspecialchars($bus['plate_no']) : 'N/A'; ?></td>
                            <td><?php echo isset($bus['capacity']) ? htmlspecialchars($bus['capacity']) : '0'; ?> passengers</td>
                            <td><?php echo isset($bus['deleted_at']) ? date('M d, Y g:i A', strtotime($bus['deleted_at'])) : 'N/A'; ?></td>
                            <td>
                                <?php if (isset($bus['id'])): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to restore this bus?');">
                                        <input type="hidden" name="bus_id" value="<?php echo $bus['id']; ?>">
                                        <button type="submit" name="restore_bus" class="btn btn-success" style="font-size: 12px; padding: 6px 12px;">↩️ Restore</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
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