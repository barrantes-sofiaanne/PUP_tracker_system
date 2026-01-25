<?php
require_once '../PHP/dbcon.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
$response = ['success' => false];

if (isset($_SESSION['user_student_number']) && isset($conn)) {
    $student_number = $_SESSION['user_student_number'];

    $stmt = $conn->prepare("UPDATE notifications_tbl SET is_read = TRUE WHERE student_number = ? AND is_read = FALSE");
    $stmt->bind_param("s", $student_number);
    
    if ($stmt->execute()) {
        $response['success'] = true;
    }
    $stmt->close();
}

echo json_encode($response);
exit;