<?php
require_once 'config.php';

// Set appropriate headers
header('Content-Type: application/json');

// Add CORS headers if needed
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: GET');
// header('Access-Control-Allow-Headers: Content-Type');

// Get user's IP address
$ip_address = $_SERVER['REMOTE_ADDR'];

try {
    // Check if user has already submitted
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE ip_address = ?");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $ip_address);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $check = $result->fetch_assoc();
    
    // Return result
    echo json_encode(['completed' => ($check['count'] > 0), 'ip' => $ip_address]);
    
    $stmt->close();
} catch (Exception $e) {
    // Log the error
    error_log('Check completion error: ' . $e->getMessage());
    
    // Return an error response
    echo json_encode([
        'completed' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>