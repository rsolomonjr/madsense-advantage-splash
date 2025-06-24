<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';// For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure no whitespace or output before JSON response
ob_clean(); // Clear any previous output buffer
ob_start(); // Start a new output buffer to capture any unwanted output

// Get the posted data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['name']) || !isset($data['email']) || !isset($data['company']) || 
    !isset($data['jobTitle']) || !isset($data['question1_answer']) || !isset($data['question2_answer'])) {
    sendJsonResponse(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$name = $data['name'];
$email = $data['email'];
$company = $data['company'];
$jobTitle = $data['jobTitle'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '0.0.0.0';
$question1_answer = $data['question1_answer'];
$question2_answer = $data['question2_answer'];
$recaptcha_response = isset($data['recaptcha_response']) ? $data['recaptcha_response'] : '';

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Verify reCAPTCHA
if (!empty($recaptcha_response)) {
    $recaptcha_verified = verifyRecaptcha($recaptcha_response, $ip_address);
    if (!$recaptcha_verified) {
        sendJsonResponse(['success' => false, 'message' => 'reCAPTCHA verification failed. Please try again.']);
        exit;
    }
} else {
    // If no reCAPTCHA response provided, still allow (for backward compatibility)
    // You can change this to require reCAPTCHA by uncommenting the lines below:
    // sendJsonResponse(['success' => false, 'message' => 'reCAPTCHA verification required']);
    // exit;
    error_log('Warning: Form submitted without reCAPTCHA verification');
}

try {
    // Check if table exists and create it if not
    $tableCheck = $conn->query("SHOW TABLES LIKE 'survey_responses'");
    if ($tableCheck->num_rows == 0) {
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

    // Check if user has already submitted for this specific topic
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE email = ? AND question1_answer = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $email, $question1_answer);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $check = $result->fetch_assoc();

    if ($check['count'] > 0) {
        sendJsonResponse(['success' => false, 'message' => 'You have already requested a PoV for this topic. Please select a different topic to receive a new PoV.']);
        exit;
    }

    // Insert the survey response
    $stmt = $conn->prepare("INSERT INTO survey_responses (name, email, company, job_title, ip_address, question1_answer, question2_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sssssss", $name, $email, $company, $jobTitle, $ip_address, $question1_answer, $question2_answer);

    if ($stmt->execute()) {
        // Get the PDF file path based on the selected topic
        $pdfPath = getPdfPath($question1_answer);
        
        // Redirect SMTP debugging output to a file or error log
        $logFile = __DIR__ . '/mail_debug.log';
        
        if ($pdfPath && sendEmail($name, $email, $pdfPath, $question1_answer, $logFile)) {
            sendJsonResponse(['success' => true, 'message' => 'Survey submitted successfully']);
        } else {
            sendJsonResponse(['success' => true, 'message' => 'Survey submitted, but there was an issue sending the email']);
        }
    } else {
        throw new Exception("Failed to submit survey: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    // Log the error
    error_log('Survey submission error: ' . $e->getMessage());
    
    // Check if this is a duplicate key error
    if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'unique_email_topic') !== false) {
        sendJsonResponse(['success' => false, 'message' => 'You have already requested a PoV for this topic. Please select a different topic to receive a new PoV.']);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} finally {
    // Close the database connection
    $conn->close();
}

// Function to verify reCAPTCHA
function verifyRecaptcha($recaptcha_response, $ip_address) {
    $secret_key = RECAPTCHA_SECRET_KEY;
    
    // Prepare the verification URL
    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
    
    // Prepare the data for POST request
    $data = array(
        'secret' => $secret_key,
        'response' => $recaptcha_response,
        'remoteip' => $ip_address
    );
    
    // Create context for POST request
    $options = array(
        'http' => array(
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        )
    );
    
    $context = stream_context_create($options);
    
    // Make the verification request
    $response = file_get_contents($verify_url, false, $context);
    
    if ($response === FALSE) {
        error_log('reCAPTCHA verification request failed');
        return false;
    }
    
    // Decode the response
    $response_data = json_decode($response, true);
    
    // Log the response for debugging
    error_log('reCAPTCHA verification response: ' . json_encode($response_data));
    
    // Check if verification was successful
    if (isset($response_data['success']) && $response_data['success'] === true) {
        // Optionally check the score for reCAPTCHA v3 (if you're using v3)
        if (isset($response_data['score'])) {
            // For reCAPTCHA v3, you might want to check if score is above a threshold (e.g., 0.5)
            return $response_data['score'] >= 0.5;
        }
        return true;
    } else {
        // Log the error codes for debugging
        if (isset($response_data['error-codes'])) {
            error_log('reCAPTCHA verification failed with errors: ' . implode(', ', $response_data['error-codes']));
        }
        return false;
    }
}

// Helper function to send clean JSON response
function sendJsonResponse($data) {
    // Clear any output that might have been generated
    ob_clean();
    
    // Set the correct content type
    header('Content-Type: application/json');
    
    // Output the JSON
    echo json_encode($data);
    
    // End script execution
    exit;
}

// Function to get the PDF file path based on topic
function getPdfPath($topicCode) {
    // Define PDF paths in src/assets/ directory - Updated with different PDFs per topic
    $pdfFiles = [
        'programmatic' => 'src/assets/pdfs/MADSense_PoV_OpenAPs.pdf',
        'identities' => 'src/assets/pdfs/MADSense_Functional_PoV_Undiclosed_Media_6.25.pdf',
        'cookies' => 'src/assets/pdfs/MADSense_PoV_OpenAPs.pdf',
        'analytics' => 'src/assets/pdfs/MADSense_PoV_OpenAPs.pdf',
        'privacy' => 'src/assets/pdfs/MADSense_PoV_OpenAPs.pdf',
        'ai' => 'src/assets/pdfs/MADSense_PoV_OpenAPs.pdf',
        'default' => 'src/assets/pdfs/MADSense_PoV_OpenAPs.pdf'
    ];
    
    // Get the path for the selected topic or use default
    $pdfPath = isset($pdfFiles[$topicCode]) ? $pdfFiles[$topicCode] : $pdfFiles['default'];
    
    // Check if file exists
    $absolutePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $pdfPath;
    if (!file_exists($absolutePath)) {
        error_log('PDF file not found: ' . $absolutePath);
        // If the specific PDF doesn't exist, try to use the default one
        $absolutePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $pdfFiles['default'];
        if (!file_exists($absolutePath)) {
            error_log('Default PDF file not found: ' . $absolutePath);
            return false;
        }
    }
    
    return $absolutePath;
}

// Function to send email with PDF attachment
function sendEmail($name, $email, $pdfPath, $topicCode, $logFile = null) {
    try {
        // Define SMTP constants if not available in config.php
        if (!defined('SMTP_HOST')) define('SMTP_HOST', 'box2467.bluehost.com');
        if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'info@madsense.tech');
        if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'qo0mD1j&*HS?');
        if (!defined('SMTP_PORT')) define('SMTP_PORT', 465);
        
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'box2467.bluehost.com'; // Use Bluehost's SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SSL instead of TLS for port 465
        $mail->Port = SMTP_PORT;
        
        // IMPORTANT: Change debug level to 0 for production or redirect output to a log file
        $mail->SMTPDebug = 0; // Turn off debug output
        
        // If you still need debugging, redirect it to a log file
        if ($logFile) {
            $mail->Debugoutput = function($str, $level) use ($logFile) {
                file_put_contents($logFile, date('Y-m-d H:i:s')."\t$level\t$str\n", FILE_APPEND);
            };
        }
        
        // Recipients
        $mail->setFrom('info@madsense.tech', 'MadSense Inc.');
        $mail->addAddress($email, $name);
        $mail->addReplyTo('info@madsense.tech', 'MadSense Inc.');
        
        // Attach the PDF
        $mail->addAttachment($pdfPath);
        
        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Your MadSense Functional PoV™️ on ' . getTopicTitle($topicCode);
        
        $mailBody = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 1px solid #eee; }
                .content { padding: 20px 0; }
                .button { display: inline-block; background-color: #4B365F; color: white !important; padding: 12px 24px; text-decoration: none; border-radius: 4px; }
                .footer { padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #777; }
                .social-icons { text-align: center; margin-top: 24px; }
                .social-icon {
                    display: inline-block;
                    margin: 0 8px;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    text-decoration: none;
                }
                .social-icon img {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: block;
                }
                @media (prefers-color-scheme: dark) {
                    body { color: #eee; background: #181818; }
                    .container { background: #232323; }
                    .footer { color: #bbb; border-top: 1px solid #333; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Your MadSense Functional PoV&trade;</h1>
                </div>
                <div class="content">
                    <p>Hello ' . htmlspecialchars($name) . ',</p>
                    <p>Thank you for participating in our survey. As promised, we\'ve prepared a custom Functional Point of View™ on ' . getTopicTitle($topicCode) . ' for you, based on your responses.</p>
                    <p>Please find the attached PDF with our analysis and recommendations. We hope you find it valuable!</p>
                    <p>If you have any questions or would like to discuss this topic further, please don\'t hesitate to reach out.</p>
                    <p style="text-align: center; margin-top: 30px;">
                        <a href="https://www.madsense.tech" class="button">Visit Our Website</a>
                    </p>
                   <div class="social-icons">
                        <a href="https://x.com/MADSenseTech" class="social-icon" target="_blank" title="Twitter">
                            <img src="https://www.madsense.tech/src/assets/images/twitter-icon.jpg" alt="Twitter" />
                        </a>
                        <a href="https://www.linkedin.com/company/madsensetech" class="social-icon" target="_blank" title="LinkedIn">
                            <img src="https://www.madsense.tech/src/assets/images/linkedin-icon.jpg" alt="LinkedIn" />
                        </a>
                    </div>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' MadSense Inc. All rights reserved.</p>
                    <p>This email was sent to ' . htmlspecialchars($email) . '</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->Body = $mailBody;
        $mail->AltBody = strip_tags(str_replace('<br>', "\r\n", $mailBody));
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email Error: ' . $e->getMessage());
        return false;
    }
}

// Helper function to get topic title from code
function getTopicTitle($topicCode) {
    $topics = [
        'programmatic' => 'Programmatic Advertising',
        'identities' => 'Identity Resolution',
        'cookies' => 'Cookieless Targeting',
        'analytics' => 'Analytics',
        'privacy' => 'Privacy Regulations',
        'ai' => 'AI in Advertising'
    ];
    
    return isset($topics[$topicCode]) ? $topics[$topicCode] : 'AdTech';
}
?>