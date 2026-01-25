<?php
session_start();
include '../PHP/dbcon.php';

$errors = [];
$email_display = "";

if (isset($_SESSION['admin_password_reset_success'])) {
    $success_message = $_SESSION['admin_password_reset_success'];
    unset($_SESSION['admin_password_reset_success']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    if (isset($_POST['email'])) {
        $email_display = htmlspecialchars($_POST['email']);
    }

    $email_for_query = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $password_posted = $_POST['password'];

    if (empty($email_for_query)) {
        $errors['email_input'] = "Email is required!";
    }
    if (empty($password_posted)) {
        $errors['password_input'] = "Password is required!";
    }

    if (empty($errors['email_input']) && empty($errors['password_input'])) {
        if (!$conn) {
            $errors['db_error'] = "Database connection failed.";
        } else {
            $query = "SELECT id, email, password FROM admins WHERE email=?";
            if ($stmt = $conn->prepare($query)) {
                $stmt->bind_param("s", $email_for_query);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $admin_account = $result->fetch_assoc();
                    if (password_verify($password_posted, $admin_account['password'])) {
                        $_SESSION['admin_id'] = $admin_account['id'];
                        $_SESSION['admin_email'] = $admin_account['email'];
                        
                        $info_stmt = $conn->prepare("SELECT firstname FROM admin_info_tbl WHERE admin_id = ?");
                        if($info_stmt) {
                            $info_stmt->bind_param("i", $admin_account['id']);
                            $info_stmt->execute();
                            $info_result = $info_stmt->get_result();
                            if($info_user = $info_result->fetch_assoc()){
                                $_SESSION['admin_first_name'] = $info_user['firstname'];
                            }
                            $info_stmt->close();
                        }
                        
                        header("Location: ../admin-dashboard/admin_homepage.php");
                        exit;
                    } else {
                        $errors['login_error'] = "Incorrect email or password! Please try again.";
                    }
                } else {
                    $errors['login_error'] = "Incorrect email or password! Please try again.";
                }
                $stmt->close();
            } else {
                $errors['db_error'] = "Database query preparation failed.";
            }
            if ($conn) mysqli_close($conn);
        }
    }
} elseif ($conn && $_SERVER["REQUEST_METHOD"] != "POST") {
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUP Admin Login</title>
    <link rel="stylesheet" href="./admin_login_style.css">
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <h2>Welcome PUPT Admin!</h2>
            <p>Manage the system efficiently and effectively.</p>
        </div>
        <div class="right-panel">
            <img src="../assets/PUP_logo.png" alt="PUP Logo" class="logo">
            <h3>Admin Account</h3>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <?php if (!empty($errors['login_error'])): ?>
                    <p class="error-message main-error"><?php echo $errors['login_error']; ?></p>
                <?php endif; ?>
                <?php if (!empty($errors['db_error'])): ?>
                    <p class="error-message main-error"><?php echo $errors['db_error']; ?></p>
                <?php endif; ?>
                <?php if (isset($success_message)): ?>
                    <p class="message success" style="padding: 10px; margin-bottom: 15px; border-radius: 5px; text-align: center; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;"><?php echo htmlspecialchars($success_message); ?></p>
                <?php endif; ?>
                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email_display); ?>" required>
                    <?php if (!empty($errors['email_input'])): ?>
                        <span class="error-message"><?php echo $errors['email_input']; ?></span>
                    <?php endif; ?>
                </div>
                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required>
                    <?php if (!empty($errors['password_input'])): ?>
                        <span class="error-message"><?php echo $errors['password_input']; ?></span>
                    <?php endif; ?>
                </div>
                <p class="forget"><a href="admin_request_password_reset.php">Forgot password?</a></p>
                <button type="submit" name="login" class="login-btn">Log in</button>
                <p class="signup-text" style="margin-top: 15px; text-align: center;">
                    For account concerns, contact the super administrator.
                </p>
            </form>
        </div>
    </div>
</body>
</html>