<?php
// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Only set Content-Type for non-OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    header('Content-Type: application/json');
}

// Database connection parameters
$host = "localhost:3306";
$user = "rodnjrpf_mtmad"; // Replace with your MySQL username
$password = "H@le3ndSaka2"; // Replace with your MySQL password
$database = "rodnjrpf_madsense";

//  madsensense.tech 
// $host = "localhost:3306";
// $user = "frienef2_rsolomon"; // Replace with your MySQL username
// $password = "H@le3ndSaka2"; // Replace with your MySQL password
// $database = "frienef2_madsense";


// Error handling for database connection
try {
    $conn = new mysqli($host, $user, $password, $database);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
} catch (Exception $e) {
    // Log the error
    error_log('Database connection error: ' . $e->getMessage());
    
    // Return JSON error response
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}