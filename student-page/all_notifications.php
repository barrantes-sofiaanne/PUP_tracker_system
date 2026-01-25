<?php
require_once '../PHP/dbcon.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["current_user_id"]) || !isset($_SESSION["user_student_number"])) {
    header("Location: ./student_login.php");
    exit();
}

$student_stud_number_from_session = $_SESSION["user_student_number"];
$page_error_message = null;

// This block is for the header dropdown
$header_unread_notifications = [];
$header_unread_count = 0;
if (isset($conn) && $conn instanceof mysqli) {
    $sql_header_list = "SELECT notification_id, message, created_at, link
                        FROM notifications_tbl
                        WHERE student_number = ? AND is_read = FALSE
                        ORDER BY created_at DESC LIMIT 5";
    if ($stmt_header_list = $conn->prepare($sql_header_list)) {
        $stmt_header_list->bind_param("s", $student_stud_number_from_session);
        $stmt_header_list->execute();
        $result_header_list = $stmt_header_list->get_result();
        while ($row_h_notif = $result_header_list->fetch_assoc()) {
            $header_unread_notifications[] = $row_h_notif;
        }
        $stmt_header_list->close();
    }

    $sql_header_count = "SELECT COUNT(*) as total_unread FROM notifications_tbl WHERE student_number = ? AND is_read = FALSE";
    if ($stmt_header_count = $conn->prepare($sql_header_count)) {
        $stmt_header_count->bind_param("s", $student_stud_number_from_session);
        $stmt_header_count->execute();
        $result_h_count = $stmt_header_count->get_result()->fetch_assoc();
        $header_unread_count = $result_h_count['total_unread'] ?? 0;
        $stmt_header_count->close();
    }
}

// This block is for the main page content
$all_notifications_list = [];
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$notifications_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $notifications_per_page;
$total_notifications = 0;

if (isset($conn) && $conn instanceof mysqli) {
    $sql_count_base = "SELECT COUNT(*) FROM notifications_tbl WHERE student_number = ?";
    $params_count = [$student_stud_number_from_session];
    $types_count = "s";
    if (!empty($search_term)) {
        $sql_count_base .= " AND message LIKE ?";
        $search_like = "%" . $search_term . "%";
        $params_count[] = $search_like;
        $types_count .= "s";
    }
    if ($stmt_total_count = $conn->prepare($sql_count_base)) {
        $stmt_total_count->bind_param($types_count, ...$params_count);
        $stmt_total_count->execute();
        $stmt_total_count->bind_result($total_notifications);
        $stmt_total_count->fetch();
        $stmt_total_count->close();
    }

    $total_pages = ($total_notifications > 0) ? ceil($total_notifications / $notifications_per_page) : 0;

    if ($total_notifications > 0 || ($total_notifications === 0 && empty($search_term))) {
        $sql_page_notifications_base = "SELECT notification_id, message, created_at, link, is_read
                                        FROM notifications_tbl WHERE student_number = ?";
        $params_page = [$student_stud_number_from_session];
        $types_page = "s";
        if (!empty($search_term)) {
            $sql_page_notifications_base .= " AND message LIKE ?";
            $params_page[] = $search_like;
            $types_page .= "s";
        }
        $sql_page_notifications_base .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params_page[] = $notifications_per_page;
        $params_page[] = $offset;
        $types_page .= "ii";
        if ($stmt_page_notifications = $conn->prepare($sql_page_notifications_base)) {
            $stmt_page_notifications->bind_param($types_page, ...$params_page);
            $stmt_page_notifications->execute();
            $result_page_notifications = $stmt_page_notifications->get_result();
            while ($row_page_n = $result_page_notifications->fetch_assoc()) {
                $all_notifications_list[] = $row_page_n;
            }
            $stmt_page_notifications->close();
        }
    }
}

$grouped_notifications = [];
if (!empty($all_notifications_list)) {
    foreach ($all_notifications_list as $notification) {
        $date = new DateTime($notification['created_at']);
        $today = new DateTime('today');
        $yesterday = (new DateTime('today'))->modify('-1 day');
        $date_key = '';
        if ($date->format('Y-m-d') === $today->format('Y-m-d')) {
            $date_key = 'Today';
        } elseif ($date->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            $date_key = 'Yesterday';
        } else {
            $date_key = $date->format('F j, Y');
        }
        $grouped_notifications[$date_key][] = $notification;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications</title>
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
                <a href="./student_announcements.php">Announcements</a>

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
                    <?php if ($header_unread_count > 0): ?>
                        <span class="notification-count"><?php echo $header_unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <div class="notifications-dropdown" id="notificationsDropdownContent">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <button id="mark-all-read-btn">Mark all as read</button>
                    </div>
                    <ul class="notification-list">
                        <?php if (!empty($header_unread_notifications)): ?>
                            <?php foreach ($header_unread_notifications as $notification_h): ?>
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
    <div class="all-notifications-wrapper">
        <div class="notifications-header-area">
            <h1 class="page-main-title">All Notifications</h1>
            <?php if ($header_unread_count > 0 && empty($search_term)): ?>
            <button id="markAllReadPageBtn" class="btn btn-primary">Mark All As Read</button>
            <?php endif; ?>
        </div>

        <div class="search-form-container">
            <form method="GET" action="all_notifications.php" class="search-form">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" name="search" placeholder="Search notifications..." value="<?php echo htmlspecialchars($search_term); ?>" aria-label="Search notifications">
                <?php if (!empty($search_term)): ?>
                    <a href="all_notifications.php" class="clear-search-btn" title="Clear search">&times;</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (!empty($page_error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($page_error_message); ?></p>
        <?php endif; ?>

        <div class="notifications-list-container">
            <?php if (!empty($grouped_notifications)): ?>
                <?php foreach ($grouped_notifications as $date_group => $notifications_in_group): ?>
                    <h2 class="date-group-header"><?php echo htmlspecialchars($date_group); ?></h2>
                    <ul class="main-notification-list">
                        <?php foreach ($notifications_in_group as $notification_item): ?>
                            <li class="main-notification-item <?php echo $notification_item['is_read'] ? 'status-read' : 'status-unread'; ?>">
                                <div class="main-notification-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 2H4c-1.103 0-2 .897-2 2v18l4-4h14c1.103 0 2-.897 2-2V4c0-1.103-.897-2-2-2zm-3 9h-4v4h-2v-4H7V9h4V5h2v4h4v2z"/></svg>
                                </div>
                                <div class="main-notification-content">
                                    <div class="main-notification-body">
                                        <p class="main-notification-message">
                                            <?php
                                            $message_text = htmlspecialchars(strip_tags($notification_item['message']));
                                            $link_url = !empty($notification_item['link']) ? htmlspecialchars($notification_item['link']) . (strpos($notification_item['link'], '?') === false ? '?' : '&') . 'notif_id=' . $notification_item['notification_id'] : null;
                                            ?>
                                            <?php if ($link_url): ?>
                                                <a href="<?php echo $link_url; ?>"><?php echo $message_text; ?></a>
                                            <?php else: ?>
                                                <?php echo $message_text; ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="main-notification-footer">
                                        <small class="main-notification-timestamp">
                                            <span><?php echo date("g:i a", strtotime($notification_item['created_at'])); ?></span>
                                        </small>
                                        <?php if (!$notification_item['is_read']): ?>
                                            <a href="./mark_notification_read.php?id=<?php echo $notification_item['notification_id']; ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="mark-main-as-read">
                                                Mark as read
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            <?php elseif (empty($page_error_message)): ?>
                <div class="no-records-message">
                    <svg class="no-records-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.71,3.29a1,1,0,0,0-1.42,0L12,11.59,3.71,3.29A1,1,0,0,0,2.29,4.71L10.59,13,2.29,21.29a1,1,0,1,0,1.42,1.42L12,14.41l8.29,8.3a1,1,0,0,0,1.42-1.42L13.41,13l8.3-8.29A1,1,0,0,0,21.71,3.29Z"/></svg>
                    <h3>No Notifications</h3>
                    <p>You have no notifications<?php echo !empty($search_term) ? ' matching your search "' . htmlspecialchars($search_term) . '"' : ' in your history'; ?>.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav class="pagination-container">
            <ul class="pagination">
                <li class="page-item <?php if($current_page <= 1){ echo 'disabled'; } ?>">
                    <a class="page-link" href="<?php if($current_page <= 1){ echo '#'; } else { echo "?page=" . ($current_page - 1) . (!empty($search_term) ? '&search='.urlencode($search_term) : ''); } ?>">Previous</a>
                </li>
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php if($current_page == $i) {echo 'active'; } ?>">
                    <a class="page-link" href="?page=<?php echo $i; echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php if($current_page >= $total_pages) { echo 'disabled'; } ?>">
                    <a class="page-link" href="<?php if($current_page >= $total_pages){ echo '#'; } else { echo "?page=" . ($current_page + 1) . (!empty($search_term) ? '&search='.urlencode($search_term) : ''); } ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</main>
<script src="./student_scripts.js"></script>
</body>
</html>