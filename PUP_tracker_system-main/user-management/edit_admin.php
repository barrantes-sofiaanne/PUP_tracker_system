<?php
ob_start();
session_start();
require '../PHP/dbcon.php';
require_once './history_logger.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

function get_lookup_name($conn, $table, $name_col, $id_col, $id) {
    if (empty($id) || empty($table)) return 'N/A';
    $stmt = $conn->prepare("SELECT $name_col FROM $table WHERE $id_col = ?");
    if (!$stmt) return 'Error';
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result[$name_col] ?? 'Unknown';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_id = (int)($_POST['admin_id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $status_id = (int)($_POST['status_id'] ?? 0);

    if ($admin_id <= 0 || empty($first_name) || empty($last_name) || empty($position) || empty($email) || $status_id <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['error'] = 'Required fields are missing or invalid.';
    } else {
        $checkEmailStmt = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
        $checkEmailStmt->bind_param("si", $email, $admin_id);
        $checkEmailStmt->execute();
        if ($checkEmailStmt->get_result()->num_rows > 0) {
            $response['error'] = 'This email address is already in use by another admin.';
        } else {
            if ($conn->begin_transaction()) {
                try {
                    $old_data_stmt = $conn->prepare("SELECT a.email, i.firstname, i.middlename, i.lastname, i.Position, i.status_id FROM admins a JOIN admin_info_tbl i ON a.id = i.admin_id WHERE a.id = ?");
                    $old_data_stmt->bind_param("i", $admin_id);
                    $old_data_stmt->execute();
                    $old_data = $old_data_stmt->get_result()->fetch_assoc();
                    $old_data_stmt->close();
                    
                    $stmt_info = $conn->prepare("UPDATE admin_info_tbl SET firstname = ?, middlename = ?, lastname = ?, Position = ?, status_id = ?, updated_at = NOW() WHERE admin_id = ?");
                    $stmt_info->bind_param("ssssii", $first_name, $middle_name, $last_name, $position, $status_id, $admin_id);
                    $stmt_info->execute();
                    $stmt_info->close();

                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt_admins = $conn->prepare("UPDATE admins SET email = ?, password = ? WHERE id = ?");
                        $stmt_admins->bind_param("ssi", $email, $hashed_password, $admin_id);
                    } else {
                        $stmt_admins = $conn->prepare("UPDATE admins SET email = ? WHERE id = ?");
                        $stmt_admins->bind_param("si", $email, $admin_id);
                    }
                    $stmt_admins->execute();
                    $stmt_admins->close();

                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'Admin updated successfully.';
                    
                    $details_array = [];
                    if ($old_data) {
                        if (($old_data['firstname'] ?? '') != $first_name) { $details_array[] = "<b>First Name:</b> '{$old_data['firstname']}' → '{$first_name}'"; }
                        if (($old_data['lastname'] ?? '') != $last_name) { $details_array[] = "<b>Last Name:</b> '{$old_data['lastname']}' → '{$last_name}'"; }
                        if (($old_data['email'] ?? '') != $email) { $details_array[] = "<b>Email:</b> '{$old_data['email']}' → '{$email}'"; }
                        if (($old_data['Position'] ?? '') != $position) { $details_array[] = "<b>Position:</b> '{$old_data['Position']}' → '{$position}'"; }
                        if (!empty($password)) { $details_array[] = "<b>Password:</b> Updated"; }
                        if (($old_data['status_id'] ?? 0) != $status_id) {
                            $old_name = get_lookup_name($conn, 'status_tbl', 'status_name', 'status_id', $old_data['status_id']);
                            $new_name = get_lookup_name($conn, 'status_tbl', 'status_name', 'status_id', $status_id);
                            $details_array[] = "<b>Status:</b> '{$old_name}' → '{$new_name}'";
                        }
                    }

                    $log_details = empty($details_array) ? 'Profile updated with no logged field changes.' : implode("<br>", $details_array);
                    log_user_action($conn, 'Edit Admin', 'Admin', $email, $log_details);
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['error'] = "Transaction failed: " . $e->getMessage();
                }
            } else {
                $response['error'] = "Failed to start database transaction.";
            }
        }
        $checkEmailStmt->close();
    }
} else {
    $response['error'] = 'Invalid request method.';
}

if (is_object($conn)) $conn->close();
ob_end_clean();
header('Content-Type: application/json');
echo json_encode($response);
?>