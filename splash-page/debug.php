<?php
// Set appropriate headers
header('Content-Type: application/json');

// Basic server information
$server_info = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'],
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
    'script_filename' => $_SERVER['SCRIPT_FILENAME'],
    'request_uri' => $_SERVER['REQUEST_URI'],
    'remote_addr' => $_SERVER['REMOTE_ADDR']
];

// Database connection test
$db_status = [];
try {
    require_once 'config.php';
    
    if ($conn && $conn->ping()) {
        $db_status['connected'] = true;
        $db_status['server_info'] = $conn->server_info;
        
        // Check if the table exists
        $result = $conn->query("SHOW TABLES LIKE 'survey_responses'");
        $db_status['table_exists'] = $result->num_rows > 0;
        
        // Count records
        if ($db_status['table_exists']) {
            $result = $conn->query("SELECT COUNT(*) as count FROM survey_responses");
            $row = $result->fetch_assoc();
            $db_status['record_count'] = $row['count'];
        }
        
        $conn->close();
    } else {
        $db_status['connected'] = false;
        $db_status['error'] = $conn->connect_error;
    }
} catch (Exception $e) {
    $db_status['connected'] = false;
    $db_status['error'] = $e->getMessage();
}

// File permissions check
$file_checks = [];
$files_to_check = [
    'index.html',
    'check_completion.php',
    'submit_survey.php',
    'config.php',
    '/src/main.js',
    '/src/style.css'
];

foreach ($files_to_check as $file) {
    $full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($file, '/');
    $file_checks[$file] = [
        'exists' => file_exists($full_path),
        'readable' => is_readable($full_path),
        'writable' => is_writable($full_path)
    ];
    
    if ($file_checks[$file]['exists']) {
        $file_checks[$file]['modified'] = date("Y-m-d H:i:s", filemtime($full_path));
        $file_checks[$file]['size'] = filesize($full_path);
    }
}

// Directory checks
$dir_checks = [];
$directories = [
    '/uploads',
    '/src',
    '/src/assets',
    '/src/assets/images'
];

foreach ($directories as $dir) {
    $full_path = $_SERVER['DOCUMENT_ROOT'] . $dir;
    $dir_checks[$dir] = [
        'exists' => is_dir($full_path),
        'readable' => is_readable($full_path),
        'writable' => is_writable($full_path)
    ];
}

// Extensions check
$required_extensions = [
    'mysqli',
    'json',
    'fileinfo',
    'mbstring',
    'openssl',
    'gd'
];

$extensions = [];
foreach ($required_extensions as $ext) {
    $extensions[$ext] = extension_loaded($ext);
}

// Return all debug information
echo json_encode([
    'server' => $server_info,
    'database' => $db_status,
    'files' => $file_checks,
    'directories' => $dir_checks,
    'extensions' => $extensions,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
?>