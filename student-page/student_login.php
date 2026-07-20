<?php
require_once "../PHP/dbcon.php";
session_start();
require_once '../maintenance/maintenance_check.php';
$errors = [];
$studentNumber = $password = "";

$max_attempts = 3;
$lockout_duration = 60;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $studentNumber = trim($_POST['student_number']);
    $password = $_POST['password'];

    if (isset($_SESSION['lockout_time'])) {
        $time_left = $_SESSION['lockout_time'] - time();
        if ($time_left > 0) {
            $errors['login_error'] = "Too many failed attempts. Please wait " . $time_left . " seconds.";
        } else {
            unset($_SESSION['lockout_time']);
            $_SESSION['login_attempts'] = 0;
        }
    }

    if (empty($studentNumber)) {
        $errors['student_number'] = "Student number is required!";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required!";
    }

    // Only proceed to query the database if there are no errors (including lockout errors)
    if (empty($errors)) {
        if (!$conn) {
            $errors['db_error'] = "Database connection failed. Please try again later or contact support.";
        } else {
            $sql = "SELECT user_id, first_name, student_number, email, password_hash, status_id FROM users_tbl WHERE student_number = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $studentNumber);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    
if (password_verify($password, $user["password_hash"]) || $password === $user["password_hash"]) {
                            if ($user['status_id'] == 1) {
                            
                            $_SESSION['login_attempts'] = 0;
                            unset($_SESSION['lockout_time']);

                            session_regenerate_id(true);
                            $_SESSION["current_user_id"] = $user["user_id"];
                            $_SESSION["user_first_name"] = $user["first_name"];
                            $_SESSION["user_student_number"] = $user["student_number"];
                            $_SESSION["user_email"] = $user["email"];
                            header("Location: ./student_dashboard.php");
                            exit();
                        } else {
                            $errors['login_error'] = "Your account has been deactivated. Please contact an administrator.";
                        }
                    } else {
                        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;

                        if ($_SESSION['login_attempts'] >= $max_attempts) {
                            $_SESSION['lockout_time'] = time() + $lockout_duration;
                            $errors['login_error'] = "Too many failed attempts. Please wait $lockout_duration seconds.";
                        } else {
                            $attempts_left = $max_attempts - $_SESSION['login_attempts'];
                            $errors['login_error'] = "Incorrect student number or password! Please try again. You have $attempts_left attempt(s) left.";
                        }
                    }
                } else {
                    $errors['login_error'] = "Incorrect student number or password! Please try again.";
                }
                $stmt->close();
            } else {
                $errors['db_error'] = "Database query failed. Please try again later.";
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
    <title>PUP Student Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./student_login_style.css">
</head>
<body>
    <div class="login-container <?php echo (!empty($errors)) ? 'no-anim' : ''; ?>">
        
        <img src="../assets/PUP_logo.png" alt="PUP Logo" class="logo">

        <div class="welcome-panel">
            <h2>Welcome PUPTians!</h2>
            <p>Access your records and stay updated.</p>
        </div>

        <div class="login-form-wrapper">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                
                <?php if (!empty($errors['login_error'])): ?>
                    <p class="message error"><?php echo $errors['login_error']; ?></p>
                <?php endif; ?>
                
                <?php if (!empty($errors['db_error'])): ?>
                    <p class="message error"><?php echo $errors['db_error']; ?></p>
                <?php endif; ?>

                <?php if (isset($_SESSION['password_reset_success'])): ?>
                    <p class="message success"><?php echo htmlspecialchars($_SESSION['password_reset_success']); ?></p>
                    <?php unset($_SESSION['password_reset_success']); ?>
                <?php endif; ?>

                <div class="input-group">
                    <input id="student_number" type="text" name="student_number" placeholder="Student Number" value="<?php echo htmlspecialchars($studentNumber); ?>" required>
                    <?php if (!empty($errors['student_number'])): ?>
                        <span class="error-message"><?php echo $errors['student_number']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <input id="password" type="password" name="password" placeholder="Password" required>
                    
                    <span class="password-toggle-icon" onclick="togglePassword()">
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>

                    <?php if (!empty($errors['password'])): ?>
                        <span class="error-message"><?php echo $errors['password']; ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="login-btn">Log In</button>
                
                <div class="form-footer">
                    <a href="../index.php" class="back-link">Back to Home</a>
                    <a href="request_password_reset.php" class="forgot-password">Forgot password?</a>
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