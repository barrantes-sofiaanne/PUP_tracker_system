<?php
function log_user_action($conn, $action_type, $target_user_type, $target_user_identifier, $details = '') {
    $admin_name = 'Admin';

    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['admin_fullname'])) {
        $admin_name = $_SESSION['admin_fullname'];
    }

    $stmt = $conn->prepare("INSERT INTO user_management_history (performed_by_admin_name, action_type, target_user_type, target_user_identifier, details) VALUES (?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("sssss", $admin_name, $action_type, $target_user_type, $target_user_identifier, $details);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("Failed to prepare history log statement: " . $conn->error);
    }
}
?>