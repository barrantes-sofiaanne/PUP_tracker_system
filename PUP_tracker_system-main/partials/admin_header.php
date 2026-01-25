<?php
// Ensure a session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure you have a DB connection. This path assumes 'partials' is at the same level as the 'PHP' folder.
// Adjust the path if your structure is different.
require_once __DIR__ . '/../PHP/dbcon.php';

// --- Admin Session & Info Check ---
$admin_session_id = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;

if ($admin_session_id === null) {
    header("Location: ../admin-login/admin_login.php"); 
    exit();
}

// --- AJAX Handler for Notifications ---
// This handles the request from the notification javascript to mark items as read.
if (isset($_POST['action']) && $_POST['action'] == 'mark_admin_notifs_read') {
    header('Content-Type: application/json');
    if (isset($conn)) {
        $update_sql = "UPDATE admin_notifications_tbl SET is_read = TRUE WHERE is_read = FALSE";
        if ($conn->query($update_sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update notifications.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database connection not available.']);
    }
    exit; // Stop execution after handling AJAX
}


// --- Fetch Notifications Data ---
$unread_admin_notifications = [];
$unread_admin_notification_count = 0;
if (isset($conn)) {
    $sql_admin_notifs = "SELECT * FROM admin_notifications_tbl WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5";
    if($stmt_notifs = $conn->prepare($sql_admin_notifs)) {
        $stmt_notifs->execute();
        $result_admin_notifs = $stmt_notifs->get_result();
        while($row = $result_admin_notifs->fetch_assoc()) {
            $unread_admin_notifications[] = $row;
        }
        $stmt_notifs->close();
    }

    $sql_admin_notif_count = "SELECT COUNT(*) as total_unread FROM admin_notifications_tbl WHERE is_read = 0";
    if($stmt_count = $conn->prepare($sql_admin_notif_count)) {
        $stmt_count->execute();
        $result_admin_notif_count = $stmt_count->get_result()->fetch_assoc();
        $unread_admin_notification_count = $result_admin_notif_count['total_unread'] ?? 0;
        $stmt_count->close();
    }
}
?>
<header class="main-header">
    <div class="header-content">
        <div class="logo"><img src="../IMAGE/Tracker-logo.png" alt="PUP Logo"></div>
        <nav class="main-nav">
            <a href="../admin-dashboard/admin_homepage.php" class="<?php echo ($currentPage == 'home' ? 'active-nav' : ''); ?>">Home</a>
            <a href="../updated-admin-violation/admin_violation_page.php" class="<?php echo ($currentPage == 'violations' ? 'active-nav' : ''); ?>">Violations</a>
            <a href="../updated-admin-sanction/admin_sanction.php" class="<?php echo ($currentPage == 'sanctions' ? 'active-nav' : ''); ?>">Student Sanction</a>
            <a href="../user-management/user_management.php" class="<?php echo ($currentPage == 'users' ? 'active-nav' : ''); ?>">User Management</a>
            <a href="../PHP/admin_announcements.php" class="<?php echo ($currentPage == 'announcements' ? 'active-nav' : ''); ?>">Announcements</a>
        </nav>
        <div class="user-icons">
            <div class="notification-icon-area">
                <a href="#" class="notification" id="notificationLinkToggle">
                    <i class="fas fa-bell header-icon"></i>
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
                                    <a href="<?php echo htmlspecialchars($notification['link'] ?? '#'); ?>">
                                        <div class="icon-wrapper"><i class="fas fa-user-check"></i></div>
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
                        <a href="#">View All Notifications</a>
                    </div>
                </div>
            </div>
            <a href="../PHP/admin_account.php" class="admin-profile">
                <i class="fas fa-user-circle header-icon"></i>
            </a>
        </div>
    </div>
</header>