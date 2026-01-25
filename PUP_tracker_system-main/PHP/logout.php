<?php
session_start();

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

$redirect_location = "../index.php"; 

/*if (isset($_GET['role'])) {
    $role = $_GET['role'];
    if ($role == 'admins') {
        $redirect_location = "../admin-login/admin_login.php";
    } elseif ($role == 'student') {
        $redirect_location = "../student-page/student_login.php";
    } elseif ($role == 'security') {
        $redirect_location = "../security-login/security_login.php";
    }
}*/

header("Location: " . $redirect_location);
exit();
?>