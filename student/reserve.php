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
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $purpose = isset($_POST['purpose']) ? clean_input($_POST['purpose']) : '';
    $destination = isset($_POST['destination']) ? clean_input($_POST['destination']) : '';
    $reservation_date = isset($_POST['reservation_date']) ? clean_input($_POST['reservation_date']) : '';
    $reservation_time = isset($_POST['reservation_time']) ? clean_input($_POST['reservation_time']) : '';
    $return_date = isset($_POST['return_date']) ? clean_input($_POST['return_date']) : null;
    $return_time = isset($_POST['return_time']) ? clean_input($_POST['return_time']) : null;
    $passenger_count = isset($_POST['passenger_count']) ? clean_input($_POST['passenger_count']) : '';
    $bus_id = isset($_POST['bus_id']) ? clean_input($_POST['bus_id']) : '';
    
    // Validation
    if (empty($purpose)) {
        $errors[] = 'Purpose is required';
    }
    
    if (empty($destination)) {
        $errors[] = 'Destination is required';
    }
    
    if (empty($reservation_date)) {
        $errors[] = 'Departure date is required';
    } else {
        // FIXED: Check if at least 3 FULL days (72 hours) in advance
        $now = new DateTime();
        $reservation_datetime = new DateTime($reservation_date . ' ' . ($reservation_time ?: '00:00:00'));
        
        // Calculate the difference
        $interval = $now->diff($reservation_datetime);
        
        // Convert to total hours
        $total_hours = ($interval->days * 24) + $interval->h + ($interval->i / 60);
        
        // Must be at least 72 hours (3 full days)
        if ($reservation_datetime <= $now) {
            $errors[] = 'Cannot reserve for past dates';
        } else if ($total_hours < 72) {
            $minimum_date = (clone $now)->add(new DateInterval('PT72H'));
            $errors[] = 'Reservations must be made at least 3 days (72 hours) in advance. Earliest available date: ' . $minimum_date->format('F d, Y g:i A');
        }
        
        if (is_sunday($reservation_date)) {
            $errors[] = 'Reservations on Sundays are not allowed';
        }
    }
    
    if (empty($reservation_time)) {
        $errors[] = 'Departure time is required';
    }
    
    // Validate return date and time (REQUIRED)
    if (empty($return_date)) {
        $errors[] = 'Return date is required - the bus needs to know when to pick you up';
    }
    
    if (empty($return_time)) {
        $errors[] = 'Return time is required - specify when to be picked up from destination';
    }
    
    if (!empty($return_date) && !empty($reservation_date)) {
        if (strtotime($return_date) < strtotime($reservation_date)) {
            $errors[] = 'Return date cannot be before departure date';
        }
        
        if (is_sunday($return_date)) {
            $errors[] = 'Return date cannot be on Sunday';
        }
        
        // If return is same day, return time must be after departure
        if ($return_date == $reservation_date && !empty($return_time)) {
            if (strtotime($return_time) <= strtotime($reservation_time)) {
                $errors[] = 'Return time must be after departure time on same-day trips';
            }
        }
    }
    
    if (empty($passenger_count) || $passenger_count < 1) {
        $errors[] = 'Valid passenger count is required';
    }
    
    if (empty($bus_id)) {
        $errors[] = 'Please select a bus';
    }
    
    // If no errors, create reservation (driver will be assigned by admin)
    if (empty($errors)) {
        $db = new Database();
        $conn = $db->connect();
        
        // Bus ID is provided, driver will be NULL (assigned by admin later)
        $sql = "INSERT INTO reservations (user_id, bus_id, driver_id, purpose, destination, reservation_date, reservation_time, return_date, return_time, passenger_count, status) 
                VALUES (:user_id, :bus_id, NULL, :purpose, :destination, :reservation_date, :reservation_time, :return_date, :return_time, :passenger_count, 'pending')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':bus_id', $bus_id);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->bindParam(':destination', $destination);
        $stmt->bindParam(':reservation_date', $reservation_date);
        $stmt->bindParam(':reservation_time', $reservation_time);
        $stmt->bindParam(':return_date', $return_date);
        $stmt->bindParam(':return_time', $return_time);
        $stmt->bindParam(':passenger_count', $passenger_count);
        
        if ($stmt->execute()) {
            $success = 'Reservation submitted successfully! Waiting for admin approval and driver assignment.';
            
            // Get bus details
            $stmt = $conn->prepare("SELECT * FROM buses WHERE id = :bus_id");
            $stmt->bindParam(':bus_id', $bus_id);
            $stmt->execute();
            $bus = $stmt->fetch();
            
            // Send email notification to admin
            $return_info = '';
            if ($return_date && $return_time) {
                $return_info = "<p><strong>Return:</strong> " . format_date($return_date) . " at " . format_time($return_time) . "</p>";
            }
            
            $email_message = "
                <h3>New Bus Reservation Request</h3>
                <p><strong>From:</strong> " . htmlspecialchars($user['name']) . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>
                <p><strong>Contact:</strong> " . htmlspecialchars($user['contact_no']) . "</p>
                <p><strong>Department:</strong> " . htmlspecialchars($user['department']) . "</p>
                <hr>
                <p><strong>Departure:</strong> " . format_date($reservation_date) . " at " . format_time($reservation_time) . "</p>
                {$return_info}
                <p><strong>Destination:</strong> " . htmlspecialchars($destination) . "</p>
                <p><strong>Purpose:</strong> " . htmlspecialchars($purpose) . "</p>
                <p><strong>Passengers:</strong> " . $passenger_count . "</p>
                <p><strong>Bus Requested:</strong> " . htmlspecialchars($bus['bus_name']) . " (" . htmlspecialchars($bus['plate_no']) . ")</p>
                <hr>
                <p><strong>Action Required:</strong> Please assign a driver and approve/reject this reservation.</p>
                <p><a href='" . SITE_URL . "admin/reservations.php'>View Reservation</a></p>
            ";
            
            send_email(ADMIN_EMAIL, 'New Bus Reservation Request - Driver Assignment Needed', $email_message);
        } else {
            $errors[] = 'Failed to submit reservation. Please try again.';
        }
    }
}

// Get all available buses (not deleted)
$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT * FROM buses WHERE (deleted = 0 OR deleted IS NULL) ORDER BY bus_name");
$stmt->execute();
$all_buses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Reservation - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-box ul {
            margin: 10px 0 0 20px;
            color: #1565c0;
        }
        
        .info-box ul li {
            margin: 5px 0;
        }
        
        .bus-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .bus-card {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            position: relative;
        }
        
        .bus-card:hover {
            border-color: var(--wmsu-maroon);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .bus-card.selected {
            border-color: var(--wmsu-maroon);
            background: #fff5f5;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
        }
        
        .bus-card.unavailable {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f5f5f5;
        }
        
        .bus-card.unavailable:hover {
            transform: none;
            box-shadow: none;
            border-color: #ddd;
        }
        
        .bus-card.checking {
            opacity: 0.7;
        }
        
        .bus-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .bus-name {
            font-weight: 600;
            color: var(--wmsu-maroon);
            font-size: 16px;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .bus-plate {
            font-size: 14px;
            color: #666;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .bus-capacity {
            font-size: 13px;
            color: #888;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .bus-status {
            text-align: center;
            padding: 5px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .bus-status.available {
            background: #d4edda;
            color: #155724;
        }
        
        .bus-status.unavailable {
            background: #f8d7da;
            color: #721c24;
        }
        
        .bus-status.checking {
            background: #fff3cd;
            color: #856404;
        }
        
        .bus-status.system-unavailable {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .availability-info {
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 5px;
            font-size: 11px;
            text-align: center;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .form-section h3 {
            color: var(--wmsu-maroon);
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .bus-selector {
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
            <li><a href="reserve.php" class="active">New Reservation</a></li>
            <li><a href="my_reservations.php">My Reservations</a></li>
            <li><a href="profile.php">Profile</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>🚌 New Bus Reservation</h2>
            </div>
            
            <div class="info-box">
                <strong>📋 Important Reservation Guidelines:</strong>
                <ul>
                    <li>Reservations must be made at least <strong>3 days (72 hours)</strong> in advance</li>
                    <li>Pickup location is always <strong>WMSU Campus, Normal Road, Baliwasan</strong></li>
                    <li>Specify your destination where the bus will drop you off</li>
                    <li><strong>Return date and time are required</strong> - the bus will pick you up and bring you back to WMSU</li>
                    <li>No reservations on Sundays (both departure and return)</li>
                    <li>Driver will be assigned by admin based on availability</li>
                    <li>You will receive email notification once your reservation is approved</li>
                </ul>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <span class="alert-close">&times;</span>
                </div>
                <div style="text-align: center; margin: 20px 0;">
                    <a href="my_reservations.php" class="btn btn-primary">View My Reservations</a>
                    <a href="reserve.php" class="btn btn-secondary">Make Another Reservation</a>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p>• <?php echo $error; ?></p>
                    <?php endforeach; ?>
                    <span class="alert-close">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST" action="" id="reservationForm">
                <!-- STEP 1: Select Bus -->
                <div class="form-section">
                    <h3>Step 1: Select a Bus</h3>
                    <p style="color: #666; margin-bottom: 15px;">Choose an available bus for your trip. Select your dates first to see which buses are available.</p>
                    
                    <div class="bus-selector">
                        <?php foreach ($all_buses as $bus): ?>
                        <div class="bus-card" 
                             data-bus-id="<?php echo $bus['id']; ?>"
                             data-bus-name="<?php echo htmlspecialchars($bus['bus_name']); ?>"
                             data-bus-status="<?php echo $bus['status']; ?>"
                             onclick="selectBus(<?php echo $bus['id']; ?>, '<?php echo htmlspecialchars($bus['bus_name']); ?>', '<?php echo $bus['status']; ?>')">
                            <div class="bus-icon">🚌</div>
                            <div class="bus-name"><?php echo htmlspecialchars($bus['bus_name']); ?></div>
                            <div class="bus-plate"><?php echo htmlspecialchars($bus['plate_no']); ?></div>
                            <div class="bus-capacity">Capacity: <?php echo $bus['capacity']; ?> passengers</div>
                            <div class="bus-status <?php echo $bus['status'] == 'unavailable' ? 'system-unavailable' : 'available'; ?>" id="status-<?php echo $bus['id']; ?>">
                                <?php echo $bus['status'] == 'unavailable' ? '⚠ System Unavailable' : '✓ Available'; ?>
                            </div>
                            <div class="availability-info" id="info-<?php echo $bus['id']; ?>">
                                <?php echo $bus['status'] == 'unavailable' ? 'Bus is disabled in system' : 'Select dates to check booking availability'; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" name="bus_id" id="selected_bus_id" value="">
                    <div id="bus-selection-error" style="color: var(--danger-red); margin-top: 10px; display: none;">
                        ⚠️ Please select a bus to continue
                    </div>
                </div>
                
                <!-- STEP 2: Trip Details -->
                <div class="form-section">
                    <h3>Step 2: Trip Information</h3>
                    
                    <div class="form-group">
                        <label for="purpose">Purpose of Reservation <span class="required">*</span></label>
                        <textarea id="purpose" name="purpose" class="form-control" rows="3" 
                                  placeholder="e.g., Educational field trip, Official business meeting, Research activity"><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="destination">Destination <span class="required">*</span></label>
                        <input type="text" id="destination" name="destination" class="form-control" 
                               value="<?php echo isset($_POST['destination']) ? htmlspecialchars($_POST['destination']) : ''; ?>" 
                               placeholder="e.g., Zamboanga City Museum, Fort Pilar, City Hall">
                        <small style="color: #666;">📍 Enter the exact location where you need to be dropped off</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="passenger_count">Number of Passengers <span class="required">*</span></label>
                        <input type="number" id="passenger_count" name="passenger_count" class="form-control" 
                               min="1" max="30" value="<?php echo isset($_POST['passenger_count']) ? htmlspecialchars($_POST['passenger_count']) : '1'; ?>">
                        <small style="color: #666;">Maximum capacity: 30 passengers per bus</small>
                    </div>
                </div>
                
                <!-- STEP 3: Schedule -->
                <div class="form-section">
                    <h3>Step 3: Schedule (From & To)</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reservation_date">Departure Date <span class="required">*</span></label>
                            <input type="date" id="reservation_date" name="reservation_date" class="form-control" 
                                   value="<?php echo isset($_POST['reservation_date']) ? htmlspecialchars($_POST['reservation_date']) : ''; ?>">
                            <small style="color: #666;">⏰ Must be at least 3 days (72 hours) from now</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="reservation_time">Departure Time <span class="required">*</span></label>
                            <input type="time" id="reservation_time" name="reservation_time" class="form-control" 
                                   value="<?php echo isset($_POST['reservation_time']) ? htmlspecialchars($_POST['reservation_time']) : ''; ?>">
                            <small style="color: #666;">⏰ Time to depart from WMSU</small>
                        </div>
                    </div>
                    
                    <hr style="margin: 20px 0;">
                    
                    <p style="color: #666; margin-bottom: 15px;">
                        <strong>Return Trip (Required):</strong> Specify when the bus should pick you up and return to WMSU.
                    </p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="return_date">Return Date <span class="required">*</span></label>
                            <input type="date" id="return_date" name="return_date" class="form-control" 
                                   value="<?php echo isset($_POST['return_date']) ? htmlspecialchars($_POST['return_date']) : ''; ?>" required>
                            <small style="color: #666;">📅 Date to return to WMSU</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="return_time">Return Time <span class="required">*</span></label>
                            <input type="time" id="return_time" name="return_time" class="form-control" 
                                   value="<?php echo isset($_POST['return_time']) ? htmlspecialchars($_POST['return_time']) : ''; ?>" required>
                            <small style="color: #666;">⏰ Time to be picked up from destination</small>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block" id="submitBtn">Submit Reservation</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> Western Mindanao State University. All rights reserved.</p>
    </footer>

    <script src="../js/main.js"></script>
    <script>
        let selectedBusId = null;
        let selectedBusName = '';
        let busAvailabilityData = {};
        
        // Set minimum date attribute - 3 days from today
        const minDateInput = new Date();
        minDateInput.setDate(minDateInput.getDate() + 3);
        const minDateStr = minDateInput.toISOString().split('T')[0];
        document.getElementById('reservation_date').setAttribute('min', minDateStr);
        
        function checkAllBusesAvailability() {
            const dateInput = document.getElementById('reservation_date').value;
            const returnDateInput = document.getElementById('return_date').value;
            
            // IMPORTANT: Don't check if EITHER date is missing
            if (!dateInput || !returnDateInput) {
                // Reset all buses to initial state
                document.querySelectorAll('.bus-card').forEach(card => {
                    const busId = card.getAttribute('data-bus-id');
                    const busStatus = card.getAttribute('data-bus-status');
                    const statusDiv = document.getElementById('status-' + busId);
                    const infoDiv = document.getElementById('info-' + busId);
                    
                    card.classList.remove('unavailable', 'checking');
                    
                    if (busStatus === 'unavailable') {
                        card.classList.add('unavailable');
                        if (statusDiv) {
                            statusDiv.textContent = '⚠ System Unavailable';
                            statusDiv.className = 'bus-status system-unavailable';
                        }
                        if (infoDiv) {
                            infoDiv.textContent = 'Bus is disabled in system';
                        }
                    } else {
                        if (statusDiv) {
                            statusDiv.textContent = '✓ Available';
                            statusDiv.className = 'bus-status available';
                        }
                        if (infoDiv) {
                            if (!dateInput) {
                                infoDiv.textContent = 'Select departure date first';
                            } else if (!returnDateInput) {
                                infoDiv.textContent = 'Select return date to check availability';
                            } else {
                                infoDiv.textContent = 'Select dates to check availability';
                            }
                        }
                    }
                });
                return;
            }
            
            // Show checking status
            document.querySelectorAll('.bus-card').forEach(card => {
                const busId = card.getAttribute('data-bus-id');
                const busStatus = card.getAttribute('data-bus-status');
                
                if (busStatus !== 'unavailable') {
                    card.classList.add('checking');
                    const statusDiv = document.getElementById('status-' + busId);
                    if (statusDiv) {
                        statusDiv.textContent = '⏳ Checking...';
                        statusDiv.className = 'bus-status checking';
                    }
                }
            });
            
            // Check each bus
            document.querySelectorAll('.bus-card').forEach(card => {
                const busId = card.getAttribute('data-bus-id');
                const busStatus = card.getAttribute('data-bus-status');
                
                if (busStatus === 'unavailable') {
                    return; // Skip system-unavailable buses
                }
                
                let url = '../api/check_availability.php?date=' + encodeURIComponent(dateInput) + '&bus_id=' + busId;
                if (returnDateInput) {
                    url += '&return_date=' + encodeURIComponent(returnDateInput);
                }
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        busAvailabilityData[busId] = data;
                        
                        card.classList.remove('checking');
                        const statusDiv = document.getElementById('status-' + busId);
                        const infoDiv = document.getElementById('info-' + busId);
                        
                        if (data.available) {
                            card.classList.remove('unavailable');
                            if (statusDiv) {
                                statusDiv.textContent = '✓ Available';
                                statusDiv.className = 'bus-status available';
                            }
                            if (infoDiv) {
                                infoDiv.textContent = '✓ Available for your dates';
                                infoDiv.style.color = 'green';
                            }
                        } else {
                            card.classList.add('unavailable');
                            if (statusDiv) {
                                statusDiv.textContent = '✗ Not Available';
                                statusDiv.className = 'bus-status unavailable';
                            }
                            if (infoDiv) {
                                infoDiv.textContent = '✗ ' + (data.message || 'Booked by another user');
                                infoDiv.style.color = 'red';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error checking bus ' + busId + ':', error);
                        card.classList.remove('checking');
                    });
            });
        }
        
        function selectBus(busId, busName, systemStatus) {
            const card = event.currentTarget;
            
            // Check if system unavailable
            if (systemStatus === 'unavailable') {
                alert('⚠️ This bus is currently unavailable in the system.\n\nPlease select another bus or contact the administrator.');
                return;
            }
            
            // Check if dates are selected
            const dateInput = document.getElementById('reservation_date').value;
            const returnDateInput = document.getElementById('return_date').value;
            
            if (!dateInput || !returnDateInput) {
                alert('⚠️ Please select both departure and return dates first to check bus availability.');
                return;
            }
            
            // Check if this bus is available for the selected dates
            if (card.classList.contains('unavailable')) {
                const availData = busAvailabilityData[busId];
                let reason = 'This bus is not available on your selected dates.';
                
                if (availData && availData.message) {
                    reason = availData.message;
                }
                
                alert('❌ Bus Not Available\n\n' + busName + ' is not available.\n\nReason: ' + reason + '\n\nPlease:\n• Choose another bus, OR\n• Select different dates');
                return;
            }
            
            // Remove selection from all buses
            document.querySelectorAll('.bus-card').forEach(c => {
                c.classList.remove('selected');
            });
            
            // Add selection to clicked bus
            card.classList.add('selected');
            
            // Set hidden input
            document.getElementById('selected_bus_id').value = busId;
            selectedBusId = busId;
            selectedBusName = busName;
            
            // Hide error
            document.getElementById('bus-selection-error').style.display = 'none';
            
            console.log('✅ Selected:', busName, '(ID:', busId, ')');
        }
        
        // Check availability when dates change
        document.getElementById('reservation_date').addEventListener('change', function() {
            const dateStr = this.value;
            
            if (!dateStr) return;
            
            // Parse the selected date
            const selectedDate = new Date(dateStr + 'T00:00:00');
            
            // Check if Sunday only
            if (selectedDate.getDay() === 0) {
                alert('❌ Sundays Not Allowed\n\nReservations on Sundays are not allowed.\n\nPlease choose Monday-Saturday.');
                this.value = '';
                return;
            }
            
            // Clear bus selection when date changes
            selectedBusId = null;
            selectedBusName = '';
            document.getElementById('selected_bus_id').value = '';
            document.querySelectorAll('.bus-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Check all buses availability
            checkAllBusesAvailability();
        });
        
        // Also check when return date changes
        document.getElementById('return_date').addEventListener('change', function() {
            const departDate = document.getElementById('reservation_date').value;
            const returnDate = this.value;
            
            if (!departDate) {
                alert('⚠️ Please select departure date first');
                this.value = '';
                return;
            }
            
            if (returnDate && new Date(returnDate) < new Date(departDate)) {
                alert('❌ Invalid Return Date\n\nReturn date cannot be before departure date.');
                this.value = '';
                return;
            }
            
            // Check if Sunday
            const returnDateObj = new Date(returnDate + 'T00:00:00');
            if (returnDateObj.getDay() === 0) {
                alert('❌ Sundays Not Allowed\n\nReturn date cannot be on Sunday.\n\nPlease choose Monday-Saturday.');
                this.value = '';
                return;
            }
            
            // Re-check all buses availability with new return date
            checkAllBusesAvailability();
        });
        
        // Form validation before submit
        document.getElementById('reservationForm').addEventListener('submit', function(e) {
            if (!selectedBusId) {
                e.preventDefault();
                document.getElementById('bus-selection-error').style.display = 'block';
                alert('⚠️ Bus Selection Required\n\nPlease select a bus before submitting your reservation.\n\nMake sure to:\n1. Select departure and return dates\n2. Choose an available bus');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
            
            const date = document.getElementById('reservation_date').value;
            const time = document.getElementById('reservation_time').value;
            const purpose = document.getElementById('purpose').value;
            const destination = document.getElementById('destination').value;
            
            if (!purpose || !destination || !date || !time) {
                e.preventDefault();
                alert('⚠️ Missing Required Fields\n\nPlease fill in all required fields before submitting.');
                return false;
            }
            
            const returnDate = document.getElementById('return_date').value;
            const returnTime = document.getElementById('return_time').value;
            
            if (!returnDate || !returnTime) {
                alert('⚠️ Return Information Required\n\nReturn date and time are required.\n\nThe bus needs to know when to pick you up from your destination.');
                e.preventDefault();
                return false;
            }
            
            // PROPER 72-hour validation using BOTH date AND time
            const selectedDateTime = new Date(date + 'T' + time);
            const now = new Date();
            const diffMs = selectedDateTime - now;
            const diffHours = diffMs / (1000 * 60 * 60);
            
            console.log('=== FORM SUBMIT VALIDATION ===');
            console.log('Current time:', now.toLocaleString());
            console.log('Selected departure:', selectedDateTime.toLocaleString());
            console.log('Hours difference:', diffHours.toFixed(2));
            
            if (diffHours < 72) {
                const hoursNeeded = (72 - diffHours).toFixed(2);
                alert('❌ Too Soon!\n\n' +
                      'Reservations must be made at least 72 hours in advance.\n\n' +
                      '⏰ Current time: ' + now.toLocaleString() + '\n' +
                      '📅 Your departure: ' + selectedDateTime.toLocaleString() + '\n' +
                      '⏱️ Time difference: ' + diffHours.toFixed(2) + ' hours\n\n' +
                      '❗ You need ' + hoursNeeded + ' more hours.\n\n' +
                      'Earliest booking: ' + new Date(now.getTime() + 72*60*60*1000).toLocaleString());
                e.preventDefault();
                return false;
            }
            
            // Confirm submission
            let confirmMsg = '✅ Confirm Your Reservation\n\n';
            confirmMsg += '🚌 Bus: ' + selectedBusName + '\n';
            confirmMsg += '📅 Departure: ' + date + ' at ' + time + '\n';
            confirmMsg += '📍 Destination: ' + destination + '\n';
            confirmMsg += '🔄 Return: ' + returnDate + ' at ' + returnTime + '\n';
            confirmMsg += '\n✅ Bus will depart from and return to WMSU\n';
            confirmMsg += '\nProceed with reservation?';
            
            if (!confirm(confirmMsg)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>