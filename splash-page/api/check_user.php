<?php
require_once 'config.php';

// Ensure proper request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get email and topic parameters from request
$email = isset($_GET['email']) ? $_GET['email'] : '';
$topic = isset($_GET['topic']) ? $_GET['topic'] : '';

// Validate email if provided
if (empty($email)) {
    echo json_encode(['success' => false, 'completed' => false, 'error' => 'Email is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'completed' => false, 'error' => 'Invalid email format']);
    exit;
}

try {
    if (!empty($topic)) {
        // Check if user has already submitted for this specific email + topic combination
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE email = ? AND question1_answer = ?");
        $stmt->bind_param("ss", $email, $topic);
        $stmt->execute();
        $result = $stmt->get_result();
        $check = $result->fetch_assoc();

        // Return result for specific topic
        echo json_encode([
            'success' => true,
            'completed' => ($check['count'] > 0),
            'email' => $email,
            'topic' => $topic
        ]);
    } else {
        // Check if user has submitted any survey (legacy support)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $check = $result->fetch_assoc();

        // Return result
        echo json_encode([
            'success' => true,
            'completed' => ($check['count'] > 0),
            'email' => $email
        ]);
    }

    $stmt->close();
} catch (Exception $e) {
    // Log the error
    error_log('Check user error: ' . $e->getMessage());
    
    // Return an error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'completed' => false,
        'error' => 'Server error occurred: ' . $e->getMessage()
    ]);
}

$conn->close();
?>