<?php
session_start();
include '../PHP/dbcon.php';

$admin_session_id = $_SESSION['admin_id'] ?? null;
if ($admin_session_id === null) {
    header("Location: ../admin-login/admin_login.php"); 
    exit();
}
$admin_id = $admin_session_id;

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


define('UPLOAD_DIR', '../uploads/announcements/');

function handle_file_upload($file_input) {
    if (isset($file_input) && $file_input['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $file_input['tmp_name'];
        $file_name = $file_input['name'];
        $file_size = $file_input['size'];
        $file_type = $file_input['type'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        if (!in_array($file_ext, $allowed_exts)) { return ['error' => 'Invalid file type. Only JPG, PNG, GIF, and PDF are allowed.']; }
        if ($file_size > 5000000) { return ['error' => 'File size must be less than 5 MB.'];}
        $new_file_name = uniqid('', true) . '.' . $file_ext;
        $dest_path = UPLOAD_DIR . $new_file_name;
        if (move_uploaded_file($file_tmp_path, $dest_path)) {
            return ['filename' => $new_file_name];
        } else {
            return ['error' => 'Failed to move uploaded file. Check folder permissions.'];
        }
    }
    return ['filename' => null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];
    switch ($_POST['action']) {
        case 'add':
        case 'edit':
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $id = $_POST['announcement_id'] ?? null;
            $attachment_result = handle_file_upload($_FILES['attachment'] ?? null);
            if (isset($attachment_result['error'])) {
                $response['message'] = $attachment_result['error'];
                echo json_encode($response); exit;
            }
            $new_filename = $attachment_result['filename'];
            if ($_POST['action'] === 'add') {
                $stmt = $conn->prepare("INSERT INTO announcements_tbl (admin_id, title, content, attachment_path) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $admin_id, $title, $content, $new_filename);
            } else {
                $current_attachment = '';
                if (!empty($id)) {
                    $res = $conn->query("SELECT attachment_path FROM announcements_tbl WHERE announcement_id = $id");
                    if($res) $current_attachment = $res->fetch_assoc()['attachment_path'] ?? '';
                }
                $final_attachment = $current_attachment;
                if ($new_filename) {
                    if ($current_attachment && file_exists(UPLOAD_DIR . $current_attachment)) unlink(UPLOAD_DIR . $current_attachment);
                    $final_attachment = $new_filename;
                } elseif (isset($_POST['remove_attachment']) && $_POST['remove_attachment'] == '1') {
                    if ($current_attachment && file_exists(UPLOAD_DIR . $current_attachment)) unlink(UPLOAD_DIR . $current_attachment);
                    $final_attachment = null;
                }
                $stmt = $conn->prepare("UPDATE announcements_tbl SET title = ?, content = ?, attachment_path = ? WHERE announcement_id = ?");
                $stmt->bind_param("sssi", $title, $content, $final_attachment, $id);
            }
            if ($stmt && $stmt->execute()) { $response = ['success' => true, 'message' => 'Announcement saved successfully.']; } else { $response['message'] = 'Database operation failed.'; }
            if ($stmt) $stmt->close();
            break;
        case 'delete':
            $id = $_POST['announcement_id'];
            if (!empty($id)) {
                $res = $conn->query("SELECT attachment_path FROM announcements_tbl WHERE announcement_id = $id");
                 if($res) {
                    $attachment_path = $res->fetch_assoc()['attachment_path'] ?? '';
                    if ($attachment_path && file_exists(UPLOAD_DIR . $attachment_path)) unlink(UPLOAD_DIR . $attachment_path);
                 }
                $stmt = $conn->prepare("DELETE FROM announcements_tbl WHERE announcement_id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) { $response = ['success' => true, 'message' => 'Announcement deleted successfully.']; }
                $stmt->close();
            }
            break;
    }
    echo json_encode($response); exit;
}

if(isset($_GET['fetch']) && $_GET['fetch'] == 'true' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT title, content, attachment_path FROM announcements_tbl WHERE announcement_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    echo json_encode($result ?: null); exit;
}

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;
$total_query = $conn->query("SELECT COUNT(*) as total FROM announcements_tbl");
$total_records = $total_query->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$query = "SELECT a.announcement_id, a.title, a.created_at, a.attachment_path, COALESCE(CONCAT(ai.firstname, ' ', ai.lastname), 'N/A') AS author_name FROM announcements_tbl a LEFT JOIN admins adm ON a.admin_id = adm.id LEFT JOIN admin_info_tbl ai ON adm.id = ai.admin_id ORDER BY a.created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $offset, $records_per_page);
$stmt->execute();
$announcements_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/announcement_style.css?v=<?php echo time(); ?>">
</head>
<body>
    <header class="main-header">
       <div class="header-content">
         <div class="logo"><img src="../IMAGE/Tracker-logo.png" alt="PUP Logo"></div>
         <nav class="main-nav">
             <a href="../admin-dashboard/admin_homepage.php">Home</a>
             <a href="../updated-admin-violation/admin_violation_page.php">Violations</a>
             <a href="../updated-admin-sanction/admin_sanction.php">Student Sanction</a>
             <a href="../user-management/user_management.php">User Management</a>
             <a href="admin_announcements.php" class="active-nav">Announcements</a>
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

<main>
    <div class="admin-wrapper">
        <div class="dashboard-header">
            <div><h1 class="page-main-title">Announcements</h1></div>
            <button id="addAnnouncementBtn" class="add-new-btn">Add New Announcement</button>
        </div>

        <div class="content-card">
            <div class="list-header">
                <div class="list-col col-title">Title</div>
                <div class="list-col col-author">Author</div>
                <div class="list-col col-date">Date Posted</div>
                <div class="list-col col-actions">Actions</div>
            </div>
            <div class="list-body" id="announcementList">
                <?php if ($announcements_result && $announcements_result->num_rows > 0): while ($row = $announcements_result->fetch_assoc()): ?>
                    <div class="list-item" id="announcement-item-<?php echo $row['announcement_id']; ?>" data-id="<?php echo $row['announcement_id']; ?>">
                        <div class="list-col col-title">
                            <span><?php echo htmlspecialchars($row['title']); ?></span>
                            <?php if (!empty($row['attachment_path'])): ?>
                                <span class="attachment-indicator" title="Has Attachment"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/></svg></span>
                            <?php endif; ?>
                        </div>
                        <div class="list-col col-author"><?php echo htmlspecialchars($row['author_name']); ?></div>
                        <div class="list-col col-date"><?php echo date("F j, Y, g:i a", strtotime($row['created_at'])); ?></div>
                        <div class="list-col col-actions">
                            <button class="icon-btn view-btn" title="View Announcement"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg></button>
                            <button class="icon-btn edit-btn" title="Edit Announcement"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button>
                            <button class="icon-btn delete-btn" title="Delete Announcement"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                        </div>
                    </div>
                <?php endwhile; else: ?>
                    <div class="no-records-cell">No announcements have been posted yet.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <a href="?page=<?php echo max(1, $page - 1); ?>" class="pagination-link <?php if($page <= 1){ echo 'disabled'; } ?>">Prev</a>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="pagination-link <?php if($page == $i) {echo 'active'; } ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="?page=<?php echo min($total_pages, $page + 1); ?>" class="pagination-link <?php if($page >= $total_pages) { echo 'disabled'; } ?>">Next</a>
        </div>
        <?php endif; ?>
    </div>
</main>
<div id="announcementModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header"><h2 id="modalTitle" class="modal-title">Add New Announcement</h2><span class="close-btn">&times;</span></div>
        <div class="modal-body">
            <form id="announcementForm" enctype="multipart/form-data">
                <input type="hidden" id="announcementId" name="announcement_id"><input type="hidden" id="formAction" name="action" value="add"><input type="hidden" id="content-input" name="content">
                <div class="form-group"><label for="title">Title</label><input type="text" id="title" name="title" required></div>
                <div class="form-group"><label for="content">Content</label><div id="editor-container"></div></div>
                <div class="form-group">
                    <label for="attachment">Attach File (Image or PDF, max 5MB)</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="attachment" name="attachment" class="file-input">
                        <button type="button" id="clearFileBtn" class="clear-file-btn" title="Clear file" style="display: none;">&times;</button>
                    </div>
                </div>
                <div class="form-group" id="existing-attachment-info" style="display:none;">
                    <label>Current Attachment</label>
                    <div class="attachment-display">
                        <a href="#" id="existing-attachment-link" target="_blank"></a>
                        <label class="remove-attachment-label"><input type="checkbox" name="remove_attachment" value="1"> Remove this file</label>
                    </div>
                </div>
                <div class="form-actions"><button type="button" class="action-btn cancel-btn">Cancel</button><button type="submit" class="action-btn save-btn">Save Announcement</button></div>
            </form>
        </div>
    </div>
</div>
<div id="viewAnnouncementModal" class="modal-overlay"><div class="modal-content"><div class="modal-header"><h2 id="viewModalTitle" class="modal-title"></h2><span class="close-btn">&times;</span></div><div id="viewModalBody" class="modal-body content-view"></div></div></div>
<div id="confirmModal" class="modal-overlay"><div class="modal-content confirm-modal"><div class="modal-header"><h2 id="confirmModalTitle" class="modal-title">Confirm Action</h2><span class="close-btn">&times;</span></div><div class="modal-body"><p id="confirmModalMessage">Are you sure?</p></div><div class="modal-footer"><button class="action-btn cancel-btn" id="confirmCancelBtn">Cancel</button><button class="action-btn" id="confirmOkBtn">Confirm</button></div></div></div>
<div id="toast-container"></div>
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script src="../JS/admin_header_script.js?v=<?php echo time(); ?>"></script>
<script src="../JS/announcement_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>