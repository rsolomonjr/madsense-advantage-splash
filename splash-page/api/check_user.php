<?php
require_once 'config.php';

$ip_address = $_SERVER['REMOTE_ADDR'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE ip_address = ?");
$stmt->bind_param("s", $ip_address);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode(['has_submitted' => $data['count'] > 0]);

$stmt->close();
$conn->close();
?>