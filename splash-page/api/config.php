<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

$host = "localhost";
$user = "rodnjrpf_mtmad"; // Replace with your MySQL username
$password = "H@le3ndSaka2"; // Replace with your MySQL password
$database = "rodnjrpf_madsense";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}
?>