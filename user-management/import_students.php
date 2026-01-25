<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
session_start();
include '../PHP/dbcon.php';
require_once './history_logger.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function generateRandomPassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+-=[]{}|';
    $password = '';
    $characterCount = strlen($characters);
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, $characterCount - 1)];
    }
    return $password;
}

function sendCredentialEmail($conn, $email, $first_name, $last_name, $student_number, $plain_text_password) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'pupinsync@gmail.com';
        $mail->Password = 'rnjrnircjdbuqhqm';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
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
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error for student {$student_number} ({$email}): " . $mail->ErrorInfo);
        return false;
    }
}

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in again.']);
    exit();
}

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    $response['message'] = 'No file uploaded or invalid request method.';
    echo json_encode($response);
    exit();
}

$csvFile = $_FILES['csv_file']['tmp_name'];
$import_type = $_POST['import_type'] ?? 'activate';

if (!is_uploaded_file($csvFile)) {
    $response['message'] = 'File upload failed.';
    echo json_encode($response);
    exit();
}

mysqli_autocommit($conn, false);

if ($import_type === 'deactivate') {
    $deactivatedCount = 0;
    $failedEntries = [];
    $status_id_inactive = 2; 

    $file = fopen($csvFile, 'r');
    $header = fgetcsv($file);
    if (empty($header) || strtolower(trim($header[0])) !== 'student_number') {
        $response['message'] = 'Invalid file format for deactivation. The file must contain a single header column named "student_number".';
        fclose($file);
        echo json_encode($response);
        exit();
    }

    $update_stmt = mysqli_prepare($conn, "UPDATE users_tbl SET status_id = ? WHERE student_number = ? AND status_id != ?");

    while (($row = fgetcsv($file)) !== false) {
        $student_number = trim($row[0]);
        if (empty($student_number)) continue;

        mysqli_stmt_bind_param($update_stmt, "isi", $status_id_inactive, $student_number, $status_id_inactive);
        if (mysqli_stmt_execute($update_stmt)) {
            if (mysqli_stmt_affected_rows($update_stmt) > 0) {
                $deactivatedCount++;
                log_user_action($conn, "Deactivate Student (Import)", "Student", $student_number, "Deactivated via bulk import.");
            } else {
                $failedEntries[] = "Student '{$student_number}' not found or already inactive.";
            }
        } else {
            $failedEntries[] = "Failed to update student '{$student_number}'.";
        }
    }
    fclose($file);
    mysqli_stmt_close($update_stmt);

    if (empty($failedEntries)) {
        mysqli_commit($conn);
        $response['success'] = true;
        $response['message'] = "Successfully deactivated {$deactivatedCount} students.";
    } else {
        mysqli_rollback($conn);
        $response['message'] = "Process finished. Deactivated: {$deactivatedCount}. Failed/Skipped: " . count($failedEntries) . ". Errors: " . implode('; ', array_slice($failedEntries, 0, 5));
    }

} elseif ($import_type === 'activate') {
    $lookup = ['courses' => [], 'years' => [], 'sections' => [], 'genders' => []];
    $result = mysqli_query($conn, "SELECT course_name, course_id FROM course_tbl");
    while($row = mysqli_fetch_assoc($result)) { $lookup['courses'][strtolower(trim($row['course_name']))] = $row['course_id']; }
    $result = mysqli_query($conn, "SELECT year, year_id FROM year_tbl");
    while($row = mysqli_fetch_assoc($result)) { $lookup['years'][strtolower(trim($row['year']))] = $row['year_id']; }
    $result = mysqli_query($conn, "SELECT section_name, section_id FROM section_tbl");
    while($row = mysqli_fetch_assoc($result)) { $lookup['sections'][strtolower(trim($row['section_name']))] = $row['section_id']; }
    $result = mysqli_query($conn, "SELECT gender_name, gender_id FROM gender_tbl");
    while($row = mysqli_fetch_assoc($result)) { $lookup['genders'][strtolower(trim($row['gender_name']))] = $row['gender_id']; }

    $file = fopen($csvFile, 'r');
    $header = array_map('strtolower', array_map('trim', fgetcsv($file)));
    $expected_headers = ['student_number', 'first_name', 'middle_name', 'last_name', 'email', 'course_name', 'year', 'section', 'gender'];
    if (count(array_diff($expected_headers, $header)) > 0) {
        $response['message'] = 'CSV headers do not match the template. Please download and use the provided template.';
        fclose($file);
        echo json_encode($response);
        exit();
    }
    $col_map = array_flip($header);
    
    $importedCount = 0;
    $failedEntries = [];
    $credentials_to_email = [];

    $insert_stmt = mysqli_prepare($conn, "INSERT INTO users_tbl (student_number, first_name, middle_name, last_name, email, password_hash, course_id, year_id, section_id, gender_id, status_id, roles_id, new_until) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $check_stmt = mysqli_prepare($conn, "SELECT user_id FROM users_tbl WHERE student_number = ? OR email = ?");

    while (($row = fgetcsv($file)) !== false) {
        $student_number = trim($row[$col_map['student_number']] ?? '');
        $email = trim($row[$col_map['email']] ?? '');
        if (empty($student_number) || empty($email)) {
            $failedEntries[] = "Skipped row due to empty Student Number or Email.";
            continue;
        }

        mysqli_stmt_bind_param($check_stmt, "ss", $student_number, $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $failedEntries[] = "Skipped: Student Number '{$student_number}' or Email '{$email}' already exists.";
            continue;
        }

        $course_id = $lookup['courses'][strtolower(trim($row[$col_map['course_name']]))] ?? 0;
        $year_id = $lookup['years'][strtolower(trim($row[$col_map['year']]))] ?? 0;
        $section_id = $lookup['sections'][strtolower(trim($row[$col_map['section']]))] ?? 0;
        $gender_id = $lookup['genders'][strtolower(trim($row[$col_map['gender']]))] ?? 0;

        if ($course_id == 0 || $year_id == 0 || $section_id == 0 || $gender_id == 0) {
            $failedEntries[] = "Skipped '{$student_number}': Invalid Course, Year, Section, or Gender name provided.";
            continue;
        }

        $first_name = trim($row[$col_map['first_name']]);
        $middle_name = trim($row[$col_map['middle_name']]);
        $last_name = trim($row[$col_map['last_name']]);
        $password = generateRandomPassword();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $status_id = 1;
        $roles_id = 2;
        $new_until = date('Y-m-d H:i:s', strtotime('+24 hours'));

        mysqli_stmt_bind_param($insert_stmt, "ssssssiiiisds", $student_number, $first_name, $middle_name, $last_name, $email, $password_hash, $course_id, $year_id, $section_id, $gender_id, $status_id, $roles_id, $new_until);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $importedCount++;
            $credentials_to_email[] = ['email' => $email, 'first_name' => $first_name, 'last_name' => $last_name, 'student_number' => $student_number, 'password' => $password];
            log_user_action($conn, 'Import Student', 'Student', $student_number, "Imported student: {$first_name} {$last_name}.");
        } else {
            $failedEntries[] = "Database error for '{$student_number}': " . mysqli_stmt_error($insert_stmt);
        }
    }
    fclose($file);
    mysqli_stmt_close($insert_stmt);
    mysqli_stmt_close($check_stmt);

    if (empty($failedEntries)) {
        mysqli_commit($conn);
        $response['success'] = true;
        $response['message'] = "Successfully imported and activated {$importedCount} students. Sending credential emails...";

        foreach ($credentials_to_email as $cred) {
            sendCredentialEmail($conn, $cred['email'], $cred['first_name'], $cred['last_name'], $cred['student_number'], $cred['password']);
        }

    } else {
        mysqli_rollback($conn);
        if ($importedCount > 0) {
            $response['message'] = "Import failed due to errors. No students were added. Imported {$importedCount} before failure. Errors: " . implode('; ', array_slice($failedEntries, 0, 5));
        } else {
            $response['message'] = "Import failed. No students were added. Errors: " . implode('; ', array_slice($failedEntries, 0, 5));
        }
    }
}

ob_end_clean();
echo json_encode($response);
mysqli_close($conn);
exit();