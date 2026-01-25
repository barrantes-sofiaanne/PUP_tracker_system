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

function generateAdminPassword($length = 12) {
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
        $username = $email; 
        $checkStmt = $conn->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
        $checkStmt->bind_param("ss", $username, $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $response['error'] = 'Email (username) already exists.';
        } else {
            if ($conn->begin_transaction()) {
                try {
                    $plain_text_password = generateAdminPassword(12);
                    $hashed_password = password_hash($plain_text_password, PASSWORD_DEFAULT);
                    $default_status_id = 1; 
                    $default_role_id = 1;

                    $stmt_admins = $conn->prepare("INSERT INTO admins (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt_admins->bind_param("sss", $username, $email, $hashed_password);
                    $stmt_admins->execute();
                    $admin_primary_id = $conn->insert_id;
                    $stmt_admins->close();
                    if (!$admin_primary_id) throw new Exception("Failed to create admin in primary table.");

                    $stmt_admin_info = $conn->prepare("INSERT INTO admin_info_tbl (admin_id, firstname, middlename, lastname, Position, status_id, role_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_admin_info->bind_param("issssii", $admin_primary_id, $first_name, $middle_name, $last_name, $position, $default_status_id, $default_role_id);
                    $stmt_admin_info->execute();
                    $stmt_admin_info->close();

                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'Admin added successfully.'; 
                    log_user_action($conn, 'Add Admin', 'Admin', $email, 'Created new admin: ' . $first_name . ' ' . $last_name . '.');
                    
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
                        $mail->Subject = 'Your Admin Account for PUPT Tracker System';
                        
                        $admin_login_url = "https://insync.ojt-ims-bsit.net/PHP/admin_login.php";

                        $emailBody = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Admin Account Created</title></head><body style="margin:0; padding:0; background-color:#f4f4f4; font-family: Arial, Helvetica, sans-serif;">';
                        $emailBody .= '<div style="max-width:600px; margin:20px auto; background-color:#ffffff; border:1px solid #dddddd; border-radius:8px; overflow:hidden;">';
                        $emailBody .= '<div style="background-color:#8a1c1c; color:#ffffff; padding:20px; text-align:center;">';
                        $emailBody .= '<img src="https://insync.ojt-ims-bsit.net/assets/PUP_logo.png" alt="PUPT Logo" style="max-width:80px; margin-bottom:10px;">';
                        $emailBody .= '<h1 style="margin:0; font-size:24px;">PUPT Tracker System</h1>';
                        $emailBody .= '</div>';
                        $emailBody .= '<div style="padding:20px 30px; color:#333333; line-height:1.6;">';
                        $emailBody .= "<p>Hello " . htmlspecialchars($first_name) . ",</p>";
                        $emailBody .= "<p>An administrator account has been created for you on the PUPT Tracker System.</p>";
                        $emailBody .= "<p>Your login details are:<br>";
                        $emailBody .= "Email / Username: <strong style=\"color:#555555;\">" . htmlspecialchars($email) . "</strong><br>";
                        $emailBody .= "Temporary Password: <strong style=\"color:#555555;\">" . htmlspecialchars($plain_text_password) . "</strong></p>";
                        $emailBody .= "<p>You can log in to your account using the button below:</p>";
                        $emailBody .= "<p style=\"text-align:center;\"><a href='" . $admin_login_url . "' style='display:inline-block; background-color:#8a1c1c; color:#ffffff; padding:12px 25px; text-decoration:none; border-radius:5px; font-size:16px;'>Go to Admin Login</a></p>";
                        $emailBody .= "<p>Please keep these credentials secure. It is recommended to change your password upon first login if the system allows for this.</p>";
                        $emailBody .= "<p>Regards,<br>System Super Administration</p>";
                        $emailBody .= '</div>';
                        $emailBody .= '<div style="background-color:#f0f0f0; padding:15px 30px; text-align:center; font-size:12px; color:#777777;">';
                        $emailBody .= '&copy; ' . date("Y") . ' PUPT Tracker System. All rights reserved.';
                        $emailBody .= '</div>';
                        $emailBody .= '</div></body></html>';

                        $mail->Body = $emailBody;
                        $mail->AltBody = "Hello " . htmlspecialchars($first_name) . ",\n\nAn administrator account has been created for you on the PUPT Tracker System.\n\nYour login details are:\nEmail / Username: " . htmlspecialchars($email) . "\nTemporary Password: " . htmlspecialchars($plain_text_password) . "\n\nYou can log in at the following address: " . $admin_login_url . "\n\nPlease keep these credentials secure.\n\nRegards,\nSystem Super Administration";
                        
                        $mail->send();
                        $response['email_status'] = 'Admin account created and notification email sent.';
                    } catch (Exception $e_mail) {
                        error_log("Mailer Error for new admin {$email}: " . $mail->ErrorInfo);
                        $response['email_status'] = 'Admin account created, but notification email could not be sent.';
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