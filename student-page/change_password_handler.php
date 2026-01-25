<?php
require_once '../PHP/dbcon.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['current_user_id'])) {
    $response['message'] = 'Authentication error. Please log in again.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_new_password = $_POST['confirm_new_password'] ?? '';
$user_id = $_SESSION['current_user_id'];

if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
    $response['message'] = 'Please fill in all password fields.';
    echo json_encode($response);
    exit;
}

if (strlen($new_password) < 8) {
    $response['message'] = 'New password must be at least 8 characters long.';
    echo json_encode($response);
    exit;
}

if ($new_password !== $confirm_new_password) {
    $response['message'] = 'New passwords do not match.';
    echo json_encode($response);
    exit;
}

if (!isset($conn) || !$conn) {
     $response['message'] = 'Database connection could not be established.';
     echo json_encode($response);
     exit;
}

$stmt = $conn->prepare("SELECT password_hash FROM users_tbl WHERE user_id = ?");
if (!$stmt) {
    $response['message'] = 'Database error: Failed to prepare statement.';
    echo json_encode($response);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if (password_verify($current_password, $user['password_hash'])) {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_stmt = $conn->prepare("UPDATE users_tbl SET password_hash = ? WHERE user_id = ?");
        $update_stmt->bind_param("si", $new_password_hash, $user_id);

        if ($update_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Password changed successfully!';
        } else {
            $response['message'] = 'Error updating password.';
        }
        $update_stmt->close();
    } else {
        $response['message'] = 'Incorrect current password.';
    }
} else {
    $response['message'] = 'User not found.';
}
$stmt->close();
$conn->close();

echo json_encode($response);
?>