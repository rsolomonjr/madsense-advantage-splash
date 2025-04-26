<?php
// Include configuration file
require_once __DIR__ . '/config.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure proper request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get user's IP address with fallback options
$ip_address = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '0.0.0.0';

try {
    // Check if the table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'survey_responses'");
    if ($tableCheck->num_rows == 0) {
        // Create the table if it doesn't exist
        $createTable = "CREATE TABLE IF NOT EXISTS survey_responses (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            company VARCHAR(255) NOT NULL,
            job_title VARCHAR(255) NOT NULL,
            question1_answer VARCHAR(255) NOT NULL,
            question2_answer TEXT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($createTable)) {
            throw new Exception("Failed to create table: " . $conn->error);
        }
    }

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
    
    if (!$result) {
        throw new Exception("Result failed: " . $stmt->error);
    }
    
    $check = $result->fetch_assoc();
    
    // Return result
    echo json_encode([
        'success' => true, 
        'completed' => ($check['count'] > 0), 
        'ip' => $ip_address
    ]);
    
    $stmt->close();
} catch (Exception $e) {
    // Log the error
    error_log('Check completion error: ' . $e->getMessage());
    
    // Return an error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'completed' => false,
        'error' => 'Server error occurred: ' . $e->getMessage()
    ]);
}

// Close the database connection
$conn->close();