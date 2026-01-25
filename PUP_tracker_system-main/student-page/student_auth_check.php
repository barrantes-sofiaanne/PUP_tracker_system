<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../PHP/dbcon.php';

if (!isset($_SESSION['current_user_id'])) {
    header("Location: ./student_login.php");
    exit();
}

$auth_check_stmt = $conn->prepare("SELECT status_id FROM users_tbl WHERE user_id = ?");

if (!$auth_check_stmt) {
    die("Database error. Please contact support.");
}

$auth_check_stmt->bind_param("i", $_SESSION['current_user_id']);
$auth_check_stmt->execute();
$auth_result = $auth_check_stmt->get_result();

if ($auth_result->num_rows === 1) {
    $user_status = $auth_result->fetch_assoc();
    
    if ($user_status['status_id'] != 1) {
        session_unset();
        session_destroy();
        header("Location: ./student_login.php?error=account_deactivated");
        exit();
    }
} else {
    session_unset();
    session_destroy();
    header("Location: ./student_login.php?error=invalid_session");
    exit();
}
$auth_check_stmt->close();
?>