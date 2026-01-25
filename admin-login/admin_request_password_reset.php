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

ini_set('display_errors', 0);
error_reporting(E_ALL);

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_email = trim($_POST['email']);

    if (empty($admin_email)) {
        $message = "Please enter your email address.";
        $message_type = "error";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $message_type = "error";
    } else {
        if (!$conn) {
            $message = "Database connection failed.";
            $message_type = "error";
            error_log("DB Connection Error in admin_request_password_reset.php: " . (is_object($conn) ? $conn->connect_error : "Conn not object"));
        } else {
            $sql = "SELECT a.id, ai.firstname FROM admins a JOIN admin_info_tbl ai ON a.id = ai.admin_id WHERE a.email = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $admin_email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $admin = $result->fetch_assoc();
                    $admin_id = $admin['id'];
                    $admin_first_name = $admin['firstname'];

                    $token = bin2hex(random_bytes(50));
                    $token_hash = hash('sha256', $token);
                    $expires_at = date("Y-m-d H:i:s", time() + 3600);

                    $update_sql = "UPDATE admins SET reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?";
                    if ($update_stmt = $conn->prepare($update_sql)) {
                        $update_stmt->bind_param("ssi", $token_hash, $expires_at, $admin_id);
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

                                $mail->setFrom('pupinsync@gmail.com', 'PUPT Tracker System Admin');
                                $mail->addAddress($admin_email, $admin_first_name);

                                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                $host = $_SERVER['HTTP_HOST'];
                                $path = dirname($_SERVER['PHP_SELF']);
                                $reset_link = "{$protocol}://{$host}{$path}/admin_reset_password_form.php?token=" . $token;
                                
                                $mail->isHTML(true);
                                $mail->Subject = 'Admin Password Reset Request - PUPT Tracker System';
                                $mail->Body    = createPasswordResetEmailBody($admin_first_name, $reset_link, 'admin');
                                $mail->AltBody = "Hello " . htmlspecialchars($admin_first_name) . ",\n\nA password reset was requested for your admin account. Please visit the following link to reset your password: " . $reset_link . "\n\nThis link will expire in 1 hour. If you did not request this, please ignore this email.\n\nRegards,\nSystem Administration";
                                
                                $mail->send();
                                $message = "If an admin account exists for " . htmlspecialchars($admin_email) . ", a reset link has been sent.";
                                $message_type = "success";
                            } catch (Exception $e) {
                                error_log("Mailer Error for admin password reset {$admin_email}: " . $mail->ErrorInfo);
                                $message = "Account found, but email could not be sent. Contact support.";
                                $message_type = "error";
                            }
                        } else {
                            $message = "Error preparing account for reset.";
                            $message_type = "error";
                            error_log("DB Execute Error (update_stmt) in admin_request_password_reset.php: " . $update_stmt->error);
                        }
                        $update_stmt->close();
                    } else {
                        $message = "Database error preparing for reset.";
                        $message_type = "error";
                        error_log("DB Prepare Error (update_sql) in admin_request_password_reset.php: " . $conn->error);
                    }
                } else {
                    $message = "If an admin account exists for " . htmlspecialchars($admin_email) . ", a reset link has been sent.";
                    $message_type = "success";
                }
                $stmt->close();
            } else {
                $message = "Database query preparation failed.";
                $message_type = "error";
                error_log("DB Prepare Error (sql) in admin_request_password_reset.php: " . $conn->error);
            }
            if ($conn) $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Request Password Reset</title>
    <link rel="stylesheet" href="./admin_login_style.css">
    <style>
        .right-panel form p.message { padding: 10px; margin-bottom: 15px; border-radius: 5px; text-align: center; }
        .right-panel form p.message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .right-panel form p.message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <h2>Admin Password</h2>
            <p>Enter your admin email address to receive a password reset link.</p>
        </div>
        <div class="right-panel">
            <img src="../assets/PUP_logo.png" alt="PUP Logo" class="logo">
            <h3>Reset Admin Password</h3>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <?php if (!empty($message)): ?>
                    <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
                <?php endif; ?>
                <div class="input-group">
                    <input type="email" name="email" placeholder="Your Admin Email Address" required value="<?php echo isset($admin_email) ? htmlspecialchars($admin_email) : ''; ?>">
                </div>
                <button type="submit" class="login-btn">Send Password Reset Link</button>
                <p style="text-align: center; margin-top: 20px;">
                    <a href="admin_login.php" style="color: #8a1c1c; text-decoration: none;">Back to Admin Login</a>
                </p>
            </form>
        </div>
    </div>
</body>
</html>