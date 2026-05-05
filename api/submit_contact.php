<?php
/**
 * api/submit_contact.php
 * Handles contact form submissions by saving to DB and sending email via Maton AI (Gmail Gateway).
 */
require_once __DIR__ . '/../config/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Configuration
$maton_api_key = 'v2.jFx5hwI_NHgLQhOhfwiJglY3qp9uWr-AApuvOJ5dNF_0-Pv4Digs5L6oCt76hBPpG_65Grjb5LG_t_Q5xcg2tCe987yQGgcpH50OSwjhUK9Ot1hMrRfVZkg2';
$connection_id = 'c868e80d-1951-417d-857b-1495a9c66f23';
$target_email  = 'bibinanshori@gmail.com';

// Get POST data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validation
if (empty($name) || empty($email) || empty($message)) {
    echo json_encode(['error' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Invalid email address format.']);
    exit;
}

try {
    // 1. Save to Database (Backup)
    $pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $stmt = $pdo->prepare("INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $message]);

    // 2. Send Email via Maton AI (Gmail API Proxy)
    
    // Construct MIME Message
    $subject = "Parkhere: New Message from $name";
    $mimeMessage = "To: $target_email\r\n";
    $mimeMessage .= "Subject: $subject\r\n";
    $mimeMessage .= "Reply-To: $email\r\n";
    $mimeMessage .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $mimeMessage .= "You have received new message from Parkhere website contact form\r\n\r\n";
    $mimeMessage .= "Name: $name\r\n";
    $mimeMessage .= "Email: $email\r\n";
    $mimeMessage .= "Message:\r\n$message\r\n";

    // Base64url encode the MIME message
    $raw = strtr(base64_encode($mimeMessage), ['+' => '-', '/' => '_', '=' => '']);

    // Send request to Maton AI
    $ch = curl_init('https://gateway.maton.ai/google-mail/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['raw' => $raw]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $maton_api_key,
        'X-Maton-Connection-Id: ' . $connection_id
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode([
            'success' => 'Thank you, ' . htmlspecialchars($name) . '! Your message has been sent successfully to our team.'
        ]);
    } else {
        // Log error but database save succeeded
        error_log("Maton AI Error ($httpCode): " . $response);
        echo json_encode([
            'success' => 'Message saved, but email notification failed. We will check it manually.',
            'debug' => $response // You can remove this in production
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while saving your message.']);
}
