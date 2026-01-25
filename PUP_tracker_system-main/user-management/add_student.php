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

function generateRandomPassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+-=[]{}|';
    $password = '';
    $characterCount = strlen($characters);
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, $characterCount - 1)];
    }
    return $password;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_number = trim($_POST['student_number'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course_id = (int)($_POST['course_id'] ?? 0);
    $year_id = (int)($_POST['year_id'] ?? 0);
    $section_id = (int)($_POST['section_id'] ?? 0);
    $status_id = (int)($_POST['status_id'] ?? 0);

    if (!empty($student_number) && !empty($first_name) && !empty($last_name) && !empty($email) && $course_id > 0 && $year_id > 0 && $section_id > 0 && $status_id > 0) {
        $checkStudentQuery = "SELECT student_number FROM users_tbl WHERE student_number = ?";
        $stmt_check_student = $conn->prepare($checkStudentQuery);
        $stmt_check_student->bind_param("s", $student_number);
        $stmt_check_student->execute();
        $stmt_check_student->store_result();

        if ($stmt_check_student->num_rows > 0) {
            $response['error'] = 'Student number already exists.';
        } else {
            $checkEmailQuery = "SELECT email FROM users_tbl WHERE email = ?";
            $stmt_check_email = $conn->prepare($checkEmailQuery);
            $stmt_check_email->bind_param("s", $email);
            $stmt_check_email->execute();
            $stmt_check_email->store_result();

            if ($stmt_check_email->num_rows > 0) {
                $response['error'] = 'Email address already exists.';
            } else {
                $plain_text_password = generateRandomPassword(12);
                $password_hash = password_hash($plain_text_password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users_tbl (student_number, first_name, middle_name, last_name, email, course_id, year_id, section_id, status_id, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt_insert = $conn->prepare($query);
                $stmt_insert->bind_param("sssssiiiis", $student_number, $first_name, $middle_name, $last_name, $email, $course_id, $year_id, $section_id, $status_id, $password_hash);
                
                if ($stmt_insert->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Student added successfully.';
                    unset($response['error']);
                    log_user_action($conn, 'Add Student', 'Student', $student_number, 'A new student account was created for ' . $first_name . ' ' . $last_name . '.');
                    
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
                        $mail->setFrom('pupinsync@gmail.com', 'PUPT Tracker System');
                        $mail->addAddress($email, $first_name . ' ' . $last_name);

                        $mail->isHTML(true);
                        $mail->Subject = 'Welcome! Your Account for PUPT Tracker System';
                        
                        $student_login_url = "https://insync.ojt-ims-bsit.net/student-page/student_login.php";

                        $emailBody = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Student Account Created</title></head><body style="margin:0; padding:0; background-color:#f4f4f4; font-family: Arial, Helvetica, sans-serif;">';
                        $emailBody .= '<div style="max-width:600px; margin:20px auto; background-color:#ffffff; border:1px solid #dddddd; border-radius:8px; overflow:hidden;">';
                        $emailBody .= '<div style="background-color:#8a1c1c; color:#ffffff; padding:20px; text-align:center;">';
                        $emailBody .= '<img src="https://insync.ojt-ims-bsit.net/assets/PUP_logo.png" alt="PUPT Logo" style="max-width:80px; margin-bottom:10px;">';
                        $emailBody .= '<h1 style="margin:0; font-size:24px;">PUPT Tracker System</h1>';
                        $emailBody .= '</div>';
                        $emailBody .= '<div style="padding:20px 30px; color:#333333; line-height:1.6;">';
                        $emailBody .= "<p>Hello " . htmlspecialchars($first_name) . ",</p>";
                        $emailBody .= "<p>Welcome to the PUPT Tracker System! Your student account has been successfully created.</p>";
                        $emailBody .= "<p>Here are your login credentials:<br>";
                        $emailBody .= "Student Number: <strong style=\"color:#555555;\">" . htmlspecialchars($student_number) . "</strong><br>";
                        $emailBody .= "Temporary Password: <strong style=\"color:#555555;\">" . htmlspecialchars($plain_text_password) . "</strong></p>";
                        $emailBody .= "<p>You can log in to your account using the button below:</p>";
                        $emailBody .= "<p style=\"text-align:center;\"><a href='" . $student_login_url . "' style='display:inline-block; background-color:#8a1c1c; color:#ffffff; padding:12px 25px; text-decoration:none; border-radius:5px; font-size:16px;'>Go to Student Login</a></p>";
                        $emailBody .= "<p>Please use these to access the system. We strongly recommend that you change your password after your first login for security reasons.</p>";
                        $emailBody .= "<p>Regards,<br>PUPT Tracker System Administration</p>";
                        $emailBody .= '</div>';
                        $emailBody .= '<div style="background-color:#f0f0f0; padding:15px 30px; text-align:center; font-size:12px; color:#777777;">';
                        $emailBody .= '&copy; ' . date("Y") . ' PUPT Tracker System. All rights reserved.';
                        $emailBody .= '</div>';
                        $emailBody .= '</div></body></html>';

                        $mail->Body = $emailBody;
                        $mail->AltBody = "Hello " . htmlspecialchars($first_name) . ",\n\nWelcome to the PUPT Tracker System! Your student account has been successfully created.\n\nHere are your login credentials:\nStudent Number: " . htmlspecialchars($student_number) . "\nTemporary Password: " . htmlspecialchars($plain_text_password) . "\n\nYou can log in at the following address: " . $student_login_url . "\n\nPlease use these to access the system.\n\nRegards,\nPUPT Tracker System Administration";
                        
                        $mail->send();
                        $response['email_status'] = 'Verification email sent successfully.';
                    } catch (Exception $e) {
                        error_log("Mailer Error for student {$student_number} ({$email}): " . $mail->ErrorInfo);
                        $response['email_status'] = 'Account created, but notification email could not be sent.';
                    }
                } else {
                    $response['error'] = 'Failed to add student: ' . $stmt_insert->error;
                }
                $stmt_insert->close();
            }
            $stmt_check_email->close();
        }
        $stmt_check_student->close();
    } else {
        $response['error'] = 'Missing or invalid required fields.';
    }
} else {
    $response['error'] = 'Invalid request method.';
}

if ($conn) {
    mysqli_close($conn);
}

ob_end_clean();
header('Content-Type: application/json');
echo json_encode($response);
?>