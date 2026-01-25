<?php
include 'dbcon.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sign-up</title>
    <link rel="stylesheet" href="/CSS/admin_signup_style.css">
</head>
<body>
    <div class="signup-container">
      <div class="signup-box">
        <div class="head-container">
            <img src="/assets/PUP_logo.png" alt="PUP Logo" class="logo">
            <h2>Create an Account</h2>
        </div>

        <form action="" method="POST">
            <div class="row">
                <input type="text" name="firstname" placeholder="First Name" required>
                <input type="email" name="email" placeholder="Email" required>
            </div>
            <div class="row">
                <input type="text" name="middlename" placeholder="Middle Name">
                <input type="password" name="password" placeholder="New Password" required>
            </div>
            <div class="row">
                <input type="text" name="lastname" placeholder="Last Name" required>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>

            <p class="login-text">Already have an account? <a href="/PHP/admin_login.php">Log in</a></p>

                <button type="submit" name="signup" class="signup-btn">Sign Up</button>
        </form>
      </div>
    </div>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
        $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
        $middlename = mysqli_real_escape_string($conn, $_POST['middlename']);
        $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Debug: Check if data is received
        echo "<script>console.log('Email entered: $email');</script>";

        // Check if passwords match
        if ($password !== $confirm_password) {
            echo "<script>alert('Passwords do not match!');</script>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Securely hash password

            // Check if email already exists
            $check_email = "SELECT * FROM admin_info_tbl WHERE email='$email'";
            $result = mysqli_query($conn, $check_email);

            if (mysqli_num_rows($result) > 0) {
                echo "<script>alert('Email already exists!');</script>";
            } else {
                $query = "INSERT INTO admin_info_tbl (firstname, middlename, lastname, email, password) 
                          VALUES ('$firstname', '$middlename', '$lastname', '$email', '$hashed_password')";

                // Debug: Show query being executed
                echo "<script>console.log('Query: " . addslashes($query) . "');</script>";

                if (mysqli_query($conn, $query)) {
                    echo "<script>alert('Admin registered successfully!'); window.location.href='admin_login.php';</script>";
                } else {
                    echo "<script>alert('Error: " . mysqli_error($conn) . "');</script>";
                }
            }
        }
    }
    ?>
</body>
</html>
