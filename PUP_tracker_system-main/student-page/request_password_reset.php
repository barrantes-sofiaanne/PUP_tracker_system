<?php
require_once "../PHP/dbcon.php";
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function createPasswordResetEmailBody($recipientName, $resetLink, $userType = 'user') {
    $emailSubject = ($userType === 'admin' ? 'Admin' : '') . ' Password Reset';
    $accountType = ($userType === 'admin' ? 'admin' : '') . ' account';

    $emailBody = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>'.htmlspecialchars($emailSubject).'</title></head><body style="margin:0; padding:0; background-color:#f4f4f4; font-family: Arial, Helvetica, sans-serif;">';
    $emailBody .= '<div style="max-width:600px; margin:20px auto; background-color:#ffffff; border:1px solid #dddddd; border-radius:8px; overflow:hidden;">';
    $emailBody .= '<div style="background-color:#8a1c1c; color:#ffffff; padding:20px; text-align:center;">';
    $emailBody .= '<img src="https://insync.ojt-ims-bsit.net/assets/PUP_logo.png" alt="PUPT Logo" style="max-width:80px; margin-bottom:10px;">';
    $emailBody .= '<h1 style="margin:0; font-size:24px;">PUPT Tracker System</h1>';
    $emailBody .= '</div>';
    $emailBody .= '<div style="padding:20px 30px; color:#333333; line-height:1.6;">';
    $emailBody .= "<p>Hello " . htmlspecialchars($recipientName) . ",</p>";
    $emailBody .= "<p>A password reset was requested for your ".htmlspecialchars($accountType)." on the PUPT Tracker System.</p>";
    $emailBody .= "<p>Please click the button below to reset your password:</p>";
    $emailBody .= "<p style=\"text-align:center;\"><a href='" . $resetLink . "' style='display:inline-block; background-color:#8a1c1c; color:#ffffff; padding:12px 25px; text-decoration:none; border-radius:5px; font-size:16px;'>Reset Your Password</a></p>";
    $emailBody .= "<p>If the button above doesn't work, copy and paste this link into your browser:<br><a href='" . $resetLink . "'>" . $resetLink . "</a></p>";
    $emailBody .= "<p>This link will expire in 1 hour. If you did not request this password reset, please ignore this email.</p>";
    $emailBody .= "<p>Regards,<br>System Administration</p>";
    $emailBody .= '</div>';
    $emailBody .= '<div style="background-color:#f0f0f0; padding:15px 30px; text-align:center; font-size:12px; color:#777777;">';
    $emailBody .= '&copy; ' . date("Y") . ' PUPT Tracker System. All rights reserved.';
    $emailBody .= '</div>';
    $emailBody .= '</div></body></html>';
    
    return $emailBody;
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_or_student_number = trim($_POST['email_or_student_number']);

    if (empty($email_or_student_number)) {
        $message = "Please enter your email address or student number.";
        $message_type = "error";
    } else {
        if (!$conn) {
            $message = "Database connection failed. Please try again later.";
            $message_type = "error";
        } else {
            if (filter_var($email_or_student_number, FILTER_VALIDATE_EMAIL)) {
                $sql = "SELECT user_id, first_name, email FROM users_tbl WHERE email = ?";
            } else {
                $sql = "SELECT user_id, first_name, email FROM users_tbl WHERE student_number = ?";
            }

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $email_or_student_number);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    $user_email = $user['email'];
                    $user_id = $user['user_id'];
                    $user_first_name = $user['first_name'];

                    $token = bin2hex(random_bytes(50));
                    $token_hash = hash('sha256', $token);
                    $expires_at = date("Y-m-d H:i:s", time() + 3600);

                    $update_sql = "UPDATE users_tbl SET reset_token_hash = ?, reset_token_expires_at = ? WHERE user_id = ?";
                    if ($update_stmt = $conn->prepare($update_sql)) {
                        $update_stmt->bind_param("ssi", $token_hash, $expires_at, $user_id);
                        if ($update_stmt->execute()) {
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
                                $mail->addAddress($user_email, $user_first_name);

                                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                $host = $_SERVER['HTTP_HOST'];
                                $path = dirname($_SERVER['PHP_SELF']);
                                $reset_link = "{$protocol}://{$host}{$path}/reset_password_form.php?token=" . $token;

                                $mail->isHTML(true);
                                $mail->Subject = 'Password Reset Request - PUPT Tracker System';
                                $mail->Body    = createPasswordResetEmailBody($user_first_name, $reset_link, 'user');
                                $mail->AltBody = "Hello " . htmlspecialchars($user_first_name) . ",\n\nYou requested a password reset. Please copy and paste this link into your browser: " . $reset_link . "\n\nThis link expires in one hour.";
                                
                                $mail->send();
                                $message = "If an account is associated with " . htmlspecialchars($email_or_student_number) . ", a password reset link has been sent to the registered email address.";
                                $message_type = "success";

                            } catch (Exception $e) {
                                error_log("Mailer Error for password reset {$user_email}: " . $mail->ErrorInfo);
                                $message = "We found your account, but could not send the reset email. Please try again later or contact support.";
                                $message_type = "error";
                            }
                        } else {
                            $message = "Error updating your account for password reset. Please try again.";
                            $message_type = "error";
                        }
                        $update_stmt->close();
                    } else {
                        $message = "Database error preparing for password reset. Please try again.";
                        $message_type = "error";
                    }
                } else {
                    $message = "If an account is associated with " . htmlspecialchars($email_or_student_number) . ", a password reset link has been sent to the registered email address.";
                    $message_type = "success";
                }
                $stmt->close();
            } else {
                $message = "Database query failed. Please try again later.";
                $message_type = "error";
            }
            if ($conn) {
                $conn->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Password Reset - PUPT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./student_login_style.css"> 
</head>
<body>
    <div class="login-container">
        <div class="welcome-panel">
            <img src="../assets/PUP_logo.png" alt="PUP Logo" class="logo">
            <h2>Forgot Your Password?</h2>
            <p>No problem. Enter your details and we'll help you reset it.</p>
        </div>
        <div class="login-form-wrapper">
            <h3>Reset Your Password</h3>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <?php if (!empty($message)): ?>
                    <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
                <?php endif; ?>

                <div class="input-group">
                    <input type="text" name="email_or_student_number" placeholder="Enter Student Number or Email" required value="<?php echo isset($email_or_student_number) ? htmlspecialchars($email_or_student_number) : ''; ?>">
                </div>
                
                <button type="submit" class="login-btn">Send Reset Link</button>
                <div class="options-container">
                    <a href="student_login.php" class="back-link">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>