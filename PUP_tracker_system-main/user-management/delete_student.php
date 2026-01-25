<?php
ob_start();
session_start();
require '../PHP/dbcon.php';
require_once './history_logger.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['student_number'])) {
    $student_number = trim($_POST['student_number']);

    if (empty($student_number)) {
        $response['error'] = 'Student number is required.';
    } else {
        $info_stmt = $conn->prepare("SELECT first_name, last_name FROM users_tbl WHERE student_number = ?");
        $info_stmt->bind_param("s", $student_number);
        $info_stmt->execute();
        $result = $info_stmt->get_result();
        $student_info = $result->fetch_assoc();
        $info_stmt->close();

        $deleteQuery = "DELETE FROM users_tbl WHERE student_number = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("s", $student_number);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Student deleted successfully.';
                unset($response['error']);

                $student_name = $student_info ? ($student_info['first_name'] ?? '') . ' ' . ($student_info['last_name'] ?? '') : 'N/A';
                log_user_action($conn, 'Delete Student', 'Student', $student_number, 'Deleted student account for ' . trim($student_name) . '.');
            } else {
                $response['error'] = 'Student not found or already deleted.';
            }
        } else {
            $response['error'] = 'Failed to delete student: ' . $stmt->error;
        }
        $stmt->close();
    }
} else {
    $response['error'] = 'Invalid request method or missing student number.';
}

mysqli_close($conn);
ob_end_clean();
header('Content-Type: application/json');
echo json_encode($response);
?>