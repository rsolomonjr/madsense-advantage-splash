<?php
require_once 'config.php';

// Get user's IP address
$ip_address = $_SERVER['REMOTE_ADDR'];

// Check if user has already submitted
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE ip_address = ?");
$stmt->bind_param("s", $ip_address);
$stmt->execute();
$result = $stmt->get_result();
$check = $result->fetch_assoc();

// Return result
echo json_encode(['completed' => ($check['count'] > 0)]);

$stmt->close();
$conn->close();
?>