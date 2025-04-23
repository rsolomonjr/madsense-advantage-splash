<?php
require_once 'config.php';
require_once 'vendor/autoload.php'; // For TCPDF library and PHPMailer

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
$ip_address = $_SERVER['REMOTE_ADDR'];
$question1_answer = $data['question1_answer'];
$question2_answer = $data['question2_answer'];

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Check if user has already submitted
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM survey_responses WHERE ip_address = ? OR email = ?");
$stmt->bind_param("ss", $ip_address, $email);
$stmt->execute();
$result = $stmt->get_result();
$check = $result->fetch_assoc();

if ($check['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already submitted a response']);
    exit;
}

// Insert the survey response
$stmt = $conn->prepare("INSERT INTO survey_responses (name, email, company, job_title, ip_address, question1_answer, question2_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssss", $name, $email, $company, $jobTitle, $ip_address, $question1_answer, $question2_answer);

if ($stmt->execute()) {
    // Generate PDF
    $pdfPath = generatePDF($data);
    
    if ($pdfPath && sendEmail($name, $email, $pdfPath, $question1_answer)) {
        echo json_encode(['success' => true, 'message' => 'Survey submitted successfully']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Survey submitted, but there was an issue sending the email']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit survey: ' . $conn->error]);
}

$stmt->close();
$conn->close();

// Function to generate PDF based on survey answers
function generatePDF($data) {
    try {
        // Get POV content based on the selected topic
        $povContent = getPovContent($data['question1_answer']);
        
        // Set up TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('MadSense AdTech');
        $pdf->SetAuthor('MadSense AdTech');
        $pdf->SetTitle('MadSense Functional PoV™');
        $pdf->SetSubject('AdTech Point of View');
        
        // Set default header and footer data
        $pdf->setHeaderData('', 0, 'MadSense Functional PoV™', 'Generated for: ' . $data['name'] . ' | ' . date('F j, Y'));
        
        // Set header and footer fonts
        $pdf->setHeaderFont(Array('helvetica', '', 10));
        $pdf->setFooterFont(Array('helvetica', '', 8));
        
        // Set margins
        $pdf->SetMargins(15, 25, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Set font
        $pdf->SetFont('helvetica', '', 11);
        
        // Add a page
        $pdf->AddPage();
        
        // Add logo
        $imagePath = $_SERVER['DOCUMENT_ROOT'] . '/src/assets/images/1-01.jpg';
        if (file_exists($imagePath)) {
            $pdf->Image($imagePath, 15, 15, 30, 0, 'JPG', '', 'T', false, 300, 'L', false, false, 0, false, false, false);
        }
        
        // Add title
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 20, '', 0, 1);
        $pdf->Cell(0, 10, 'Functional Point of View™: ' . getTopicTitle($data['question1_answer']), 0, 1);
        
        // Add date and personalization
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 10, 'Created for: ' . $data['name'] . ' at ' . $data['company'], 0, 1);
        $pdf->Cell(0, 10, 'Date: ' . date('F j, Y'), 0, 1);
        $pdf->Ln(5);
        
        // Add intro
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Executive Summary', 0, 1);
        $pdf->SetFont('helvetica', '', 11);
       $pdf->writeHTML('<p>Based on your response regarding challenges with "' . htmlspecialchars($data['question2_answer']) . '", we\'ve prepared this analysis to help you navigate the complexities of ' . getTopicTitle($data['question1_answer']) . '.</p>');
        $pdf->Ln(5);
        
        // Add main content
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Industry Analysis', 0, 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->writeHTML($povContent['industry_analysis']);
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Key Considerations', 0, 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->writeHTML($povContent['key_considerations']);
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Recommendations', 0, 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->writeHTML($povContent['recommendations']);
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Next Steps', 0, 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->writeHTML($povContent['next_steps']);
        $pdf->Ln(10);
        
        // Add footer note
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 10, 'For more information or to schedule a consultation, please contact MadSense at info@madsense.tech', 0, 1);
        
        // Create directory if it doesn't exist
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Save the PDF
        $filename = 'MadSense_PoV_' . sanitizeFilename($data['name']) . '_' . date('Y-m-d') . '.pdf';
        $filepath = $uploadDir . $filename;
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        return false;
    }
}

// Function to send email with PDF attachment
function sendEmail($name, $email, $pdfPath, $topicCode) {
    try {
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
                    <p>Thank you for participating in our survey. As promised, we've prepared a custom Functional Point of View™ on ' . getTopicTitle($topicCode) . ' for you, based on your responses.</p>
                    <p>Please find the attached PDF with our analysis and recommendations. We hope you find it valuable!</p>
                    <p>If you have any questions or would like to discuss this topic further, please don't hesitate to reach out.</p>
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

// Helper function to sanitize filename
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-z0-9]+/', '-', strtolower($filename));
    return trim($filename, '-');
}

// Helper function to get POV content based on topic
function getPovContent($topicCode) {
    $content = [];
    
    switch ($topicCode) {
        case 'programmatic':
            $content['industry_analysis'] = '<p>The programmatic advertising landscape continues to evolve rapidly, with global spend exceeding $200 billion annually. Recent industry shifts include the push toward supply path optimization (SPO), the rise of programmatic guaranteed deals, and increased investment in CTV and audio channels.</p><p>Key players are focusing on transparency initiatives, with demand-side platforms implementing measures to provide greater visibility into the programmatic supply chain. Meanwhile, supply-side platforms are differentiating through unique inventory access, data offerings, and optimization tools.</p>';
            $content['key_considerations'] = '<ul><li><strong>Supply Chain Transparency:</strong> Visibility into the programmatic supply chain remains challenging, with numerous intermediaries potentially adding costs and complexity.</li><li><strong>Identity Challenges:</strong> Cookie deprecation and mobile identifier restrictions are forcing programmatic buyers to develop alternative targeting strategies.</li><li><strong>Creative Optimization:</strong> Dynamic creative optimization and personalization opportunities are often underutilized in programmatic campaigns.</li><li><strong>Channel Integration:</strong> Cross-channel programmatic execution requires sophisticated planning and measurement approaches.</li></ul>';
            $content['recommendations'] = '<p>Based on current market conditions, we recommend:</p><ul><li>Implement formal supply path optimization protocols to reduce ad tech fees and improve transparency</li><li>Develop a balanced identity strategy utilizing first-party data, contextual signals, and privacy-compliant identifiers</li><li>Consider direct programmatic deals with premium publishers where audience alignment is strong</li><li>Explore emerging programmatic channels like DOOH and audio if they align with audience behaviors</li><li>Invest in creative testing frameworks to maximize the impact of programmatic placements</li></ul>';
            $content['next_steps'] = '<p>To advance your programmatic capabilities, consider:</p><ol><li>Conducting a programmatic supply chain audit to identify optimization opportunities</li><li>Evaluating your DSP stack against current and future campaign requirements</li><li>Developing clear KPIs that align with business outcomes rather than just campaign metrics</li><li>Creating a roadmap for expanding programmatic capabilities across emerging channels</li></ol>';
            break;
            
        case 'identities':
            $content['industry_analysis'] = '<p>Identity resolution has become a critical capability as traditional identifiers face increasing restrictions. The industry has responded with a proliferation of alternative approaches, including deterministic ID solutions, probabilistic matching methodologies, and clean room technologies.</p><p>Enterprise brands are increasingly investing in customer data platforms (CDPs) to unify first-party data assets, while publishers are exploring authenticated traffic solutions to maintain addressability. Meanwhile, walled gardens continue to strengthen their positions through vast logged-in user bases.</p>';
            $content['key_considerations'] = '<ul><li><strong>First-Party Data Strategy:</strong> The quality and scale of first-party data now directly impacts addressability capabilities.</li><li><strong>Technology Integration:</strong> Identity resolution requires careful integration across multiple systems and platforms.</li><li><strong>Privacy Compliance:</strong> Evolving regulations introduce complexity to identity data collection and activation.</li><li><strong>Measurement Impact:</strong> Changes to identity infrastructure directly affect attribution and measurement capabilities.</li></ul>';
            $content['recommendations'] = '<p>Based on current market conditions, we recommend:</p><ul><li>Develop a comprehensive first-party data strategy that includes both collection and activation planning</li><li>Establish a formal identity graph that unifies customer identities across touchpoints</li><li>Implement a multi-faceted approach that leverages deterministic matching where possible and probabilistic where necessary</li><li>Explore data clean room solutions for advanced use cases involving sensitive data</li><li>Test and evaluate universal ID solutions while maintaining flexibility as the landscape evolves</li></ul>';
            $content['next_steps'] = '<p>To advance your identity resolution capabilities, consider:</p><ol><li>Conducting an audit of your current identity infrastructure and data assets</li><li>Evaluating identity resolution vendors against your specific business requirements</li><li>Developing clear governance processes for identity data management</li><li>Creating test-and-learn programs to quantify the value of improved identity resolution</li></ol>';
            break;
            
        case 'cookies':
            $content['industry_analysis'] = '<p>The deprecation of third-party cookies in Chrome has accelerated industry transformation toward alternative targeting approaches. Major changes include Google\'s Privacy Sandbox initiatives, Apple\'s continuing privacy restrictions, and the emergence of numerous cookieless targeting solutions.</p><p>Publishers have responded by enhancing first-party data offerings, while advertisers increasingly test contextual targeting, cohort-based approaches, and probabilistic solutions. Measurement is similarly evolving, with greater emphasis on media mix modeling and incrementality testing.</p>';
            $content['key_considerations'] = '<ul><li><strong>Audience Targeting:</strong> Cookie deprecation requires new approaches to audience definition and targeting.</li><li><strong>Measurement Disruption:</strong> Attribution and measurement frameworks need adaptation for environments without persistent identifiers.</li><li><strong>Publisher Relationships:</strong> Direct publisher relationships become more valuable for access to first-party data and addressable inventory.</li><li><strong>Contextual Renaissance:</strong> Advanced contextual targeting offers opportunities beyond traditional keyword approaches.</li></ul>';
            $content['recommendations'] = '<p>Based on current market conditions, we recommend:</p><ul><li>Develop a balanced targeting strategy that reduces reliance on third-party cookies</li><li>Invest in first-party data collection, management, and activation capabilities</li><li>Evaluate contextual intelligence platforms that go beyond basic keyword targeting</li><li>Test Google\'s Privacy Sandbox solutions while maintaining realistic expectations about their effectiveness</li><li>Consider direct publisher relationships that provide authenticated audience access</li></ul>';
            $content['next_steps'] = '<p>To prepare for a cookieless future, consider:</p><ol><li>Conducting a cookie dependency audit across your marketing technology stack</li><li>Developing a testing framework to evaluate alternative targeting approaches</li><li>Creating a first-party data strategy that addresses both collection and activation</li><li>Establishing new measurement methodologies that reduce reliance on cookie-based attribution</li></ol>';
            break;
            
        default:
            // Generic content for other topics
            $content['industry_analysis'] = '<p>The AdTech landscape continues to evolve rapidly, with technological innovation driving new capabilities and regulatory changes reshaping industry practices. Enterprise organizations are increasingly focused on establishing direct consumer relationships, while building first-party data assets to reduce dependence on third-party solutions.</p><p>Recent industry trends include the continued growth of programmatic channels, increasing focus on measurement quality, and heightened attention to privacy compliance and responsible data usage.</p>';
            $content['key_considerations'] = '<ul><li><strong>Strategic Planning:</strong> AdTech capabilities should align with broader marketing and business objectives.</li><li><strong>Integration Challenges:</strong> Connecting disparate technologies remains a key challenge for many organizations.</li><li><strong>Privacy Compliance:</strong> Evolving regulations require constant adaptation of data practices.</li><li><strong>Measurement Complexity:</strong> Attribution across channels and platforms requires sophisticated approaches.</li></ul>';
            $content['recommendations'] = '<p>Based on current market conditions, we recommend:</p><ul><li>Develop a comprehensive AdTech strategy that aligns with business objectives</li><li>Establish clear data governance policies that address privacy regulations</li><li>Invest in first-party data collection and activation capabilities</li><li>Create measurement frameworks that combine multiple methodologies</li><li>Prioritize integration capabilities when evaluating new technology</li></ul>';
            $content['next_steps'] = '<p>To advance your AdTech capabilities, consider:</p><ol><li>Conducting an audit of your current technology stack</li><li>Evaluating your data strategy against industry best practices</li><li>Developing a roadmap for capability development</li><li>Creating test-and-learn programs to evaluate new approaches</li></ol>';
            break;
    }
    
    return $content;
}
?>