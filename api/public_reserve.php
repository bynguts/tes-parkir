<?php
/**
 * api/public_reserve.php — Handle public reservation requests
 */
require_once '../config/connection.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $plate_number = strtoupper(trim($_POST['plate_number'] ?? ''));
    $client_name  = trim($_POST['client_name'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    $vehicle_type = $_POST['vehicle_type'] ?? 'car';
    $from_iso     = $_POST['from'] ?? '';
    $until_iso    = $_POST['until'] ?? '';

    $slot_id_req  = $_POST['slot_id'] ?? null;

    if (empty($plate_number) || empty($client_name) || empty($client_phone) || empty($from_iso) || empty($until_iso)) {
        throw new Exception("All fields are required.");
    }

    // --- Strict Validation Logic ---
    
    // 1. Full Name Validation (Min 3 chars, letters/spaces only)
    if (strlen($client_name) < 3) {
        throw new Exception("Full name must be at least 3 characters.");
    }
    if (!preg_match("/^[a-zA-Z\s\']+$/", $client_name)) {
        throw new Exception("Full name contains invalid characters.");
    }

    // 2. Phone Number Validation (Indonesian format: 08... or +628...)
    // Allow spaces or dashes for flexibility during input, then strip them
    $clean_phone = str_replace([' ', '-'], '', $client_phone);
    if (!preg_match("/^(08|\+628)\d{8,12}$/", $clean_phone)) {
        throw new Exception("Invalid phone format. Must be Indonesian number starting with 08 or +628.");
    }
    $client_phone = $clean_phone;

    // 3. License Plate Validation (Indonesian standard: B 1234 ABC)
    // Format: [Area Code] [Number] [Suffix]
    if (!preg_match("/^[A-Za-z]{1,3}\s*\d{1,4}\s*[A-Za-z]{0,3}\s*$/", $plate_number)) {
        throw new Exception("Invalid license plate format. Use standard format (e.g., B 1234 XYZ).");
    }

    // Convert ISO dates to MySQL format
    $reserved_from  = date('Y-m-d H:i:s', strtotime($from_iso));
    $reserved_until = date('Y-m-d H:i:s', strtotime($until_iso));

    if (strtotime($reserved_until) <= strtotime($reserved_from)) {
        throw new Exception("Exit time must be after entry time.");
    }

    // 0. Check for duplicates (Plate, Phone, or Client Name)
    $stmt = $pdo->prepare("
        SELECT plate_number, client_phone, client_name 
        FROM reservation 
        WHERE (plate_number = ? OR client_phone = ? OR client_name = ?)
          AND status IN ('pending', 'confirmed')
        LIMIT 1
    ");
    $stmt->execute([$plate_number, $client_phone, $client_name]);
    $duplicate = $stmt->fetch();

    if ($duplicate) {
        if ($duplicate['plate_number'] === $plate_number) {
            throw new Exception("This license plate already has an active reservation.");
        }
        if ($duplicate['client_phone'] === $client_phone) {
            throw new Exception("This phone number is already linked to an active booking.");
        }
        if ($duplicate['client_name'] === $client_name) {
            throw new Exception("An active booking already exists under this name.");
        }
    }

    // 1. Find a slot that doesn't have an overlapping confirmed reservation
    if ($slot_id_req) {
        $stmt = $pdo->prepare("
            SELECT ps.slot_id, ps.slot_number, f.floor_code
            FROM parking_slot ps
            JOIN floor f ON ps.floor_id = f.floor_id
            WHERE ps.slot_id = ?
              AND ps.slot_type = ? 
              AND ps.status != 'maintenance'
              AND ps.slot_id NOT IN (
                  SELECT slot_id 
                  FROM reservation 
                  WHERE status IN ('pending', 'confirmed')
                    AND (
                        (reserved_from < ?) AND (reserved_until > ?)
                    )
              )
            LIMIT 1
        ");
        $stmt->execute([$slot_id_req, $vehicle_type, $reserved_until, $reserved_from]);
    } else {
        $stmt = $pdo->prepare("
            SELECT ps.slot_id, ps.slot_number, f.floor_code
            FROM parking_slot ps
            JOIN floor f ON ps.floor_id = f.floor_id
            WHERE ps.slot_type = ? 
              AND ps.status != 'maintenance'
              AND ps.slot_id NOT IN (
                  SELECT slot_id 
                  FROM reservation 
                  WHERE status IN ('pending', 'confirmed')
                    AND (
                        (reserved_from < ?) AND (reserved_until > ?)
                    )
              )
            ORDER BY ps.is_reservation_only DESC, ps.slot_id ASC
            LIMIT 1
        ");
        $stmt->execute([$vehicle_type, $reserved_until, $reserved_from]);
    }
    $slot = $stmt->fetch();

    if (!$slot) {
        throw new Exception("No slots available for the selected timeframe.");
    }

    $slot_id = $slot['slot_id'];
    $slot_number = $slot['slot_number'];
    $floor_code = $slot['floor_code'];

    // 2. Ensure vehicle exists or create it
    $stmt = $pdo->prepare("SELECT vehicle_id FROM vehicle WHERE plate_number = ?");
    $stmt->execute([$plate_number]);
    $v = $stmt->fetch();
    
    if ($v) {
        $vehicle_id = $v['vehicle_id'];
        // If logged in and vehicle has no customer_id, claim it
        if (!empty($_SESSION['customer_id'])) {
            $stmt = $pdo->prepare("UPDATE vehicle SET customer_id = ? WHERE vehicle_id = ? AND customer_id IS NULL");
            $stmt->execute([$_SESSION['customer_id'], $vehicle_id]);
        }
    } else {
        $customer_id = $_SESSION['customer_id'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO vehicle (plate_number, vehicle_type, owner_name, customer_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$plate_number, $vehicle_type, $client_name, $customer_id]);
        $vehicle_id = $pdo->lastInsertId();
    }

    // 3. Generate reservation code
    $reservation_code = generate_reservation_code($pdo);

    // 4. Insert reservation
    $customer_id = $_SESSION['customer_id'] ?? null;
    $stmt = $pdo->prepare("
        INSERT INTO reservation (
            customer_id, vehicle_id, plate_number, client_name, client_phone, slot_id, reservation_code, 
            reserved_from, reserved_until, status, is_public
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 1)
    ");
    
    $stmt->execute([
        $customer_id,
        $vehicle_id,
        $plate_number,
        $client_name,
        $client_phone,
        $slot_id,
        $reservation_code,
        $reserved_from,
        $reserved_until
    ]);

    // 5. Update slot status if the reservation starts within the next 15 minutes
    sync_slot_statuses($pdo);

    echo json_encode([
        'success' => true, 
        'reservation_code' => $reservation_code,
        'slot_id' => $slot_id,
        'slot_number' => $slot_number,
        'floor_code' => $floor_code,
        'client_name' => $client_name,
        'plate_number' => $plate_number,
        'vehicle_type' => $vehicle_type,
        'from' => $reserved_from,
        'until' => $reserved_until
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
