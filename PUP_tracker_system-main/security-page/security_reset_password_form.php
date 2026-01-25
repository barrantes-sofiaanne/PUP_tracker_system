<?php
require_once "../PHP/dbcon.php";
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function createPasswordConfirmationEmailBody($recipientName, $userType = 'user') {
    $accountType = ($userType === 'security' ? 'security' : 'PUPT Tracker System');
    $emailBody = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Password Changed</title></head><body style="margin:0; padding:0; background-color:#f4f4f4; font-family: Arial, Helvetica, sans-serif;">';
    $emailBody .= '<div style="max-width:600px; margin:20px auto; background-color:#ffffff; border:1px solid #dddddd; border-radius:8px; overflow:hidden;">';
    $emailBody .= '<div style="background-color:#8a1c1c; color:#ffffff; padding:20px; text-align:center;">';
    $emailBody .= '<img src="https://insync.ojt-ims-bsit.net/assets/PUP_logo.png" alt="PUPT Logo" style="max-width:80px; margin-bottom:10px;">';
    $emailBody .= '<h1 style="margin:0; font-size:24px;">PUPT Tracker System</h1>';
    $emailBody .= '</div>';
    $emailBody .= '<div style="padding:20px 30px; color:#333333; line-height:1.6;">';
    $emailBody .= "<p>Hello " . htmlspecialchars($recipientName) . ",</p>";
    $emailBody .= "<p>This is a confirmation that the password for your " . htmlspecialchars($accountType) . " account has been successfully changed.</p>";
    $emailBody .= "<p>If you did not authorize this change, please contact support immediately.</p>";
    $emailBody .= "<p>Regards,<br>System Administration</p>";
    $emailBody .= '</div>';
    $emailBody .= '<div style="background-color:#f0f0f0; padding:15px 30px; text-align:center; font-size:12px; color:#777777;">';
    $emailBody .= '&copy; ' . date("Y") . ' PUPT Tracker System. All rights reserved.';
    $emailBody .= '</div>';
    $emailBody .= '</div></body></html>';
    return $emailBody;
}

$token_valid = false;
$message = "";
$message_type = "";
$security_id_to_reset = null;
$security_email_for_notification = null;
$security_first_name_for_notification = null;

if (isset($_GET['token'])) {
    $received_token = $_GET['token'];
    $token_hash = hash('sha256', $received_token);

    if (!$conn) {
        $message = "Database connection failed.";
        $message_type = "error";
    } else {
        $sql = "SELECT s.id, si.firstname, s.email, s.reset_token_expires_at 
                FROM security s 
                JOIN security_info si ON s.id = si.security_id 
                WHERE s.reset_token_hash = ? LIMIT 1";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $token_hash);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $security_row = $result->fetch_assoc();
                if (strtotime($security_row['reset_token_expires_at']) > time()) {
                    $token_valid = true;
                    $security_id_to_reset = $security_row['id'];
                    $security_email_for_notification = $security_row['email'];
                    $security_first_name_for_notification = $security_row['firstname'];
                }
            }
            $stmt->close();
        }
        if (!$token_valid && empty($message)) {
            $message = "Invalid or expired password reset link.";
            $message_type = "error";
        }
    }
} else {
    $message = "No reset token provided.";
    $message_type = "error";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid && isset($_POST['password'], $_POST['confirm_password'])) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($password) || empty($confirm_password) || $password !== $confirm_password || strlen($password) < 8) {
        $message = ($password !== $confirm_password) ? "Passwords do not match." : "Password must be at least 8 characters long.";
        $message_type = "error";
    } else {
        if ($conn) {
            $new_password_hash = password_hash($password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE security SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?";
            
            if ($update_stmt = $conn->prepare($update_sql)) {
                $update_stmt->bind_param("si", $new_password_hash, $security_id_to_reset);
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
                        $mail->addAddress($security_email_for_notification, $security_first_name_for_notification);
                        $mail->isHTML(true);
                        $mail->Subject = 'Your Security Password Has Been Changed - PUPT Tracker System';
                        $mail->Body    = createPasswordConfirmationEmailBody($security_first_name_for_notification, 'security');
                        $mail->send();
                    } catch (Exception $e) {
                        // Log error but don't stop the user
                    }

                    $_SESSION['security_password_reset_success'] = "Your password has been reset! You can now log in.";
                    if ($conn) $conn->close();
                    header("Location: security_login.php");
                    exit();
                }
                $update_stmt->close();
            }
        }
    }
}

if ($conn && ($_SERVER["REQUEST_METHOD"] != "POST" || !empty($message_type) && $message_type == "error") ) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security - Reset Your Password</title>
    <link rel="stylesheet" href="./security_login_style.css">
     <style>
        .right-panel form p.message { padding: 10px; margin-bottom: 15px; border-radius: 5px; text-align: center; }
        .right-panel form p.message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .right-panel form p.message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <h2>Set New Security Password</h2>
            <?php if ($token_valid): ?>
            <p>Please enter your new password below.</p>
            <?php endif; ?>
        </div>
        <div class="right-panel">
            <img src="../assets/PUP_logo.png" alt="PUP Logo" class="logo">
            <h3>Create New Security Password</h3>
            <?php if (!empty($message)): ?>
                <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
            <?php endif; ?>

            <?php if ($token_valid): ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?token=<?php echo htmlspecialchars(isset($_GET['token']) ? $_GET['token'] : ''); ?>" method="POST">
                <div class="input-group">
                    <input type="password" name="password" placeholder="New Password" required>
                </div>
                <div class="input-group">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                </div>
                <button type="submit" class="login-btn">Reset Password</button>
            </form>
            <?php endif; ?>
            <p style="text-align: center; margin-top: 20px;">
                <a href="security_login.php" style="color: #8a1c1c; text-decoration: none;">Back to Login</a>
            </p>
        </div>
    </div>
</body>
</html>