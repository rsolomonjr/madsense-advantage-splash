<?php
require_once 'config.php';
require_once 'vendor/autoload.php'; // For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get the posted data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['name']) || !isset($data['email']) || !isset($data['company']) || 
    !isset($data['jobTitle']) || !isset($data['question1_answer']) || !isset($data['question2_answer'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$name = $data['name'];
$email = $data['email'];
$company = $data['company'];
$jobTitle = $data['jobTitle'];
$ip_address = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '0.0.0.0';
$question1_answer = $data['question1_answer'];
$question2_answer = $data['question2_answer'];

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
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
            submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($createTable)) {
            throw new Exception("Failed to create table: " . $conn->error);
        }
    }

    // Check if user has already submitted
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE ip_address = ? OR email = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $ip_address, $email);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $check = $result->fetch_assoc();

    if ($check['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already submitted a response']);
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
        
        if ($pdfPath && sendEmail($name, $email, $pdfPath, $question1_answer)) {
            echo json_encode(['success' => true, 'message' => 'Survey submitted successfully']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Survey submitted, but there was an issue sending the email']);
        }
    } else {
        throw new Exception("Failed to submit survey: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    // Log the error
    error_log('Survey submission error: ' . $e->getMessage());
    
    // Return an error response
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    // Close the database connection
    $conn->close();
}

// Function to get the PDF file path based on topic
function getPdfPath($topicCode) {
    // Define PDF paths in src/assets/ directory
    $pdfFiles = [
        'programmatic' => 'src/assets/pdfs/MadSense_Programmatic_Advertising_PoV.pdf',
        'identities' => 'src/assets/pdfs/MadSense_Identity_Resolution_PoV.pdf',
        'cookies' => 'src/assets/pdfs/MadSense_Cookieless_Targeting_PoV.pdf',
        'attribution' => 'src/assets/pdfs/MadSense_Attribution_PoV.pdf',
        'privacy' => 'src/assets/pdfs/MadSense_Privacy_Regulations_PoV.pdf',
        'ai' => 'src/assets/pdfs/MadSense_AI_Advertising_PoV.pdf',
        'default' => 'src/assets/pdfs/MadSense_AdTech_PoV.pdf'
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
function sendEmail($name, $email, $pdfPath, $topicCode) {
    try {
        // Define SMTP constants if not available in config.php
        if (!defined('SMTP_HOST')) define('SMTP_HOST', 'mail.madsense.tech');
        if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'info@madsense.tech');
        if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'your_email_password');
        if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
        
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom('info@madsense.tech', 'MadSense AdTech');
        $mail->addAddress($email, $name);
        $mail->addReplyTo('info@madsense.tech', 'MadSense AdTech');
        
        // Attach the PDF
        $mail->addAttachment($pdfPath);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your MadSense Functional PoV™ on ' . getTopicTitle($topicCode);
        
        $mailBody = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding-bottom: 20px; border-bottom: 1px solid #eee; }
                .content { padding: 20px 0; }
                .button { display: inline-block; background-color: #4B365F; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; }
                .footer { padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #777; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Your MadSense Functional PoV™</h1>
                </div>
                <div class="content">
                    <p>Hello ' . htmlspecialchars($name) . ',</p>
                    <p>Thank you for participating in our survey. As promised, we\'ve prepared a custom Functional Point of View™ on ' . getTopicTitle($topicCode) . ' for you, based on your responses.</p>
                    <p>Please find the attached PDF with our analysis and recommendations. We hope you find it valuable!</p>
                    <p>If you have any questions or would like to discuss this topic further, please don\'t hesitate to reach out.</p>
                    <p style="text-align: center; margin-top: 30px;">
                        <a href="https://www.madsense.tech" class="button">Visit Our Website</a>
                    </p>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' MadSense AdTech Solutions. All rights reserved.</p>
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
        'attribution' => 'Multi-touch Attribution',
        'privacy' => 'Privacy Regulations',
        'ai' => 'AI in Advertising'
    ];
    
    return isset($topics[$topicCode]) ? $topics[$topicCode] : 'AdTech';
}