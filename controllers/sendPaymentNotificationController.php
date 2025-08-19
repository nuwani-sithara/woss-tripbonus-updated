<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'debug' => [] // Added debug information
];

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method. Only POST requests are allowed.");
    }

    // Get input data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['month']) || !isset($data['year']) || !isset($data['publishedID'])) {
        throw new Exception("Invalid input data. Month, year and publishedID are required.");
    }

    $month = $data['month'];
    $year = $data['year'];
    $publishedID = $data['publishedID'];
    $response['debug']['input_data'] = $data;

    // Database connection
    require_once '../config/dbConnect.php';
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Get accountant details (roleID = 5)
    $accountantQuery = "SELECT userID, email, fname, lname FROM users WHERE roleID = 5 LIMIT 1";
    $accountantResult = $conn->query($accountantQuery);
    if (!$accountantResult || $accountantResult->num_rows === 0) {
        throw new Exception("No accountant found in the system.");
    }
    $accountant = $accountantResult->fetch_assoc();
    $accountantID = $accountant['userID'];
    $accountantEmail = $accountant['email'];
    $accountantName = trim($accountant['fname'] . ' ' . trim($accountant['lname']));
    $response['debug']['accountant_info'] = $accountant;

    // Prepare email content
    $subject = "Monthly Diving Payments Approval Required - " . $month . " " . $year;
    $approvalLink = "https://tripbonus.worldsubsea.lk/views/paymentverification.php?month=" . urlencode($month) . "&year=" . $year;
    
    $emailBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; background-color: #f5f5f5; margin: 0; padding: 20px; }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background-color: white; 
                border: 1px solid #e0e0e0; 
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .header { 
                background-color: #f8f9fa; 
                padding: 15px; 
                text-align: center; 
                border-bottom: 1px solid #e0e0e0;
            }
            .content { 
                padding: 20px; 
            }
            .button { 
                display: inline-block; 
                padding: 10px 20px; 
                background-color: #2e59d9; 
                color: white !important; 
                text-decoration: none; 
                border-radius: 5px; 
                margin-top: 15px;
                font-weight: bold;
            }
            .footer { 
                margin-top: 20px; 
                font-size: 12px; 
                color: #6c757d; 
                text-align: center; 
                padding: 15px;
                border-top: 1px solid #e0e0e0;
                background-color: #f8f9fa;
            }
            p {
                margin-bottom: 15px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Monthly Diving Payments Approval</h2>
            </div>
            <div class='content'>
                <p>Dear $accountantName,</p>
                <p>The monthly diving payments for <strong>$month $year</strong> have been published by the Operations Manager and require your approval.</p>
                <p>Please review the payments and approve them at your earliest convenience.</p>
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='$approvalLink' class='button'>Review Payments</a>
                </div>
                <p>If you have any questions or need additional information, please contact the Operations Manager.</p>
                <p>Best regards,<br>WOSS Trip Bonus System</p>
            </div>
            <div class='footer'>
                This is an automated notification. Please do not reply to this email.
            </div>
        </div>
    </body>
    </html>
    ";

    // Verify PHPMailer is installed
    if (!file_exists('../vendor/autoload.php')) {
        throw new Exception("PHPMailer is not installed. Please run 'composer require phpmailer/phpmailer'");
    }
    require '../vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings with detailed debug
        $mail->SMTPDebug = 3; // Enable verbose debug output
        $mail->isSMTP();
        $mail->Host = 'smtp.office365.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'systems@mclarens.lk';
        $mail->Password = 'Com38518';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 30; // Increase timeout
        
        // Recipients
        $mail->setFrom('tripbonus@worldsubsea.lk', 'WOSS Trip Bonus System');
        $mail->addAddress($accountantEmail, $accountantName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $emailBody));
        
        // Capture debug output
        ob_start();
        $mailSent = $mail->send();
        $debugOutput = ob_get_clean();
        $response['debug']['smtp_debug'] = $debugOutput;
        
        if (!$mailSent) {
            throw new Exception("Email could not be sent. PHPMailer Error: " . $mail->ErrorInfo);
        }
        
        // Log the notification in database
        $stmt = $conn->prepare("
            INSERT INTO payment_notifications (publishedID, accountantID, email_sent, sent_at)
            VALUES (?, ?, 1, NOW())
        ");
        $stmt->bind_param("ii", $publishedID, $accountantID);
        if (!$stmt->execute()) {
            throw new Exception("Failed to log notification: " . $stmt->error);
        }
        $stmt->close();
        
        $response['success'] = true;
        $response['message'] = "Notification email sent successfully to accountant.";
        
    } catch (Exception $e) {
        // Log the failed attempt
        $stmt = $conn->prepare("
            INSERT INTO payment_notifications (publishedID, accountantID, email_sent, sent_at)
            VALUES (?, ?, 0, NOW())
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $publishedID, $accountantID);
            $stmt->execute();
            $stmt->close();
        }
        
        throw new Exception("Email sending failed: " . $e->getMessage());
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
    error_log("Payment notification error: " . $e->getMessage());
    
    // Add more debug info if available
    if (isset($mail)) {
        $response['debug']['phpmailer_error'] = $mail->ErrorInfo;
    }
} finally {
    // Log the full response for debugging
    error_log("Notification response: " . print_r($response, true));
    echo json_encode($response);
    if (isset($conn) && $conn) $conn->close();
    exit;
}