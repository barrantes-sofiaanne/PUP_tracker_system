<?php
ob_start();
session_start();
require '../PHP/dbcon.php';
require_once './history_logger.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_id = (int)($_POST['admin_id'] ?? 0);

    if ($admin_id <= 0) {
        $response['error'] = 'A valid Admin ID is required.';
    } else {
        $info_stmt = $conn->prepare("SELECT a.email, i.firstname, i.lastname FROM admins a JOIN admin_info_tbl i ON a.id = i.admin_id WHERE a.id = ?");
        $info_stmt->bind_param("i", $admin_id);
        $info_stmt->execute();
        $admin_info = $info_stmt->get_result()->fetch_assoc();
        $info_stmt->close();

        if ($conn->begin_transaction()) {
            try {
                $stmt_info = $conn->prepare("DELETE FROM admin_info_tbl WHERE admin_id = ?");
                $stmt_info->bind_param("i", $admin_id);
                $stmt_info->execute();
                $stmt_info->close();

                $stmt_admins = $conn->prepare("DELETE FROM admins WHERE id = ?");
                $stmt_admins->bind_param("i", $admin_id);
                $stmt_admins->execute();

                if ($stmt_admins->affected_rows > 0) {
                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'Admin deleted successfully.';
                    unset($response['error']);

                    if ($admin_info) {
                        $admin_name = trim(($admin_info['firstname'] ?? '') . ' ' . ($admin_info['lastname'] ?? ''));
                        $admin_email = $admin_info['email'] ?? 'ID: ' . $admin_id;
                        log_user_action($conn, 'Delete Admin', 'Admin', $admin_email, 'Deleted admin account for ' . $admin_name . '.');
                    }
                } else {
                    throw new Exception("Admin not found or already deleted from main table.");
                }
                $stmt_admins->close();
            } catch (Exception $e) {
                $conn->rollback();
                if (strpos($e->getMessage(), 'foreign key constraint') !== false || $conn->errno == 1451) {
                    $response['error'] = "Cannot delete admin. They are linked to other records.";
                } else {
                    $response['error'] = "Deletion failed: " . $e->getMessage();
                }
            }
        } else {
            $response['error'] = "Failed to start transaction.";
        }
    }
} else {
    $response['error'] = 'Invalid request method.';
}

if (is_object($conn)) {
    $conn->close();
}

ob_end_clean();
header('Content-Type: application/json');
echo json_encode($response);
?>