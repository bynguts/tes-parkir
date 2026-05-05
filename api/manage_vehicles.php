<?php
/**
 * api/manage_vehicles.php — Customer Vehicle Management
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/connection.php';
require_once '../includes/functions.php';

// Auth check
if (empty($_SESSION['customer_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

$customer_id = $_SESSION['customer_id'];
$action      = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $plate = strtoupper(trim($_POST['plate_number'] ?? ''));
        $type  = $_POST['vehicle_type'] ?? 'car';

        // Plate regex validation
        $plateRegex = '/^[A-Z]{1,2}\s\d{1,4}\s[A-Z]{1,3}$/';

        if (empty($plate)) {
            header('Location: ../account.php?error=Plate number required');
            exit;
        }

        if (!preg_match($plateRegex, $plate)) {
            header('Location: ../account.php?error=Invalid License Plate format (e.g. B 1234 ABC)');
            exit;
        }

        // Check if vehicle already exists in the system
        $stmt = $pdo->prepare("SELECT vehicle_id FROM vehicle WHERE plate_number = ?");
        $stmt->execute([$plate]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update the existing vehicle to belong to this customer
            $stmt = $pdo->prepare("UPDATE vehicle SET customer_id = ?, vehicle_type = ? WHERE vehicle_id = ?");
            $stmt->execute([$customer_id, $type, $existing['vehicle_id']]);
        } else {
            // Insert new vehicle
            $stmt = $pdo->prepare("INSERT INTO vehicle (plate_number, vehicle_type, customer_id) VALUES (?, ?, ?)");
            $stmt->execute([$plate, $type, $customer_id]);
        }

        header('Location: ../account.php?success=Vehicle added');
        exit;
    }
}

header('Location: ../account.php');
