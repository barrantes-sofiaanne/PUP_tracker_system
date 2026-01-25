<?php
include '../PHP/dbcon.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_session_id = $_SESSION['admin_id'] ?? null;

if ($admin_session_id === null) {
    header("Location: ../admin-login/admin_login.php"); 
    exit();
}

$unread_admin_notification_count = 0;
$all_admin_notifications = [];
if (isset($conn)) {
    // Fetch all notifications for the list
    $sql_all_notifs = "SELECT * FROM admin_notifications_tbl ORDER BY created_at DESC";
    $result_all_notifs = $conn->query($sql_all_notifs);
    while($row = $result_all_notifs->fetch_assoc()) {
        $all_admin_notifications[] = $row;
    }

    // Fetch unread count for the header
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications</title>
    <link rel="stylesheet" href="../CSS/admin_notification.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
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
            </div>
            <a href="../PHP/admin_account.php" class="admin-profile">
                <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </a>
         </div>
       </div>
    </header>

    <main class="container">
        <div class="page-header">
            <h1>All Notifications</h1>
        </div>
        <div class="notification-page-list">
            <?php if (!empty($all_admin_notifications)): ?>
                <?php foreach ($all_admin_notifications as $notification): ?>
                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="notification-page-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                        <div class="notification-content">
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small><?php echo date("F j, Y, h:i a", strtotime($notification['created_at'])); ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-records-cell">
                    <p>No notifications found.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="../JS/admin_header_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
<?php if (isset($conn)) { $conn->close(); } ?>