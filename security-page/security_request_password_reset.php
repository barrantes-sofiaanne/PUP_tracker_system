<?php
require_once "../PHP/dbcon.php";
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function createPasswordResetEmailBody($recipientName, $resetLink, $userType = 'user') {
    $emailSubject = ($userType === 'security' ? 'Security' : '') . ' Password Reset';
    $accountType = ($userType === 'security' ? 'security' : '') . ' account';

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
    $security_email = trim($_POST['email']);

    if (empty($security_email) || !filter_var($security_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } else {
        if (!$conn) {
            $message = "Database connection failed.";
            $message_type = "error";
        } else {
            $sql = "SELECT s.id, si.firstname FROM security s JOIN security_info si ON s.id = si.security_id WHERE s.email = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $security_email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $security = $result->fetch_assoc();
                    $security_id = $security['id'];
                    $security_first_name = $security['firstname'];

                    $token = bin2hex(random_bytes(50));
                    $token_hash = hash('sha256', $token);
                    $expires_at = date("Y-m-d H:i:s", time() + 3600);

                    $update_sql = "UPDATE security SET reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?";
                    if ($update_stmt = $conn->prepare($update_sql)) {
                        $update_stmt->bind_param("ssi", $token_hash, $expires_at, $security_id);
                        if ($update_stmt->execute()) {
                            $mail = new PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host = 'smtp.gmail.com';
                                $mail->SMTPAuth = true;
                                $mail->Username = 'pupinsync@gmail.com';
                                $mail->Password = 'rnjrnircjdbuqhqm';
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port = 587;

                                $mail->setFrom('pupinsync@gmail.com', 'PUPT Tracker System');
                                $mail->addAddress($security_email, $security_first_name);

                                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                                $host = $_SERVER['HTTP_HOST'];
                                $path = dirname($_SERVER['PHP_SELF']);
                                $reset_link = "{$protocol}://{$host}{$path}/security_reset_password_form.php?token=" . $token;
                                
                                $mail->isHTML(true);
                                $mail->Subject = 'Security Password Reset Request - PUPT Tracker System';
                                $mail->Body    = createPasswordResetEmailBody($security_first_name, $reset_link, 'security');
                                $mail->send();
                                
                                $message = "A reset link has been sent to " . htmlspecialchars($security_email);
                                $message_type = "success";
                            } catch (Exception $e) {
                                $message = "Account found, but email could not be sent. Contact support.";
                                $message_type = "error";
                            }
                        }
                        $update_stmt->close();
                    }
                } else {
                    $message = "We could not find a security account with that email address.";
                    $message_type = "error";
                }
                $stmt->close();
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
    <title>Security - Request Password Reset</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./security_login_style.css">
</head>
<body>
    <div class="login-container <?php echo (!empty($message)) ? 'no-anim' : ''; ?>">
        
        <img src="../assets/PUP_logo.png" alt="PUP Logo" class="logo">

        <div class="welcome-panel">
            <h2>Forgot Password?</h2>
            <p>Enter your security email to receive a reset link.</p>
        </div>

        <div class="login-form-wrapper">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                
                <?php if (!empty($message)): ?>
                    <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
                <?php endif; ?>

                <div class="input-group">
                    <input type="email" name="email" placeholder="Your Security Email Address" required value="<?php echo isset($security_email) ? htmlspecialchars($security_email) : ''; ?>">
                </div>
                
                <button type="submit" class="login-btn">Send Reset Link</button>
                
                <div class="form-footer" style="justify-content: center;">
                    <a href="security_login.php" class="back-link">Back to Security Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>