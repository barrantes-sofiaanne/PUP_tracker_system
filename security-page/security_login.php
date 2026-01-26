<?php
session_start();
include '../PHP/dbcon.php';

$errors = [];
$email_display = "";

if (isset($_SESSION['security_password_reset_success'])) {
    $success_message = $_SESSION['security_password_reset_success'];
    unset($_SESSION['security_password_reset_success']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    if (isset($_POST['email'])) {
        $email_display = htmlspecialchars($_POST['email']);
    }

    $email_for_query = trim($_POST['email']);
    $password_posted = $_POST['password'];

    if (empty($email_for_query)) {
        $errors['email_input'] = "Email Address is required!";
    } elseif (!filter_var($email_for_query, FILTER_VALIDATE_EMAIL)) {
        $errors['email_input'] = "Invalid email format!";
    }
    
    if (empty($password_posted)) {
        $errors['password_input'] = "Password is required!";
    }

    if (empty($errors)) {
        if (!$conn) {
            $errors['db_error'] = "Database connection failed.";
        } else {
            $query = "SELECT s.id, s.email, s.password, si.firstname 
                      FROM security s 
                      LEFT JOIN security_info si ON s.id = si.security_id 
                      WHERE s.email = ?";
            
            if ($stmt = $conn->prepare($query)) {
                $stmt->bind_param("s", $email_for_query);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $security_account = $result->fetch_assoc();
                    
                    if (password_verify($password_posted, $security_account['password'])) {
                        $_SESSION['security_id'] = $security_account['id'];
                        $_SESSION['security_email'] = $security_account['email'];
                        $_SESSION['security_first_name'] = $security_account['firstname'];
                        
                        $stmt->close();
                        $conn->close();
                        header("Location: ../security-page/security_dashboard.php");
                        exit;
                    } else {
                        $errors['login_error'] = "Incorrect password! Please try again.";
                    }
                } else {
                    $errors['login_error'] = "We could not find a security account with that email.";
                }
                $stmt->close();
            } else {
                $errors['db_error'] = "Database query preparation failed.";
            }
        }
    }
}

if ($conn && $conn->ping()) {
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUP Security Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./security_login_style.css">
</head>
<body>
    <div class="login-container <?php echo (!empty($errors)) ? 'no-anim' : ''; ?>">
        
        <img src="../assets/PUP_logo.png" alt="PUP Logo" class="logo">

        <div class="welcome-panel">
            <h2>Welcome PUPT Security!</h2>
            <p>Ensuring a safe and secure campus environment.</p>
        </div>

        <div class="login-form-wrapper">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                
                <?php if (!empty($errors['login_error'])): ?>
                    <p class="message error"><?php echo $errors['login_error']; ?></p>
                <?php endif; ?>
                
                <?php if (!empty($errors['db_error'])): ?>
                    <p class="message error"><?php echo $errors['db_error']; ?></p>
                <?php endif; ?>

                <?php if (isset($success_message)): ?>
                    <p class="message success"><?php echo htmlspecialchars($success_message); ?></p>
                <?php endif; ?>
                
                <div class="input-group">
                    <input type="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($email_display); ?>" required>
                    <?php if (!empty($errors['email_input'])): ?>
                        <span class="error-message"><?php echo $errors['email_input']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    
                    <span class="password-toggle-icon" onclick="togglePassword()">
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>

                    <?php if (!empty($errors['password_input'])): ?>
                        <span class="error-message"><?php echo $errors['password_input']; ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" name="login" class="login-btn">Log In</button>
                
                <div class="form-footer">
                    <a href="../index.php" class="back-link">Back to Home</a>
                    <a href="security_request_password_reset.php" class="forgot-password">Forgot password?</a>
                </div>

            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            var passwordInput = document.getElementById("password");
            var iconContainer = document.querySelector(".password-toggle-icon");
            var eyeOpenHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>';
            var eyeClosedHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>';

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                iconContainer.innerHTML = eyeClosedHTML;
            } else {
                passwordInput.type = "password";
                iconContainer.innerHTML = eyeOpenHTML;
            }
        }
    </script>
</body>
</html>