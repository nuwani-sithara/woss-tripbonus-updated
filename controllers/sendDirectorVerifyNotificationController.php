<?php
require_once '../config/dbConnect.php';
require_once '../vendor/autoload.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'debug' => []];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("POST only");

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['directorVerifyID']) || !isset($data['publishedID'])) throw new Exception("Missing directorVerifyID or publishedID");

    $directorVerifyID = intval($data['directorVerifyID']);
    $publishedID = intval($data['publishedID']);

    // Get directorverify info
    $sql = "SELECT * FROM directorverify WHERE directorVerifyID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $directorVerifyID);
    $stmt->execute();
    $verify = $stmt->get_result()->fetch_assoc();
    if (!$verify) throw new Exception("Invalid directorVerifyID");
    $month = $verify['month'];
    $year = $verify['year'];
    $directorID = $verify['directorVerifyBy'];
    $verifyDate = $verify['directorVerifyDate'];

    // Get Director info
    $director = $conn->query("SELECT fname, lname FROM users WHERE userID = $directorID")->fetch_assoc();
    $directorName = trim($director['fname'] . ' ' . $director['lname']);

    // Get operation manager and accountant
    $om = $conn->query("SELECT userID, email, fname, lname FROM users WHERE roleID = 4 LIMIT 1")->fetch_assoc();
    $accountant = $conn->query("SELECT userID, email, fname, lname FROM users WHERE roleID = 5 LIMIT 1")->fetch_assoc();

    // --- Email to Operation Manager ---
    $subjectOM = "Payments Verified by Director for $month $year";
    $bodyOM = "
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
                <p>The payments for <strong>$month $year</strong> have been <strong>verified</strong> by Director: <strong>$directorName</strong> on $verifyDate.</p>
                <p>Please review if needed.</p>
                <p>Best regards,<br>SubseaOps</p>
            </div>
            <div class='footer'>
                This is an automated notification. Please do not reply to this email.
            </div>
        </div>
    </body>
    </html>
    ";

    // --- Email to Accountant ---
    $subjectAcc = "Payments Awaiting Payroll Processing - $month $year";
    $payrollLink = "https://subseaops.worldsubsea.lk/views/exportpayrollreport.php?month=" . urlencode($month) . "&year=" . $year;
    $bodyAcc = "
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
                <h2>Monthly Diving Payments Awaiting Payroll</h2>
            </div>
            <div class='content'>
                <p>Dear {$accountant['fname']} {$accountant['lname']},</p>
                <p>The payments for <strong>$month $year</strong> have been verified by the Director and now require your review and payroll processing.</p>
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='$payrollLink' class='button'>Review & Send Payroll List</a>
                </div>
                <p>Best regards,<br>SubseaOps</p>
            </div>
            <div class='footer'>
                This is an automated notification. Please do not reply to this email.
            </div>
        </div>
    </body>
    </html>
    ";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.office365.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'systems@mclarens.lk';
    $mail->Password = 'Com38518';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom('subseaops@worldsubsea.lk', 'SubseaOps');
    $mail->isHTML(true);

    // Send to OM
    $mail->clearAddresses();
    $mail->addAddress($om['email'], $om['fname'] . ' ' . $om['lname']);
    $mail->Subject = $subjectOM;
    $mail->Body = $bodyOM;
    $mail->send();
    // Log OM notification
    $stmt = $conn->prepare("INSERT INTO payment_notifications (publishedID, accountantID, recipientID, recipient_role, notification_type, email_sent, sent_at, reason, status) VALUES (?, ?, ?, 'operation_manager', 'director_verify', 1, NOW(), NULL, 1)");
    $stmt->bind_param("iii", $publishedID, $directorID, $om['userID']);
    $stmt->execute();

    // Send to Accountant
    $mail->clearAddresses();
    $mail->addAddress($accountant['email'], $accountant['fname'] . ' ' . $accountant['lname']);
    $mail->Subject = $subjectAcc;
    $mail->Body = $bodyAcc;
    $mail->send();
    // Log Accountant notification
    $stmt = $conn->prepare("INSERT INTO payment_notifications (publishedID, accountantID, recipientID, recipient_role, notification_type, email_sent, sent_at, reason, status) VALUES (?, ?, ?, 'accountant', 'director_verify', 1, NOW(), NULL, 1)");
    $stmt->bind_param("iii", $publishedID, $directorID, $accountant['userID']);
    $stmt->execute();

    $response['success'] = true;
    $response['message'] = "Notifications sent to OM and Accountant.";
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}
echo json_encode($response);
exit;