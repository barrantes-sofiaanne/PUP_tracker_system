<?php
require_once "../PHP/dbcon.php";
session_start();

$errors = [];
$studentNumber = $password = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $studentNumber = trim($_POST['student_number']);
    $password = $_POST['password'];

    if (empty($studentNumber)) {
        $errors['student_number'] = "Student number is required!";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required!";
    }

    if (empty($errors)) {
        if (!$conn) {
            $errors['db_error'] = "Database connection failed. Please try again later or contact support.";
        } else {
            // --- CHANGE 1: Added 'status_id' to the SQL query ---
            $sql = "SELECT user_id, first_name, student_number, email, password_hash, status_id FROM users_tbl WHERE student_number = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $studentNumber);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user["password_hash"])) {
                        
                        // --- CHANGE 2: Added the status check ---
                        // Check if the account is active (status_id = 1)
                        if ($user['status_id'] == 1) {
                            // Status is Active, proceed with login
                            session_regenerate_id(true); // Security measure
                            $_SESSION["current_user_id"] = $user["user_id"];
                            $_SESSION["user_first_name"] = $user["first_name"];
                            $_SESSION["user_student_number"] = $user["student_number"];
                            $_SESSION["user_email"] = $user["email"];
                            header("Location: ./student_dashboard.php");
                            exit();
                        } else {
                            // Account is Inactive, set an error message
                            $errors['login_error'] = "Your account has been deactivated. Please contact an administrator.";
                        }
                    } else {
                        $errors['login_error'] = "Incorrect student number or password! Please try again.";
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
    <div class="login-container">
        <div class="welcome-panel">
            <img src="../assets/PUP_logo.png" alt="PUP Logo" class="logo">
            <h2>Welcome PUPTians!</h2>
            <p>Access your records and stay updated.</p>
        </div>
        <div class="login-form-wrapper">
            <h3>Student Login</h3>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <?php if (!empty($errors['login_error'])): ?>
                    <p class="error-message main-error"><?php echo $errors['login_error']; ?></p>
                <?php endif; ?>
                <?php if (!empty($errors['db_error'])): ?>
                    <p class="error-message main-error"><?php echo $errors['db_error']; ?></p>
                <?php endif; ?>
                <?php
                if (isset($_SESSION['password_reset_success'])) {
                    echo '<p class="message success">' . htmlspecialchars($_SESSION['password_reset_success']) . '</p>';
                    unset($_SESSION['password_reset_success']);
                }
                ?>
                <div class="input-group">
                    <input id="student_number" type="text" name="student_number" placeholder="Student Number" value="<?php echo htmlspecialchars($studentNumber); ?>" required>
                    <?php if (!empty($errors['student_number'])): ?>
                        <span class="error-message"><?php echo $errors['student_number']; ?></span>
                    <?php endif; ?>
                </div>
                <div class="input-group">
                    <input id="password" type="password" name="password" placeholder="Password" required>
                    <?php if (!empty($errors['password'])): ?>
                        <span class="error-message"><?php echo $errors['password']; ?></span>
                    <?php endif; ?>
                </div>
                <div class="options-container">
                    <a href="request_password_reset.php" class="forgot-password">Forgot password?</a>
                </div>
                <button type="submit" class="login-btn">Log In</button>
            </form>
        </div>
    </div>
</body>
</html>