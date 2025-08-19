<?php
require_once '../config/dbConnect.php';
require_once '../vendor/autoload.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'debug' => []];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST only");

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['ceoVerifyID']) || !isset($data['publishedID'])) throw new Exception("Missing ceoVerifyID or publishedID");

    $ceoVerifyID = intval($data['ceoVerifyID']);
    $publishedID = intval($data['publishedID']);

    // Get ceoverify info
    $sql = "SELECT * FROM ceoverify WHERE ceoVerifyID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $ceoVerifyID);
    $stmt->execute();
    $verify = $stmt->get_result()->fetch_assoc();
    if (!$verify) throw new Exception("Invalid ceoVerifyID");
    $month = $verify['month'];
    $year = $verify['year'];
    $ceoID = $verify['ceoVerifyBy'];
    $verifyDate = $verify['ceoVerifyDate'];

    // Get CEO info
    $ceo = $conn->query("SELECT fname, lname FROM users WHERE userID = $ceoID")->fetch_assoc();
    $ceoName = trim($ceo['fname'] . ' ' . $ceo['lname']);

    // Get operation manager and director
    $om = $conn->query("SELECT userID, email, fname, lname FROM users WHERE roleID = 4 LIMIT 1")->fetch_assoc();
    $director = $conn->query("SELECT userID, email, fname, lname FROM users WHERE roleID = 7 LIMIT 1")->fetch_assoc();

    // --- Email to Operation Manager ---
    $subjectOM = "Payments Verified by CEO for $month $year";
    $bodyOM = "
    <html>
    <head>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                background-color: #f5f5f5; 
                margin: 0; 
                padding: 20px; 
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background-color: white; 
                border: 1px solid #e0e0e0; 
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .header { 
                background-color: #d4edda; 
                padding: 15px; 
                text-align: center; 
                border-bottom: 1px solid #e0e0e0;
                color: #155724;
            }
            .content { 
                padding: 20px; 
            }
            .button { 
                display: inline-block; 
                padding: 10px 20px; 
                background-color: #28a745; 
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
                <h2>Monthly Diving Payments Verified</h2>
            </div>
            <div class='content'>
                <p>Dear {$om['fname']} {$om['lname']},</p>
                <p>The payments for <strong>$month $year</strong> have been <strong>verified</strong> by CEO: <strong>$ceoName</strong> on $verifyDate.</p>
                <p>Please review if needed.</p>
                <p>Best regards,<br>WOSS Trip Bonus System</p>
            </div>
            <div class='footer'>
                This is an automated notification. Please do not reply to this email.
            </div>
        </div>
    </body>
    </html>
    ";

    // --- Email to Director ---
    $subjectDirector = "Payments Awaiting Your Approval - $month $year";
    $approvalLink = "https://tripbonus.worldsubsea.lk/views/directorverfication.php?month=" . urlencode($month) . "&year=" . $year;
    $bodyDirector = "
    <html>
    <head>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                background-color: #f5f5f5; 
                margin: 0; 
                padding: 20px; 
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background-color: white; 
                border: 1px solid #e0e0e0; 
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .header { 
                background-color: #cce5ff; 
                padding: 15px; 
                text-align: center; 
                border-bottom: 1px solid #e0e0e0;
                color: #004085;
            }
            .content { 
                padding: 20px; 
            }
            .button { 
                display: inline-block; 
                padding: 10px 20px; 
                background-color: #007bff; 
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
                <h2>Monthly Diving Payments Awaiting Approval</h2>
            </div>
            <div class='content'>
                <p>Dear {$director['fname']} {$director['lname']},</p>
                <p>The payments for <strong>$month $year</strong> have been verified by the CEO and now require your approval.</p>
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='$approvalLink' class='button'>Review & Approve Payments</a>
                </div>
                <p>Best regards,<br>WOSS Trip Bonus System</p>
            </div>
            <div class='footer'>
                This is an automated notification. Please do not reply to this email.
            </div>
        </div>
    </body>
    </html>
    ";
    // Send emails using PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.office365.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'systems@mclarens.lk';
    $mail->Password = 'Com38518';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom('tripbonus@worldsubsea.lk', 'WOSS Trip Bonus System');
    $mail->isHTML(true);

    // Send to OM
    $mail->clearAddresses();
    $mail->addAddress($om['email'], $om['fname'] . ' ' . $om['lname']);
    $mail->Subject = $subjectOM;
    $mail->Body = $bodyOM;
    $mail->send();
    // Log OM notification
    $stmt = $conn->prepare("INSERT INTO payment_notifications (publishedID, accountantID, recipientID, recipient_role, notification_type, email_sent, sent_at, reason, status) VALUES (?, ?, ?, 'operation_manager', 'ceo_verify', 1, NOW(), NULL, 1)");
    $stmt->bind_param("iii", $publishedID, $ceoID, $om['userID']);
    $stmt->execute();

    // Send to Director
    $mail->clearAddresses();
    $mail->addAddress($director['email'], $director['fname'] . ' ' . $director['lname']);
    $mail->Subject = $subjectDirector;
    $mail->Body = $bodyDirector;
    $mail->send();
    // Log Director notification
    $stmt = $conn->prepare("INSERT INTO payment_notifications (publishedID, accountantID, recipientID, recipient_role, notification_type, email_sent, sent_at, reason, status) VALUES (?, ?, ?, 'director', 'ceo_verify', 1, NOW(), NULL, 1)");
    $stmt->bind_param("iii", $publishedID, $ceoID, $director['userID']);
    $stmt->execute();

    $response['success'] = true;
    $response['message'] = "Notifications sent to OM and Director.";
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}
echo json_encode($response);
exit;