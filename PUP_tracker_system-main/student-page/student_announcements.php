<?php
require_once '../PHP/dbcon.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (isset($_GET['action']) && $_GET['action'] == 'get_announcement' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $response = ['success' => false];
    if (isset($conn)) {
        $stmt = $conn->prepare("
            SELECT a.title, a.content, a.attachment_path, a.created_at, CONCAT(ai.firstname, ' ', ai.lastname) AS author_name
            FROM announcements_tbl a
            LEFT JOIN admin_info_tbl ai ON a.admin_id = ai.admin_id
            WHERE a.announcement_id = ?
        ");
        $stmt->bind_param("i", $_GET['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($announcement = $result->fetch_assoc()) {
            $announcement['created_at_formatted'] = date("F j, Y, g:i a", strtotime($announcement['created_at']));
            $response = ['success' => true, 'data' => $announcement];
        }
        $stmt->close();
    }
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION["current_user_id"]) || !isset($_SESSION["user_student_number"])) {
    header("Location: student_login.php");
    exit();
}

$student_stud_number_from_session = $_SESSION["user_student_number"];

$unread_notifications_header = [];
$unread_notification_count_header = 0;

if (isset($conn)) {
    $sql_notifications_list_header = "SELECT notification_id, message, created_at, link
                                      FROM notifications_tbl
                                      WHERE student_number = ? AND is_read = FALSE
                                      ORDER BY created_at DESC LIMIT 5";
    if ($stmt_notifications_list_header = $conn->prepare($sql_notifications_list_header)) {
        $stmt_notifications_list_header->bind_param("s", $student_stud_number_from_session);
        $stmt_notifications_list_header->execute();
        $result_notifications_list_header = $stmt_notifications_list_header->get_result();
        while ($row_notif_h = $result_notifications_list_header->fetch_assoc()) {
            $unread_notifications_header[] = $row_notif_h;
        }
        $stmt_notifications_list_header->close();
    }

    $sql_notifications_count_header = "SELECT COUNT(*) as total_unread
                                           FROM notifications_tbl
                                           WHERE student_number = ? AND is_read = FALSE";
    if ($stmt_notifications_count_header = $conn->prepare($sql_notifications_count_header)) {
        $stmt_notifications_count_header->bind_param("s", $student_stud_number_from_session);
        $stmt_notifications_count_header->execute();
        $result_count_h = $stmt_notifications_count_header->get_result()->fetch_assoc();
        $unread_notification_count_header = $result_count_h['total_unread'] ?? 0;
        $stmt_notifications_count_header->close();
    }
}

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 5;
$offset = ($page - 1) * $records_per_page;

$total_records_result = $conn->query("SELECT COUNT(*) FROM announcements_tbl");
$total_records = $total_records_result->fetch_row()[0];
$total_pages = ceil($total_records / $records_per_page);

$announcements_stmt = $conn->prepare("
    SELECT a.*, CONCAT(ai.firstname, ' ', ai.lastname) AS author_name
    FROM announcements_tbl a
    LEFT JOIN admin_info_tbl ai ON a.admin_id = ai.admin_id
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?
");
$announcements_stmt->bind_param("ii", $records_per_page, $offset);
$announcements_stmt->execute();
$announcements_result = $announcements_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./student_style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <div class="logo">
                <img src="../IMAGE/Tracker-logo.png" alt="PUP Logo">
            </div>
            <nav class="main-nav" id="primary-navigation">
                <div class="nav-links">
                    <a href="./student_dashboard.php">Home</a>
                    <a href="./student_record.php">Record</a>
                    <a href="./student_announcements.php" class="active-nav">Announcements</a>
                    <div class="mobile-only">
                        <a href="./student_account.php" class="profile-icon admin">
                            <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            <span>My Account</span>
                        </a>
                        <a href="../PHP/logout.php" class="logout-link">
                            <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16 17v-3H9v-4h7V7l5 5-5 5zM14 2a2 2 0 0 1 2 2v2h-2V4H5v16h9v-2h2v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9z"></path></svg>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </nav>
            <div class="header-actions">
                <div class="notification-icon-area">
                    <a href="#" class="notification" id="notificationLinkToggle">
                        <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13.586V10c0-3.217-2.185-5.927-5.145-6.742C13.562 2.52 12.846 2 12 2s-1.562.52-1.855 1.258C7.185 4.073 5 6.783 5 10v3.586l-1.707 1.707A.996.996 0 0 0 3 16v2a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-2a.996.996 0 0 0-.293-.707L19 13.586zM19 17H5v-.586l1.707-1.707A.996.996 0 0 0 7 14v-4c0-2.757 2.243-5 5-5s5 2.243 5 5v4c0 .266.105.52.293.707L19 16.414V17zm-7 5a2.98 2.98 0 0 0 2.818-2H9.182A2.98 2.98 0 0 0 12 22z"/></svg>
                        <?php if ($unread_notification_count_header > 0): ?>
                            <span class="notification-count"><?php echo $unread_notification_count_header; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="notifications-dropdown" id="notificationsDropdownContent">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <button id="mark-all-read-btn">Mark all as read</button>
                        </div>
                        <ul class="notification-list">
                            <?php if (!empty($unread_notifications_header)): ?>
                                <?php foreach ($unread_notifications_header as $notification_h): ?>
                                    <li class="notification-item">
                                        <div class="notification-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 2H4c-1.103 0-2 .897-2 2v18l4-4h14c1.103 0 2-.897 2-2V4c0-1.103-.897-2-2-2zm-3 9h-4v4h-2v-4H7V9h4V5h2v4h4v2z"/></svg>
                                        </div>
                                        <div class="notification-details">
                                            <p class="notification-message"><?php echo htmlspecialchars($notification_h['message']); ?></p>
                                            <small class="notification-timestamp"><?php echo date("M d, Y h:i A", strtotime($notification_h['created_at'])); ?></small>
                                        </div>
                                        <a href="./mark_notification_read.php?id=<?php echo $notification_h['notification_id']; ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="mark-as-read-btn" title="Mark as read">
                                            <span class="read-dot-icon"></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="no-notifications">No new notifications.</li>
                            <?php endif; ?>
                        </ul>
                        <div class="notification-footer">
                            <a href="./all_notifications.php" class="view-all-notifications-link">View All Notifications</a>
                        </div>
                    </div>
                </div>
                <a href="./student_account.php" class="profile-icon admin desktop-only">
                       <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </a>
                <button class="mobile-nav-toggle" aria-controls="primary-navigation" aria-expanded="false">
                    <span class="sr-only">Menu</span>
                </button>
            </div>
        </div>
    </header>

<main>
    <div class="announcements-wrapper">
        <h1 class="page-main-title">Announcements</h1>
        <div class="announcements-list">
            <?php if ($announcements_result && $announcements_result->num_rows > 0): ?>
                <?php while ($row = $announcements_result->fetch_assoc()): ?>
                    <div class="announcement-card" data-id="<?php echo $row['announcement_id']; ?>">
                        <div class="announcement-header">
                            <h2 class="announcement-title">
                                <span class="unread-dot"></span>
                                <?php echo htmlspecialchars($row['title']); ?>
                            </h2>
                            <div class="announcement-meta">
                                <span class="meta-item author">
                                    <svg class="meta-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 6c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2m0 10c2.7 0 5.8 1.29 6 2H6c.23-.72 3.31-2 6-2m0-12C9.79 4 8 5.79 8 8s1.79 4 4 4 4-1.79-4-4-1.79-4-4-4zm0 10c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    By <?php echo htmlspecialchars($row['author_name'] ?? 'Admin'); ?>
                                </span>
                                <span class="meta-item date">
                                    <svg class="meta-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                                    <?php echo date("F j, Y, g:i a", strtotime($row['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="announcement-content-preview">
                            <?php echo nl2br(htmlspecialchars(strip_tags(substr($row['content'], 0, 200)))) . (strlen(strip_tags($row['content'])) > 200 ? '...' : ''); ?>
                        </div>
                        <div class="announcement-footer">
                            <span class="read-more-link">Read More &rarr;</span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-records-message">There are currently no announcements.</p>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <nav class="pagination-container" aria-label="Page navigation">
                <ul class="pagination">
                    <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                        <a class="page-link" href="<?php if($page <= 1){ echo '#'; } else { echo "?page=" . ($page - 1); } ?>">Previous</a>
                    </li>
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if($page == $i) {echo 'active'; } ?>">
                        <a class="page-link" href="student_announcements.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php if($page >= $total_pages) { echo 'disabled'; } ?>">
                        <a class="page-link" href="<?php if($page >= $total_pages){ echo '#'; } else { echo "?page=" . ($page + 1); } ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</main>

<div id="announcementModal" class="announcement-modal">
    <div class="announcement-modal-content">
        <div class="announcement-modal-header">
            <h2 id="modalTitle"></h2>
            <span class="announcement-close-btn">&times;</span>
        </div>
        <div class="announcement-modal-body">
            <div id="modalMeta" class="announcement-meta"></div>
            <div id="modalContent"></div>
            <img id="modalImage" class="announcement-modal-image" src="" alt="Announcement Image" style="display: none;">
        </div>
    </div>
</div>
<script src="./student_scripts.js"></script>
</body>
</html>