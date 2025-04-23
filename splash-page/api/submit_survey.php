<?php
require_once 'config.php';

// Get the posted data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['name']) || !isset($data['email']) || !isset($data['question1_answer']) || !isset($data['question2_answer'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$name = $data['name'];
$email = $data['email'];
$ip_address = $_SERVER['REMOTE_ADDR'];
$question1_answer = $data['question1_answer'];
$question2_answer = $data['question2_answer'];

// Check if user has already submitted
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE ip_address = ?");
$stmt->bind_param("s", $ip_address);
$stmt->execute();
$result = $stmt->get_result();
$check = $result->fetch_assoc();

if ($check['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already submitted a response']);
    exit;
}

// Insert the survey response
$stmt = $conn->prepare("INSERT INTO survey_responses (name, email, ip_address, question1_answer, question2_answer) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $name, $email, $ip_address, $question1_answer, $question2_answer);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Survey submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit survey: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>