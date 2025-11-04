<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Validate required parameters
if (!isset($_GET['date']) || !isset($_GET['bus_id'])) {
    echo json_encode([
        'error' => 'Missing required parameters: date and bus_id',
        'available' => false
    ]);
    exit;
}

$date = clean_input($_GET['date']);
$bus_id = (int)clean_input($_GET['bus_id']);
$return_date = isset($_GET['return_date']) && !empty($_GET['return_date']) ? clean_input($_GET['return_date']) : null;

$db = new Database();
$conn = $db->connect();

try {
    // STEP 1: Check if bus exists and is enabled in system
    $stmt = $conn->prepare("SELECT id, bus_name, plate_no, status FROM buses WHERE id = :bus_id");
    $stmt->bindParam(':bus_id', $bus_id, PDO::PARAM_INT);
    $stmt->execute();
    $bus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bus) {
        echo json_encode([
            'available' => false,
            'error' => 'Bus not found',
            'bus_id' => $bus_id,
            'message' => 'Bus does not exist in system'
        ]);
        exit;
    }
    
    // Check if bus is disabled in system
    if ($bus['status'] === 'unavailable') {
        echo json_encode([
            'available' => false,
            'bus_id' => $bus_id,
            'bus_name' => $bus['bus_name'],
            'date' => $date,
            'return_date' => $return_date,
            'message' => 'Bus is currently disabled in system by administrator'
        ]);
        exit;
    }
    
    // STEP 2: Check for booking conflicts
    if ($return_date && $return_date !== $date) {
        // ROUND TRIP (multi-day or same day with return)
        // Bus is unavailable if:
        // - Any existing reservation uses this bus on ANY day between our departure and return
        
        $sql = "
            SELECT 
                id,
                reservation_date,
                return_date,
                COALESCE(return_date, reservation_date) as effective_return
            FROM reservations 
            WHERE bus_id = :bus_id 
            AND status IN ('pending', 'approved')
            AND (
                -- Existing reservation's departure is within our trip period
                (reservation_date BETWEEN :start_date AND :end_date)
                OR
                -- Existing reservation's return is within our trip period  
                (COALESCE(return_date, reservation_date) BETWEEN :start_date AND :end_date)
                OR
                -- Our trip is completely within existing reservation period
                (reservation_date <= :start_date AND COALESCE(return_date, reservation_date) >= :end_date)
            )
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':bus_id', $bus_id, PDO::PARAM_INT);
        $stmt->bindParam(':start_date', $date, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $return_date, PDO::PARAM_STR);
    } else {
        // ONE-WAY or SAME DAY TRIP (no return date OR return date same as departure)
        // Bus is unavailable if there's any reservation on this specific date
        
        $sql = "
            SELECT 
                id,
                reservation_date,
                return_date,
                COALESCE(return_date, reservation_date) as effective_return
            FROM reservations 
            WHERE bus_id = :bus_id 
            AND status IN ('pending', 'approved')
            AND (
                -- Check if the requested date falls within any existing reservation
                :check_date BETWEEN reservation_date AND COALESCE(return_date, reservation_date)
            )
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':bus_id', $bus_id, PDO::PARAM_INT);
        $stmt->bindParam(':check_date', $date, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $conflict_count = count($conflicts);
    
    $is_available = ($conflict_count === 0);
    
    // Build detailed message
    $message = '';
    if ($is_available) {
        if ($return_date && $return_date !== $date) {
            $message = "Bus is available from {$date} to {$return_date}";
        } else {
            $message = "Bus is available on {$date}";
        }
    } else {
        $conflict_dates = array_map(function($c) {
            $ret = $c['return_date'] ? $c['return_date'] : $c['reservation_date'];
            if ($c['reservation_date'] === $ret) {
                return $c['reservation_date'];
            }
            return $c['reservation_date'] . ' to ' . $ret;
        }, $conflicts);
        
        $message = "Bus is already booked on: " . implode(', ', $conflict_dates);
    }
    
    // Return response
    echo json_encode([
        'available' => $is_available,
        'bus_id' => $bus_id,
        'bus_name' => $bus['bus_name'],
        'date' => $date,
        'return_date' => $return_date,
        'conflict_count' => $conflict_count,
        'conflicts' => $conflicts,
        'message' => $message,
        'debug' => [
            'query_type' => $return_date && $return_date !== $date ? 'round_trip' : 'one_way',
            'check_date' => $date,
            'check_return' => $return_date
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'available' => false,
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'bus_id' => $bus_id,
        'date' => $date,
        'return_date' => $return_date
    ]);
}
?>