<?php
include '../PHP/dbcon.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_session_id = null;
if (isset($_SESSION['admin_user_id'])) {
    $admin_session_id = $_SESSION['admin_user_id'];
} elseif (isset($_SESSION['admin_id'])) {
    $admin_session_id = $_SESSION['admin_id'];
}

if ($admin_session_id === null) {
    header("Location: ../admin-login/admin_login.php");
    exit();
}

if (isset($_POST['action']) && $_POST['action'] == 'mark_admin_notifs_read') {
    header('Content-Type: application/json');
    $update_sql = "UPDATE admin_notifications_tbl SET is_read = TRUE WHERE is_read = FALSE";
    if ($conn->query($update_sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update notifications.']);
    }
    exit;
}

$admin_id = $admin_session_id;
$admin_name = "Unknown Admin";

if (isset($conn)) {
    $stmt_admin_info = $conn->prepare("SELECT firstname, middlename, lastname FROM admin_info_tbl WHERE admin_id = ?");
    if ($stmt_admin_info) {
        $stmt_admin_info->bind_param("i", $admin_id);
        $stmt_admin_info->execute();
        $result_admin_info = $stmt_admin_info->get_result();
        if ($admin_info = $result_admin_info->fetch_assoc()) {
            $admin_name = htmlspecialchars($admin_info['firstname']) . ' ' .
                          (!empty($admin_info['middlename']) ? htmlspecialchars(substr($admin_info['middlename'], 0, 1)) . '. ' : '') .
                          htmlspecialchars($admin_info['lastname']);
        }
        $stmt_admin_info->close();
    }
}

$unread_admin_notifications = [];
$unread_admin_notification_count = 0;
if (isset($conn)) {
    $sql_admin_notifs = "SELECT * FROM admin_notifications_tbl WHERE is_read = ? ORDER BY created_at DESC LIMIT 5";
    if($stmt_notifs = $conn->prepare($sql_admin_notifs)) {
        $is_read_val = 0;
        $stmt_notifs->bind_param("i", $is_read_val);
        $stmt_notifs->execute();
        $result_admin_notifs = $stmt_notifs->get_result();
        while($row = $result_admin_notifs->fetch_assoc()) {
            $unread_admin_notifications[] = $row;
        }
        $stmt_notifs->close();
    }

    $sql_admin_notif_count = "SELECT COUNT(*) as total_unread FROM admin_notifications_tbl WHERE is_read = ?";
    if($stmt_count = $conn->prepare($sql_admin_notif_count)) {
        $is_read_val = 0;
        $stmt_count->bind_param("i", $is_read_val);
        $stmt_count->execute();
        $result_admin_notif_count = $stmt_count->get_result()->fetch_assoc();
        $unread_admin_notification_count = $result_admin_notif_count['total_unread'] ?? 0;
        $stmt_count->close();
    }
}

$all_violation_types = [];
if (isset($conn)) {
    $vt_query_modal = "SELECT violation_type_id, violation_type FROM violation_type_tbl ORDER BY violation_type ASC";
    if ($vt_result_modal = $conn->query($vt_query_modal)) {
        while ($row = $vt_result_modal->fetch_assoc()) {
            $all_violation_types[] = $row;
        }
    }
}


$DEFAULT_TAB = 'sanction-request';
$active_tab = $_GET['tab'] ?? $DEFAULT_TAB;
$active_view = $_GET['view'] ?? 'list';
$search_query = trim($_GET['search'] ?? '');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['approve_sanction'])) {
    $response = ['success' => false, 'message' => 'An unexpected error occurred.'];
    header('Content-Type: application/json');
    $request_id = $_POST['request_id'] ?? '';
    $student_number = $_POST['student_number'] ?? '';
    $violation_type_id = $_POST['violation_type_id'] ?? '';
    $assigned_sanction_id = $_POST['assigned_sanction_id'] ?? '';
    $deadline_date = $_POST['deadline_date'] ?? null;
    if (empty($request_id) || empty($student_number) || empty($violation_type_id) || empty($assigned_sanction_id) || empty($deadline_date)) {
        $response['message'] = 'Missing required fields for approval.';
        echo json_encode($response);
        exit;
    }
    $conn->begin_transaction();
    try {
        $stmt_find_viol = $conn->prepare("SELECT violation_id FROM violation_tbl WHERE student_number = ? AND violation_type = ? ORDER BY violation_date DESC LIMIT 1");
        if (!$stmt_find_viol) throw new mysqli_sql_exception('DB Error (find violation): ' . htmlspecialchars($conn->error));
        $stmt_find_viol->bind_param("si", $student_number, $violation_type_id);
        $stmt_find_viol->execute();
        $result_viol = $stmt_find_viol->get_result();
        if ($result_viol->num_rows === 0) throw new Exception("No matching violation instance found for this request.");
        $violation_row = $result_viol->fetch_assoc();
        $violation_id = $violation_row['violation_id'];
        $stmt_find_viol->close();
        $stmt_insert = $conn->prepare("INSERT INTO student_sanction_records_tbl (student_number, violation_id, assigned_sanction_id, deadline_date, assigned_by_admin_id, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        if (!$stmt_insert) throw new mysqli_sql_exception('DB Error (insert sanction): ' . htmlspecialchars($conn->error));
        $stmt_insert->bind_param("siisi", $student_number, $violation_id, $assigned_sanction_id, $deadline_date, $admin_id);
        if (!$stmt_insert->execute()) throw new mysqli_sql_exception('DB Execute Error (insert sanction): ' . htmlspecialchars($stmt_insert->error));
        $stmt_insert->close();
        $stmt_update_request = $conn->prepare("UPDATE sanction_requests_tbl SET is_active = 0, status = 'Approved', approved_by_admin_id = ?, approved_at = NOW() WHERE request_id = ?");
        if (!$stmt_update_request) throw new mysqli_sql_exception('DB Error (update request): ' . htmlspecialchars($conn->error));
        $stmt_update_request->bind_param("ii", $admin_id, $request_id);
        if (!$stmt_update_request->execute()) throw new mysqli_sql_exception('DB Execute Error (update request): ' . htmlspecialchars($stmt_update_request->error));
        $stmt_update_request->close();
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Sanction approved and assigned successfully!';
    } catch (Exception $exception) {
        $conn->rollback();
        $response['message'] = 'Transaction failed: ' . $exception->getMessage();
    }
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_sanction_status'])) {
    $response = ['success' => false, 'message' => 'An error occurred.'];
    header('Content-Type: application/json');
    $record_id = $_POST['record_id'] ?? '';
    $new_status = $_POST['new_status'] ?? '';
    $student_number = $_POST['student_number'] ?? '';
    if (empty($record_id) || empty($new_status) || !in_array($new_status, ['Completed', 'Pending'])) {
        $response['message'] = 'Invalid data provided.';
        echo json_encode($response);
        exit;
    }
    $conn->begin_transaction();
    try {
        $stmt_update = $conn->prepare("UPDATE student_sanction_records_tbl SET status = ?, date_completed = ? WHERE record_id = ?");
        $date_completed = ($new_status == 'Completed') ? date('Y-m-d H:i:s') : NULL;
        $stmt_update->bind_param("ssi", $new_status, $date_completed, $record_id);
        $stmt_update->execute();
        $stmt_history = $conn->prepare("INSERT INTO sanction_compliance_history (record_id, student_number, performed_by_admin_name, `action`, details) VALUES (?, ?, ?, ?, ?)");
        $action = "Marked as " . $new_status;
        $details = "Admin '$admin_name' updated sanction status to '$new_status' for student $student_number.";
        $stmt_history->bind_param("issss", $record_id, $student_number, $admin_name, $action, $details);
        $stmt_history->execute();
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Status updated successfully!';
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $response['message'] = 'Database transaction failed: ' . $exception->getMessage();
    }
    echo json_encode($response);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'get_sanctions_for_violation_type' && isset($_GET['violation_type_id'])) {
    $response = ['success' => false, 'message' => 'Sanctions not found.', 'sanctions' => []];
    $violationTypeId = trim($_GET['violation_type_id']);
    if (empty($violationTypeId) || !is_numeric($violationTypeId)) {
        $response['message'] = 'Invalid Violation Type ID.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    $sql_sanctions = "SELECT disciplinary_sanction_id, offense_level, disciplinary_sanction FROM disciplinary_sanctions WHERE violation_type_id = ? ORDER BY LENGTH(offense_level), offense_level ASC";
    $stmt_sanctions = $conn->prepare($sql_sanctions);
    if ($stmt_sanctions) {
        $stmt_sanctions->bind_param("i", $violationTypeId);
        $stmt_sanctions->execute();
        $result_sanctions = $stmt_sanctions->get_result();
        $sanctions_data = [];
        while ($sanc_row = $result_sanctions->fetch_assoc()) {
            $sanctions_data[] = $sanc_row;
        }
        $response['success'] = true;
        $response['sanctions'] = $sanctions_data;
        $response['message'] = empty($sanctions_data) ? 'No disciplinary sanctions found for this violation type.' : 'Sanctions fetched successfully.';
        $stmt_sanctions->close();
    } else {
        $response['message'] = 'Error preparing sanctions fetch statement: ' . $conn->error;
        error_log('Error preparing sanctions fetch statement: ' . $conn->error);
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'get_disciplinary_sanction_details' && isset($_GET['id'])) {
    $response = ['success' => false, 'message' => 'Details not found.', 'data' => null];
    $disciplinary_sanction_id = $_GET['id'];
    if (empty($disciplinary_sanction_id)) {
        $response['message'] = 'Sanction ID not provided.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    $sql_details = "SELECT ds.disciplinary_sanction_id, ds.violation_type_id, ds.offense_level, ds.disciplinary_sanction, vt.violation_type AS violation_type_name FROM disciplinary_sanctions ds JOIN violation_type_tbl vt ON ds.violation_type_id = vt.violation_type_id WHERE ds.disciplinary_sanction_id = ?";
    $stmt_details = $conn->prepare($sql_details);
    if ($stmt_details) {
        $stmt_details->bind_param("i", $disciplinary_sanction_id);
        $stmt_details->execute();
        $result_details = $stmt_details->get_result();
        if ($row_details = $result_details->fetch_assoc()) {
            $response['success'] = true;
            $response['message'] = 'Details fetched successfully.';
            $response['data'] = $row_details;
        }
        $stmt_details->close();
    } else {
        $response['message'] = 'Error preparing details fetch statement: ' . $conn->error;
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_disciplinary_sanction'])) {
    $response = ['success' => false, 'message' => 'An unexpected error occurred.'];
    $violation_type_id = $_POST['violation_type_id_sanction_modal'] ?? '';
    $offense_level = trim($_POST['offense_level_sanction_modal'] ?? '');
    $disciplinary_sanction = trim($_POST['disciplinary_sanction_text'] ?? '');
    $violation_type_name = trim($_POST['violation_type_name_hidden'] ?? '');
    if (empty($violation_type_id) || empty($offense_level) || empty($disciplinary_sanction)) {
        $response['message'] = 'All fields are required for adding a sanction.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    $conn->begin_transaction();
    try {
        $check_stmt = $conn->prepare("SELECT disciplinary_sanction_id FROM disciplinary_sanctions WHERE violation_type_id = ? AND offense_level = ?");
        if ($check_stmt) {
            $check_stmt->bind_param("is", $violation_type_id, $offense_level);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $response['message'] = "Error: An offense level '{$offense_level}' already exists for this violation type.";
                $conn->rollback();
                echo json_encode($response);
                exit;
            }
            $check_stmt->close();
        } else {
            throw new mysqli_sql_exception('Error preparing check statement for sanction: ' . $conn->error);
        }
        $insert_stmt = $conn->prepare("INSERT INTO disciplinary_sanctions (violation_type_id, offense_level, disciplinary_sanction) VALUES (?, ?, ?)");
        if ($insert_stmt) {
            $insert_stmt->bind_param("iss", $violation_type_id, $offense_level, $disciplinary_sanction);
            if ($insert_stmt->execute()) {
                $new_sanction_id = $conn->insert_id;
                $stmt_history = $conn->prepare("INSERT INTO disciplinary_sanction_history_tbl (performed_by_admin_name, action_type, violation_type_id, violation_type_name, offense_level, sanction_details_snapshot) VALUES (?, ?, ?, ?, ?, ?)");
                $action = "Added Sanction";
                $snapshot = json_encode(['disciplinary_sanction_id' => $new_sanction_id, 'offense_level' => $offense_level, 'disciplinary_sanction' => $disciplinary_sanction]);
                $stmt_history->bind_param("ssisss", $admin_name, $action, $violation_type_id, $violation_type_name, $offense_level, $snapshot);
                $stmt_history->execute();
                $response['success'] = true;
                $response['message'] = 'Disciplinary sanction added successfully!';
            } else {
                throw new mysqli_sql_exception('Error adding disciplinary sanction: ' . $insert_stmt->error);
            }
            $insert_stmt->close();
        } else {
            throw new mysqli_sql_exception('Error preparing insert statement for sanction: ' . $conn->error);
        }
        $conn->commit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $response['message'] = 'Database transaction failed: ' . $exception->getMessage();
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_disciplinary_sanction_submit'])) {
    $response = ['success' => false, 'message' => 'An unexpected error occurred.'];
    $disciplinary_sanction_id = $_POST['edit_disciplinary_sanction_id'] ?? '';
    $violation_type_id = $_POST['edit_violation_type_id_sanction_modal'] ?? '';
    $offense_level = trim($_POST['edit_offense_level_sanction_modal'] ?? '');
    $disciplinary_sanction_text = trim($_POST['edit_disciplinary_sanction_text'] ?? '');
    $violation_type_name = trim($_POST['edit_violation_type_name_hidden'] ?? '');
    if (empty($disciplinary_sanction_id) || empty($violation_type_id) || empty($offense_level) || empty($disciplinary_sanction_text)) {
        $response['message'] = 'All fields are required for editing a sanction.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    $conn->begin_transaction();
    try {
        $old_details_stmt = $conn->prepare("SELECT offense_level, disciplinary_sanction FROM disciplinary_sanctions WHERE disciplinary_sanction_id = ?");
        $old_details_stmt->bind_param("i", $disciplinary_sanction_id);
        $old_details_stmt->execute();
        $old_details_result = $old_details_stmt->get_result();
        $old_sanction_data = $old_details_result->fetch_assoc();
        $old_details_stmt->close();
        $check_stmt = $conn->prepare("SELECT disciplinary_sanction_id FROM disciplinary_sanctions WHERE violation_type_id = ? AND offense_level = ? AND disciplinary_sanction_id != ?");
        if ($check_stmt) {
            $check_stmt->bind_param("isi", $violation_type_id, $offense_level, $disciplinary_sanction_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $response['message'] = "Error: An offense level '{$offense_level}' already exists for this violation type.";
                $conn->rollback();
                echo json_encode($response);
                exit;
            }
            $check_stmt->close();
        } else {
            throw new mysqli_sql_exception('Error preparing check statement for sanction update: ' . $conn->error);
        }
        $update_stmt = $conn->prepare("UPDATE disciplinary_sanctions SET violation_type_id = ?, offense_level = ?, disciplinary_sanction = ? WHERE disciplinary_sanction_id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("issi", $violation_type_id, $offense_level, $disciplinary_sanction_text, $disciplinary_sanction_id);
            if ($update_stmt->execute()) {
                $stmt_history = $conn->prepare("INSERT INTO disciplinary_sanction_history_tbl (performed_by_admin_name, action_type, violation_type_id, violation_type_name, offense_level, sanction_details_snapshot) VALUES (?, ?, ?, ?, ?, ?)");
                $action = "Updated Sanction";
                $snapshot = json_encode(['disciplinary_sanction_id' => $disciplinary_sanction_id, 'old_offense_level' => $old_sanction_data['offense_level'], 'old_disciplinary_sanction' => $old_sanction_data['disciplinary_sanction'], 'new_offense_level' => $offense_level, 'new_disciplinary_sanction' => $disciplinary_sanction_text]);
                $stmt_history->bind_param("ssisss", $admin_name, $action, $violation_type_id, $violation_type_name, $offense_level, $snapshot);
                $stmt_history->execute();
                $response['success'] = true;
                $response['message'] = 'Disciplinary sanction updated successfully!';
            } else {
                throw new mysqli_sql_exception('Error updating disciplinary sanction: ' . $update_stmt->error);
            }
            $update_stmt->close();
        } else {
            throw new mysqli_sql_exception('Error preparing update statement for sanction: ' . $conn->error);
        }
        $conn->commit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $response['message'] = 'Database transaction failed: ' . $exception->getMessage();
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_disciplinary_sanction_id'])) {
    $response = ['success' => false, 'message' => 'An unexpected error occurred.'];
    $disciplinary_sanction_id = $_POST['delete_disciplinary_sanction_id'];
    $violation_type_id = $_POST['violation_type_id_hidden'] ?? null;
    $violation_type_name = $_POST['violation_type_name_hidden'] ?? null;
    $offense_level = $_POST['offense_level_hidden'] ?? null;
    $disciplinary_sanction_text = $_POST['sanction_details_hidden'] ?? null;
    if (empty($disciplinary_sanction_id)) {
        $response['message'] = 'Sanction ID not provided for deletion.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    $conn->begin_transaction();
    try {
        $delete_stmt = $conn->prepare("DELETE FROM disciplinary_sanctions WHERE disciplinary_sanction_id = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("i", $disciplinary_sanction_id);
            if ($delete_stmt->execute()) {
                if ($delete_stmt->affected_rows > 0) {
                    $stmt_history = $conn->prepare("INSERT INTO disciplinary_sanction_history_tbl (performed_by_admin_name, action_type, violation_type_id, violation_type_name, offense_level, sanction_details_snapshot) VALUES (?, ?, ?, ?, ?, ?)");
                    $action = "Deleted Sanction";
                    $snapshot = json_encode(['disciplinary_sanction_id' => $disciplinary_sanction_id, 'offense_level' => $offense_level, 'disciplinary_sanction' => $disciplinary_sanction_text]);
                    $stmt_history->bind_param("ssisss", $admin_name, $action, $violation_type_id, $violation_type_name, $offense_level, $snapshot);
                    $stmt_history->execute();
                    $response['success'] = true;
                    $response['message'] = 'Disciplinary sanction deleted successfully.';
                } else {
                    $response['message'] = 'Disciplinary sanction not found or already deleted.';
                }
            } else {
                throw new mysqli_sql_exception('Error deleting disciplinary sanction: ' . $delete_stmt->error);
            }
            $delete_stmt->close();
        } else {
            throw new mysqli_sql_exception('Error preparing delete statement for sanction: ' . $conn->error);
        }
        $conn->commit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $response['message'] = 'Database transaction failed: ' . $exception->getMessage();
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sanction Management</title>
    <link rel="stylesheet" href="./admin_sanction.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div id="toast-notification" class="toast"></div>
    <header class="main-header">
       <div class="header-content">
         <div class="logo"><img src="../IMAGE/Tracker-logo.png" alt="PUP Logo"></div>
         <nav class="main-nav">
             <a href="../admin-dashboard/admin_homepage.php">Home</a>
             <a href="../updated-admin-violation/admin_violation_page.php">Violations</a>
             <a href="../updated-admin-sanction/admin_sanction.php" class="active-nav">Student Sanction</a>
             <a href="../user-management/user_management.php">User Management</a>
             <a href="../PHP/admin_announcements.php">Announcements</a>
         </nav>
         <div class="user-icons">
            <div class="notification-icon-area">
                <a href="#" class="notification" id="notificationLinkToggle">
                    <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13.586V10c0-3.217-2.185-5.927-5.145-6.742C13.562 2.52 12.846 2 12 2s-1.562.52-1.855 1.258C7.185 4.073 5 6.783 5 10v3.586l-1.707 1.707A.996.996 0 0 0 3 16v2a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-2a.996.996 0 0 0-.293-.707L19 13.586zM19 17H5v-.586l1.707-1.707A.996.996 0 0 0 7 14v-4c0-2.757 2.243-5 5-5s5 2.243 5 5v4c0 .266.105.52.293.707L19 16.414V17zm-7 5a2.98 2.98 0 0 0 2.818-2H9.182A2.98 2.98 0 0 0 12 22z"/></svg>
                    <?php if ($unread_admin_notification_count > 0): ?>
                        <span class="notification-count"><?php echo $unread_admin_notification_count; ?></span>
                    <?php endif; ?>
                </a>
                <div class="notifications-dropdown" id="notificationsDropdownContent">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                    </div>
                    <ul class="notification-list">
                        <?php if (!empty($unread_admin_notifications)): ?>
                            <?php foreach ($unread_admin_notifications as $notification): ?>
                                <li class="notification-item">
                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>">
                                        <div class="icon-wrapper">
                                            <i class="fas fa-user-check"></i>
                                        </div>
                                        <div class="content">
                                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <small><?php echo date("M d, Y, h:i A", strtotime($notification['created_at'])); ?></small>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="no-notifications">
                                <i class="fas fa-check-circle"></i>
                                <p>No new notifications</p>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <div class="notification-footer">
                        <a href="../PHP/admin_notifications.php">View All Notifications</a>
                    </div>
                </div>
            </div>
            <a href="../PHP/admin_account.php" class="admin-profile">
                <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </a>
         </div>
       </div>
    </header>

    <main class="container">
        <?php if ($active_view === 'history'): ?>
            <div class="history-view">
                <div class="history-header">
                    <h1>Sanction Compliance History</h1>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?tab=sanction-compliance" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Performed By</th>
                                <th>Action</th>
                                <th>Target Student</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $history_sql = "SELECT * FROM sanction_compliance_history ORDER BY `timestamp` DESC";
                            $history_result = $conn->query($history_sql);
                            if ($history_result && $history_result->num_rows > 0) {
                                while ($row = $history_result->fetch_assoc()) {
                                    $action_class = '';
                                    if (strpos($row['action'], 'Completed') !== false) {
                                        $action_class = 'action-completed';
                                    } elseif (strpos($row['action'], 'Pending') !== false) {
                                        $action_class = 'action-pending';
                                    }
                                    
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars(date("M d, Y, h:i A", strtotime($row['timestamp']))) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['performed_by_admin_name']) . "</td>";
                                    echo "<td><span class='status-badge " . $action_class . "'>" . htmlspecialchars($row['action']) . "</span></td>";
                                    echo "<td>" . htmlspecialchars($row['student_number']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['details']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' class='no-records-cell'>No compliance history found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($active_view === 'sanction_config_history'): ?>
            <div class="history-view">
                <div class="history-header">
                    <h1>Configuration History</h1>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?tab=sanction-config" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Configuration</a>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Performed By</th>
                                <th>Action Type</th>
                                <th>Violation Type</th>
                                <th>Offense Level</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $history_sql = "SELECT * FROM disciplinary_sanction_history_tbl ORDER BY `timestamp` DESC";
                            $history_result = $conn->query($history_sql);
                            if ($history_result && $history_result->num_rows > 0) {
                                while ($row = $history_result->fetch_assoc()) {
                                    $action_class = '';
                                    if ($row['action_type'] == 'Added Sanction') {
                                        $action_class = 'action-added';
                                    } elseif ($row['action_type'] == 'Updated Sanction') {
                                        $action_class = 'action-updated';
                                    } elseif ($row['action_type'] == 'Deleted Sanction') {
                                        $action_class = 'action-deleted';
                                    }
                                    $details_output = 'N/A';
                                    if (!empty($row['sanction_details_snapshot'])) {
                                        $snapshot = json_decode($row['sanction_details_snapshot'], true);
                                        if ($snapshot) {
                                            if ($row['action_type'] == 'Added Sanction' || $row['action_type'] == 'Deleted Sanction') {
                                                $details_output = "Sanction: " . htmlspecialchars($snapshot['disciplinary_sanction']);
                                            } elseif ($row['action_type'] == 'Updated Sanction') {
                                                $details_output = "Old: \"" . htmlspecialchars($snapshot['old_disciplinary_sanction']) . "\", New: \"" . htmlspecialchars($snapshot['new_disciplinary_sanction']) . "\"";
                                            }
                                        }
                                    }
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars(date("M d, Y, h:i A", strtotime($row['timestamp']))) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['performed_by_admin_name']) . "</td>";
                                    echo "<td><span class='status-badge " . $action_class . "'>" . htmlspecialchars($row['action_type']) . "</span></td>";
                                    echo "<td>" . htmlspecialchars($row['violation_type_name'] ?? 'N/A') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['offense_level'] ?? 'N/A') . "</td>";
                                    echo "<td>" . htmlspecialchars($details_output) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' class='no-records-cell'>No configuration history found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="page-header">
                <h1>Student Sanction Management</h1>
            </div>
            <div class="tabs">
                <button class="tab <?php echo ($active_tab == 'sanction-request' ? 'active' : ''); ?>" data-tab="sanction-request"><i class="fas fa-inbox"></i> Sanction Requests</button>
                <button class="tab <?php echo ($active_tab == 'sanction-compliance' ? 'active' : ''); ?>" data-tab="sanction-compliance"><i class="fas fa-tasks"></i> Sanction Compliance</button>
                <button class="tab <?php echo ($active_tab == 'sanction-config' ? 'active' : ''); ?>" data-tab="sanction-config"><i class="fas fa-cogs"></i> Sanction Configuration</button>
            </div>

            <div id="sanction-request" class="tab-content" style="<?php echo ($active_tab == 'sanction-request' ? 'display: block;' : 'display: none;'); ?>">
                <div class="controls-header">
                    <form method="GET" class="search-form">
                        <input type="hidden" name="tab" value="sanction-request">
                        <div class="search-bar">
                                <input type="text" name="search" placeholder="Search by Student Name or Number..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student Number</th>
                                <th class="text-wrap-header">Student Name</th>
                                <th>Course, Yr & Sec</th>
                                <th class="text-wrap-header">Violation</th>
                                <th>Offense Level</th>
                                <th>Date Requested</th>
                                <th class="actions-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                                <?php
                                $sql_base = "SELECT sr.request_id, sr.request_date, u.student_number, u.first_name, u.middle_name, u.last_name, c.course_name, y.year, s.section_name, vt.violation_type_id, vt.violation_type, (SELECT COUNT(*) FROM violation_tbl v_count WHERE v_count.student_number = u.student_number AND v_count.violation_type = vt.violation_type_id) as offense_count, ds.disciplinary_sanction_id, ds.disciplinary_sanction, ds.offense_level FROM sanction_requests_tbl sr JOIN users_tbl u ON sr.student_number = u.student_number JOIN violation_type_tbl vt ON sr.violation_type_id = vt.violation_type_id LEFT JOIN course_tbl c ON u.course_id = c.course_id LEFT JOIN year_tbl y ON u.year_id = y.year_id LEFT JOIN section_tbl s ON u.section_id = s.section_id LEFT JOIN ( SELECT student_number, violation_type, COUNT(*) as offense_count FROM violation_tbl GROUP BY student_number, violation_type ) as offense_counts ON offense_counts.student_number = u.student_number AND offense_counts.violation_type = vt.violation_type_id LEFT JOIN disciplinary_sanctions ds ON ds.violation_type_id = vt.violation_type_id AND ds.offense_level LIKE CONCAT(offense_counts.offense_count, '%Offense')";
                                
                                $conditions = ["sr.is_active = 1"];
                                $params = [];
                                $types = "";

                                if (!empty($search_query)) {
                                    $conditions[] = "(u.student_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
                                    $search_wildcard = "%" . $search_query . "%";
                                    $params[] = $search_wildcard;
                                    $params[] = $search_wildcard;
                                    $types .= "ss";
                                }

                                $sql = $sql_base . " WHERE " . implode(" AND ", $conditions) . " ORDER BY sr.request_date DESC";
                                
                                $stmt = $conn->prepare($sql);
                                if ($stmt) {
                                    if(!empty($types)) {
                                        $stmt->bind_param($types, ...$params);
                                    }
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $student_full_name = htmlspecialchars($row['first_name'] . ' ' . ($row['middle_name'] ? mb_substr($row['middle_name'], 0, 1) . '. ' : '') . $row['last_name']);
                                            $course_year_section = htmlspecialchars(($row['course_name'] ?? 'N/A') . ' ' . ($row['year'] ?? '') . '-' . ($row['section_name'] ?? ''));
                                            $offense_count = $row['offense_count'];
                                            $offense_level_display = $offense_count . (in_array($offense_count % 10, [1]) && $offense_count % 100 != 11 ? 'st' : (in_array($offense_count % 10, [2]) && $offense_count % 100 != 12 ? 'nd' : (in_array($offense_count % 10, [3]) && $offense_count % 100 != 13 ? 'rd' : 'th'))) . ' Offense';
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row['student_number']) . "</td>";
                                            echo "<td class='text-wrap-content'>" . $student_full_name . "</td>";
                                            echo "<td>" . $course_year_section . "</td>";
                                            echo "<td class='text-wrap-content'>" . htmlspecialchars($row['violation_type']) . "</td>";
                                            echo "<td>" . htmlspecialchars($offense_level_display) . "</td>";
                                            echo "<td>" . htmlspecialchars(date("F j, Y, h:i a", strtotime($row['request_date']))) . "</td>";
                                            echo "<td><button class='btn btn-primary view-manage-btn' data-request-id='" . htmlspecialchars($row['request_id']) . "' data-student-number='" . htmlspecialchars($row['student_number']) . "' data-student-name='" . $student_full_name . "' data-course-year-section='" . $course_year_section . "' data-violation-type-id='" . htmlspecialchars($row['violation_type_id']) . "' data-violation-type='" . htmlspecialchars($row['violation_type']) . "' data-disciplinary-sanction='" . htmlspecialchars($row['disciplinary_sanction'] ?? 'No sanction defined.') . "' data-offense-level='" . htmlspecialchars($offense_level_display) . "' data-date-requested='" . htmlspecialchars(date("F j, Y", strtotime($row['request_date']))) . "' data-assigned-sanction-id='" . htmlspecialchars($row['disciplinary_sanction_id'] ?? '') . "'><i class='fas fa-eye'></i> Manage</button></td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='no-records-cell'>No matching sanction requests found.</td></tr>";
                                    }
                                    $stmt->close();
                                }
                                ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="sanction-compliance" class="tab-content" style="<?php echo ($active_tab == 'sanction-compliance' ? 'display: block;' : 'display: none;'); ?>">
                <?php $filterComplianceStatus = $_GET['status_filter'] ?? 'All'; ?>
                <div class="controls-header">
                    <div class="compliance-filter-tabs">
                        <a href="?tab=sanction-compliance&status_filter=All&search=<?php echo urlencode($search_query); ?>" class="filter-tab-btn <?php echo (($filterComplianceStatus == 'All') ? 'active' : ''); ?>">All</a>
                        <a href="?tab=sanction-compliance&status_filter=Pending&search=<?php echo urlencode($search_query); ?>" class="filter-tab-btn <?php echo (($filterComplianceStatus == 'Pending') ? 'active' : ''); ?>">Pending</a>
                        <a href="?tab=sanction-compliance&status_filter=Completed&search=<?php echo urlencode($search_query); ?>" class="filter-tab-btn <?php echo (($filterComplianceStatus == 'Completed') ? 'active' : ''); ?>">Completed</a>
                    </div>
                    <div class="search-and-history-container">
                        <form method="GET" class="search-form">
                            <input type="hidden" name="tab" value="sanction-compliance">
                            <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($filterComplianceStatus); ?>">
                            <div class="search-bar">
                                <input type="text" name="search" placeholder="Search by Student..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button type="submit"><i class="fas fa-search"></i></button>
                            </div>
                        </form>
                        <a href="?tab=sanction-compliance&view=history" class="btn btn-outline-secondary"><i class="fas fa-history"></i> View History</a>
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student Number</th>
                                <th class="text-wrap-header">Student Name</th>
                                <th>Violation</th>
                                <th class="text-wrap-header">Disciplinary Sanction</th>
                                <th>Date of Compliance</th>
                                <th>Status</th>
                                <th class="actions-column <?php echo ($filterComplianceStatus == 'All' ? 'hidden' : ''); ?>">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                                <?php
                                
                                $params = [];
                                $types = "";
                                $where_conditions = "rn = 1";

                                if ($filterComplianceStatus != 'All') {
                                    $where_conditions .= " AND status = ?";
                                    $params[] = $filterComplianceStatus;
                                    $types .= "s";
                                }
                                
                                if (!empty($search_query)) {
                                    $where_conditions .= " AND (student_number LIKE ? OR full_name LIKE ?)";
                                    $search_wildcard = "%" . $search_query . "%";
                                    $params[] = $search_wildcard;
                                    $params[] = $search_wildcard;
                                    $types .= "ss";
                                }
                                
                                $sql_compliance = "
                                    WITH RankedSanctions AS (
                                        SELECT 
                                            ssr.record_id, ssr.status, ssr.deadline_date, ssr.date_completed, ssr.date_assigned,
                                            u.student_number, u.first_name, u.middle_name, u.last_name,
                                            CONCAT(u.first_name, ' ', u.last_name) as full_name,
                                            vt.violation_type,
                                            ds.disciplinary_sanction,
                                            ROW_NUMBER() OVER(PARTITION BY ssr.student_number, v.violation_type ORDER BY ssr.date_assigned DESC) as rn
                                        FROM student_sanction_records_tbl ssr
                                        JOIN users_tbl u ON ssr.student_number = u.student_number
                                        JOIN violation_tbl v ON ssr.violation_id = v.violation_id
                                        JOIN violation_type_tbl vt ON v.violation_type = vt.violation_type_id
                                        LEFT JOIN disciplinary_sanctions ds ON ssr.assigned_sanction_id = ds.disciplinary_sanction_id
                                    )
                                    SELECT * FROM RankedSanctions WHERE $where_conditions ORDER BY date_assigned DESC
                                ";
                                
                                $stmt_compliance = $conn->prepare($sql_compliance);

                                if ($stmt_compliance) {
                                    if (!empty($types)) {
                                        $stmt_compliance->bind_param($types, ...$params);
                                    }
                                    $stmt_compliance->execute();
                                    $result_compliance = $stmt_compliance->get_result();
                                    if ($result_compliance && $result_compliance->num_rows > 0) {
                                        while ($row = $result_compliance->fetch_assoc()) {
                                            $status_class = 'status-default';
                                            if ($row['status'] == 'Pending') $status_class = 'status-pending';
                                            if ($row['status'] == 'Completed') $status_class = 'status-completed';
                                            $student_full_name = htmlspecialchars($row['first_name'] . ' ' . ($row['middle_name'] ? mb_substr($row['middle_name'], 0, 1) . '. ' : '') . $row['last_name']);
                                            echo "<tr class='compliance-row-data' data-record-id='" . htmlspecialchars($row['record_id']) . "' data-student-number='" . htmlspecialchars($row['student_number']) . "'>";
                                            echo "<td>" . htmlspecialchars($row['student_number']) . "</td>";
                                            echo "<td class='text-wrap-content'>" . $student_full_name . "</td>";
                                            echo "<td class='text-wrap-content'>" . htmlspecialchars($row['violation_type']) . "</td>";
                                            echo "<td class='text-wrap-content'>" . htmlspecialchars($row['disciplinary_sanction'] ?? 'N/A') . "</td>";
                                            echo "<td>" . htmlspecialchars(date("F j, Y", strtotime($row['deadline_date']))) . "</td>";
                                            echo "<td><span class='status-badge " . $status_class . "'>" . htmlspecialchars($row['status']) . "</span></td>";
                                            echo "<td class='actions-column " . ($filterComplianceStatus == 'All' ? 'hidden' : '') . "'>";
                                            if ($row['status'] == 'Pending') {
                                                echo "<button class='btn btn-success update-status-btn' data-record-id='" . htmlspecialchars($row['record_id']) . "' data-student-number='" . htmlspecialchars($row['student_number']) . "' data-new-status='Completed'><i class='fas fa-check-circle'></i> Mark Completed</button>";
                                            } else {
                                                echo "<button class='btn btn-warning update-status-btn' data-record-id='" . htmlspecialchars($row['record_id']) . "' data-student-number='" . htmlspecialchars($row['student_number']) . "' data-new-status='Pending'><i class='fas fa-undo'></i> Mark as Pending</button>";
                                            }
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        $colspan = ($filterComplianceStatus == 'All' ? '6' : '7');
                                        echo "<tr><td colspan='" . $colspan . "' class='no-records-cell'>No matching sanctions found.</td></tr>";
                                    }
                                    $stmt_compliance->close();
                                } else {
                                    echo "<tr><td colspan='7' class='no-records-cell'>Error preparing statement.</td></tr>";
                                }
                                ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="sanction-config" class="tab-content" style="<?php echo ($active_tab == 'sanction-config' ? 'display: block;' : 'display: none;'); ?>">
                <div class="controls-header">
                    <div class="search-bar">
                        <input type="text" id="violation-type-search" placeholder="Search Violation Type...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="config-buttons">
                        <button class="btn btn-success add-sanction-btn">
                            <i class="fas fa-plus"></i> Add Sanction
                        </button>
                        <a href="?tab=sanction-config&view=sanction_config_history" class="btn btn-outline-secondary"><i class="fas fa-history"></i> View History</a>
                    </div>
                </div>
                <div class="accordion-container">
                    <?php
                    $violationTypesQuery = "SELECT violation_type_id, violation_type, resolution_number FROM violation_type_tbl ORDER BY violation_type ASC";
                    $vtResult = $conn->query($violationTypesQuery);
                    if ($vtResult && $vtResult->num_rows > 0) {
                        while ($vtRow = $vtResult->fetch_assoc()) {
                            $violationTypeId = htmlspecialchars($vtRow['violation_type_id']);
                            $violationTypeName = htmlspecialchars($vtRow['violation_type']);
                    ?>
                    <div class="accordion-item violation-type-item" data-violation-type-name="<?php echo $violationTypeName; ?>">
                        <button class="accordion-header" data-violation-type-id="<?php echo $violationTypeId; ?>" data-violation-type-name="<?php echo $violationTypeName; ?>">
                            <span><?php echo $violationTypeName; ?></span>
                            <i class="fas fa-chevron-down accordion-icon"></i>
                        </button>
                        <div class="accordion-content">
                            <div class="accordion-content-header">
                                <h4>Disciplinary Sanctions</h4>
                                <button class="btn btn-success btn-sm add-sanction-btn" data-violation-type-id="<?php echo $violationTypeId; ?>" data-violation-type-name="<?php echo $violationTypeName; ?>">
                                    <i class="fas fa-plus"></i> Add Sanction
                                </button>
                            </div>
                            <div class="table-container-inner">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Offense Level</th>
                                            <th>Disciplinary Sanction</th>
                                            <th class="actions-column">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="sanction-table-body" id="sanction-table-body-<?php echo $violationTypeId; ?>">
                                        <tr><td colspan='3' class='no-records-cell'><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php
                        }
                    } else {
                        echo "<p class='no-records-cell'>No violation types found.</p>";
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?> 
    </main>

    <div id="viewSanctionDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Sanction Request Management</h2>
                <button class="close-modal-button" data-modal="viewSanctionDetailsModal">&times;</button>
            </div>
            <form id="approveSanctionForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="approve_sanction" value="1">
                    <input type="hidden" id="approveRequestId" name="request_id">
                    <input type="hidden" id="approveStudentNumber" name="student_number">
                    <input type="hidden" id="approveViolationTypeId" name="violation_type_id">
                    <input type="hidden" id="approveAssignedSanctionId" name="assigned_sanction_id">
                    
                    <div class="detail-group">
                        <p><strong>Student Name:</strong> <span id="detailStudentName"></span></p>
                        <p><strong>Student Number:</strong> <span id="detailStudentNumber"></span></p>
                        <p><strong>Course | Yr & Sec:</strong> <span id="detailCourseYearSection"></span></p>
                    </div>
                    <div class="detail-group">
                        <p><strong>Violation Type:</strong> <span id="detailViolationType"></span></p>
                        <p><strong>Disciplinary Sanction:</strong> <span id="detailDisciplinarySanction"></span></p>
                        <p><strong>Offense Level:</strong> <span id="detailOffenseLevel"></span></p>
                        <p><strong>Date Requested:</strong> <span id="detailDateRequested"></span></p>
                    </div>
                    <div class="form-group">
                        <label for="deadlineDate">Set Starting Date for Compliance:</label>
                        <input type="date" id="deadlineDate" name="deadline_date" class="form-control" required>
                    </div>
                    <div id="approveSanctionModalMessage" class="modal-message" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary close-modal-button" data-modal="viewSanctionDetailsModal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="deleteConfirmationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="close-modal-button" data-modal="deleteConfirmationModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this sanction? This action cannot be undone.</p>
                <div id="deleteModalErrorMessage" class="modal-message error-message" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal-button" data-modal="deleteConfirmationModal">Cancel</button>
                <form id="deleteSanctionForm" method="POST" style="display:inline;">
                    <input type="hidden" id="delete_disciplinary_sanction_id" name="delete_disciplinary_sanction_id">
                    <input type="hidden" id="delete_violation_type_id_hidden" name="violation_type_id_hidden">
                    <input type="hidden" id="delete_violation_type_name_hidden" name="violation_type_name_hidden">
                    <input type="hidden" id="delete_offense_level_hidden" name="offense_level_hidden">
                    <input type="hidden" id="delete_sanction_details_hidden" name="sanction_details_hidden">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Confirm Delete</button>
                </form>
            </div>
        </div>
    </div>
    
    <div id="addSanctionModal" class="modal">
        <div class="modal-content">
            <form id="addSanctionForm" method="POST">
                <div class="modal-header">
                    <h2>Add Disciplinary Sanction</h2>
                    <button type="button" class="close-modal-button" data-modal="addSanctionModal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_disciplinary_sanction" value="1">
                    <input type="hidden" id="add_violation_type_name_hidden" name="violation_type_name_hidden">
                    
                    <div class="form-group">
                        <label for="violation_type_id_sanction_modal">Violation Type</label>
                        <select id="violation_type_id_sanction_modal" name="violation_type_id_sanction_modal" class="form-control" required>
                            <option value="" disabled selected>-- Select a Violation Type --</option>
                            <?php foreach($all_violation_types as $vt): ?>
                                <option value="<?php echo htmlspecialchars($vt['violation_type_id']); ?>" data-name="<?php echo htmlspecialchars($vt['violation_type']); ?>"><?php echo htmlspecialchars($vt['violation_type']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="offense_level_sanction_modal">Offense Level (e.g., 1st Offense)</label>
                        <input type="text" id="offense_level_sanction_modal" name="offense_level_sanction_modal" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="disciplinary_sanction_text">Disciplinary Sanction</label>
                        <textarea id="disciplinary_sanction_text" name="disciplinary_sanction_text" class="form-control" rows="3" required></textarea>
                    </div>
                    <div id="addSanctionModalMessage" class="modal-message" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary close-modal-button" data-modal="addSanctionModal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Add Sanction</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editSanctionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Disciplinary Sanction</h2>
                <button class="close-modal-button" data-modal="editSanctionModal">&times;</button>
            </div>
            <form id="editSanctionForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_disciplinary_sanction_submit" value="1">
                    <input type="hidden" id="edit_disciplinary_sanction_id" name="edit_disciplinary_sanction_id">
                    <input type="hidden" id="edit_violation_type_id_sanction_modal" name="edit_violation_type_id_sanction_modal">
                    <input type="hidden" id="edit_violation_type_name_hidden" name="edit_violation_type_name_hidden">
                    
                    <p>Editing sanction for: <strong id="editSanctionViolationName"></strong></p>

                    <div class="form-group">
                        <label for="edit_offense_level_sanction_modal">Offense Level</label>
                        <input type="text" id="edit_offense_level_sanction_modal" name="edit_offense_level_sanction_modal" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_disciplinary_sanction_text">Disciplinary Sanction</label>
                        <textarea id="edit_disciplinary_sanction_text" name="edit_disciplinary_sanction_text" class="form-control" rows="3" required></textarea>
                    </div>
                    <div id="editSanctionModalMessage" class="modal-message" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary close-modal-button" data-modal="editSanctionModal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../JS/admin_header_script.js?v=<?php echo time(); ?>"></script>
    <script src="./admin_sanction.js?v=<?php echo time(); ?>"></script>
</body>
</html>
<?php if(isset($conn)) $conn->close(); ?>