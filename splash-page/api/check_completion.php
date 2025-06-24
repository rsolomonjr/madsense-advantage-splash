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

// Get parameters from request
$email = isset($_GET['email']) ? $_GET['email'] : '';
$topic = isset($_GET['topic']) ? $_GET['topic'] : '';
$getTopics = isset($_GET['get_topics']) ? $_GET['get_topics'] : '';

// Validate email if provided
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

try {
    // Check if the table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'survey_responses'");
    if ($tableCheck->num_rows == 0) {
        // Create the table if it doesn't exist - updated to include topic tracking
        $createTable = "CREATE TABLE IF NOT EXISTS survey_responses (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            company VARCHAR(255) NOT NULL,
            job_title VARCHAR(255) NOT NULL,
            question1_answer VARCHAR(255) NOT NULL,
            question2_answer TEXT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_email_topic (email, question1_answer)
        )";
        
        if (!$conn->query($createTable)) {
            throw new Exception("Failed to create table: " . $conn->error);
        }
    } else {
        // Check if the table has the unique constraint, add it if not
        $constraintCheck = $conn->query("SHOW INDEX FROM survey_responses WHERE Key_name = 'unique_email_topic'");
        if ($constraintCheck->num_rows == 0) {
            // Add unique constraint for email + topic combination
            $addConstraint = "ALTER TABLE survey_responses ADD UNIQUE KEY unique_email_topic (email, question1_answer)";
            if (!$conn->query($addConstraint)) {
                // If constraint fails due to existing duplicates, log but continue
                error_log("Could not add unique constraint - may have duplicate data: " . $conn->error);
            }
        }
    }

    if (!empty($email)) {
        if ($getTopics === '1') {
            // Return all completed topics for this email
            $stmt = $conn->prepare("SELECT question1_answer FROM survey_responses WHERE email = ?");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $email);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if (!$result) {
                throw new Exception("Result failed: " . $stmt->error);
            }
            
            $completedTopics = [];
            while ($row = $result->fetch_assoc()) {
                $completedTopics[] = $row['question1_answer'];
            }
            
            echo json_encode([
                'success' => true,
                'completed_topics' => $completedTopics,
                'email' => $email
            ]);
            
            $stmt->close();
        } elseif (!empty($topic)) {
            // Check if specific email + topic combination exists
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE email = ? AND question1_answer = ?");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("ss", $email, $topic);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if (!$result) {
                throw new Exception("Result failed: " . $stmt->error);
            }
            
            $check = $result->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'completed' => ($check['count'] > 0),
                'email' => $email,
                'topic' => $topic
            ]);
            
            $stmt->close();
        } else {
            // Check if email exists at all (legacy support)
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE email = ?");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $email);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if (!$result) {
                throw new Exception("Result failed: " . $stmt->error);
            }
            
            $check = $result->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'completed' => ($check['count'] > 0),
                'email' => $email
            ]);
            
            $stmt->close();
        }
    } else {
        // If no email provided, just return success without completion status
        echo json_encode([
            'success' => true,
            'completed' => false,
            'email' => ''
        ]);
    }
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
?>