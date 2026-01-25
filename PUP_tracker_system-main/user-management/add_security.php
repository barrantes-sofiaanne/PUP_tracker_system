<?php
ob_start();
session_start();
require '../PHP/dbcon.php';
require_once './history_logger.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

function generateUserPassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+-=[]{}|';
    $password = '';
    $characterCount = strlen($characters);
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, $characterCount - 1)];
    }
    return $password;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($first_name) || empty($last_name) || empty($position) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['error'] = 'Required fields are missing or email is invalid.';
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM security WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $response['error'] = 'Email already exists for a security user.';
        } else {
            if ($conn->begin_transaction()) {
                try {
                    $plain_text_password = generateUserPassword(12);
                    $hashed_password = password_hash($plain_text_password, PASSWORD_DEFAULT);
                    $default_status_id = 1; // Active
                    $default_role_id = 3;   // Security

                    $stmt_security = $conn->prepare("INSERT INTO security (email, password, created_at) VALUES (?, ?, NOW())");
                    $stmt_security->bind_param("ss", $email, $hashed_password);
                    $stmt_security->execute();
                    $security_primary_id = $conn->insert_id;
                    $stmt_security->close();
                    if (!$security_primary_id) throw new Exception("Failed to create security user in primary table.");

                    $stmt_security_info = $conn->prepare("INSERT INTO security_info (security_id, firstname, middlename, lastname, Position, status_id, role_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_security_info->bind_param("issssii", $security_primary_id, $first_name, $middle_name, $last_name, $position, $default_status_id, $default_role_id);
                    $stmt_security_info->execute();
                    $stmt_security_info->close();

                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'Security user added successfully.'; 
                    log_user_action($conn, 'Add Security', 'Security', $email, 'Created new security user: ' . $first_name . ' ' . $last_name . '.');
                    
                    $mail = new PHPMailer(true);
                    try {
                        $mail->SMTPDebug = SMTP::DEBUG_OFF; 
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'pupinsync@gmail.com';
                        $mail->Password   = 'rnjrnircjdbuqhqm'; 
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                        $mail->setFrom('pupinsync@gmail.com', 'PUPT Tracker System Admin');
                        $mail->addAddress($email, $first_name . ' ' . $last_name);
                        $mail->isHTML(true);
                        $mail->Subject = 'Your Security Account for PUPT Tracker System';
                        
                        // Use the server's root to build a full URL for the login page
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                        $domain_name = $_SERVER['HTTP_HOST'];
                        $base_url = $protocol . $domain_name;
                        $login_path = "/security-page/security_login.php"; // Changed from ../ to /
                        $security_login_url = $base_url . $login_path;


                        $emailBody = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Security Account Created</title></head><body style="margin:0; padding:0; background-color:#f4f4f4; font-family: Arial, Helvetica, sans-serif;">';
                        $emailBody .= '<div style="max-width:600px; margin:20px auto; background-color:#ffffff; border:1px solid #dddddd; border-radius:8px; overflow:hidden;">';
                        $emailBody .= '<div style="background-color:#8a1c1c; color:#ffffff; padding:20px; text-align:center;">';
                        $emailBody .= '<img src="https://insync.ojt-ims-bsit.net/assets/PUP_logo.png" alt="PUPT Logo" style="max-width:80px; margin-bottom:10px;">';
                        $emailBody .= '<h1 style="margin:0; font-size:24px;">PUPT Tracker System</h1>';
                        $emailBody .= '</div>';
                        $emailBody .= '<div style="padding:20px 30px; color:#333333; line-height:1.6;">';
                        $emailBody .= "<p>Hello " . htmlspecialchars($first_name) . ",</p>";
                        $emailBody .= "<p>A security account has been created for you on the PUPT Tracker System.</p>";
                        $emailBody .= "<p>Your login details are:<br>";
                        $emailBody .= "Email / Username: <strong style=\"color:#555555;\">" . htmlspecialchars($email) . "</strong><br>";
                        $emailBody .= "Temporary Password: <strong style=\"color:#555555;\">" . htmlspecialchars($plain_text_password) . "</strong></p>";
                        $emailBody .= "<p>You can log in to your account using the button below:</p>";
                        $emailBody .= "<p style=\"text-align:center;\"><a href='" . $security_login_url . "' style='display:inline-block; background-color:#8a1c1c; color:#ffffff; padding:12px 25px; text-decoration:none; border-radius:5px; font-size:16px;'>Go to Security Login Page</a></p>";
                        $emailBody .= "<p>Please keep these credentials secure. It is recommended to change your password upon first login.</p>";
                        $emailBody .= "<p>Regards,<br>System Administration</p>";
                        $emailBody .= '</div>';
                        $emailBody .= '<div style="background-color:#f0f0f0; padding:15px 30px; text-align:center; font-size:12px; color:#777777;">';
                        $emailBody .= '&copy; ' . date("Y") . ' PUPT Tracker System. All rights reserved.';
                        $emailBody .= '</div>';
                        $emailBody .= '</div></body></html>';

                        $mail->Body = $emailBody;
                        $mail->AltBody = "Hello " . htmlspecialchars($first_name) . ",\n\nA security account has been created for you on the PUPT Tracker System.\n\nYour login details are:\nEmail / Username: " . htmlspecialchars($email) . "\nTemporary Password: " . htmlspecialchars($plain_text_password) . "\n\nYou can log in at the following address: " . $security_login_url . "\n\nPlease keep these credentials secure.\n\nRegards,\nSystem Administration";
                        
                        $mail->send();
                        $response['email_status'] = 'Security account created and notification email sent.';
                    } catch (Exception $e_mail) {
                        error_log("Mailer Error for new security user {$email}: " . $mail->ErrorInfo);
                        $response['email_status'] = 'Security account created, but notification email could not be sent.';
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['error'] = "Transaction failed: " . $e->getMessage();
                }
            } else {
                $response['error'] = "Failed to start transaction.";
            }
        }
        $checkStmt->close();
    }
} else {
    $response['error'] = 'Invalid request method.';
}

if (is_object($conn)) $conn->close();
ob_end_clean();
header('Content-Type: application/json');
echo json_encode($response);
?>