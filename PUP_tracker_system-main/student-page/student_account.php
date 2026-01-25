<?php
require_once './student_auth_check.php';

$session_user_id_for_account = $_SESSION["current_user_id"];
$student_stud_number_from_session = $_SESSION["user_student_number"];


if (isset($_GET['notif_id']) && is_numeric($_GET['notif_id']) && isset($conn)) {
    $notification_id_to_mark = (int)$_GET['notif_id'];
    if (!empty($student_stud_number_from_session)) {
        $sql_mark_direct = "UPDATE notifications_tbl SET is_read = TRUE 
                            WHERE notification_id = ? AND student_number = ?";
        if ($stmt_mark_direct = $conn->prepare($sql_mark_direct)) {
            $stmt_mark_direct->bind_param("is", $notification_id_to_mark, $student_stud_number_from_session);
            $stmt_mark_direct->execute();
            $stmt_mark_direct->close();
        }
    }
}

$unread_notifications_header = [];
$unread_notification_count_header = 0;

if (isset($conn) && !empty($student_stud_number_from_session)) {
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

$student_info = null;
$page_error = null;

$sql_student = "SELECT u.first_name, u.middle_name, u.last_name, u.student_number, u.email,
                       c.course_name,
                       s.section_name,
                       y.year AS year_level,
                       g.gender_name
                FROM users_tbl u
                LEFT JOIN course_tbl c ON u.course_id = c.course_id
                LEFT JOIN section_tbl s ON u.section_id = s.section_id
                LEFT JOIN year_tbl y ON u.year_id = y.year_id
                LEFT JOIN gender_tbl g ON u.gender_id = g.gender_id
                WHERE u.user_id = ?";

if (isset($conn)) {
    if ($stmt_student = $conn->prepare($sql_student)) {
        $stmt_student->bind_param("i", $session_user_id_for_account);
        $stmt_student->execute();
        $result_student = $stmt_student->get_result();
        if ($result_student->num_rows == 1) {
            $student_info = $result_student->fetch_assoc();
        } else {
            $page_error = "Student information not found for your account.";
        }
        $stmt_student->close();
    } else {
        $page_error = "An error occurred while fetching your account details.";
    }
} else {
    $page_error = "Database connection error. Cannot fetch account details.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Account</title>
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
                    <a href="./student_account.php" class="profile-icon admin active-nav">
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
            <a href="./student_account.php" class="profile-icon admin active-nav desktop-only">
                <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </a>
            <button class="mobile-nav-toggle" aria-controls="primary-navigation" aria-expanded="false">
                <span class="sr-only">Menu</span>
            </button>
        </div>
    </div>
</header>

<main>
    <div class="account-wrapper">
        <?php if ($page_error): ?>
            <p class="error-message"><?php echo htmlspecialchars($page_error); ?></p>
        <?php elseif ($student_info): ?>
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
                    <h1 class="profile-name"><?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></h1>
                    <p class="profile-subtext"><?php echo htmlspecialchars($student_info['student_number']); ?></p>
                    <p class="profile-subtext"><?php echo htmlspecialchars($student_info['email']); ?></p>
                </div>
                
                <div class="profile-details">
                    <div class="info-group">
                        <h3>Personal Information</h3>
                        <div class="info-item">
                            <span class="info-label">First Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_info['first_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Middle Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_info['middle_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_info['last_name'] ?? 'N/A'); ?></span>
                        </div>
                         <div class="info-item">
                            <span class="info-label">Gender</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_info['gender_name'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    <div class="info-group">
                        <h3>Academic Information</h3>
                        <div class="info-item">
                            <span class="info-label">Course</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_info['course_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Year Level</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_info['year_level'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Section</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_info['section_name'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="profile-actions">
                    <button id="changePasswordBtn" class="btn btn-secondary">Change Password</button>
                    <a href="../PHP/logout.php" id="signOutBtn" class="btn btn-primary">Sign Out</a>
                </div>
            </div>
        <?php else: ?>
            <p class="error-message">Could not retrieve student information.</p>
        <?php endif; ?>
    </div>
</main>

<div id="changePasswordModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Change Password</h2>
            <button class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body">
            <form id="changePasswordForm">
                <div id="form-message" class="message" style="display:none;"></div>
                <div class="form-input-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-input-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-input-group">
                    <label for="confirm_new_password">Confirm New Password</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="./student_scripts.js"></script>
<?php
    if (isset($conn)) {
        $conn->close();
    }
?>
</body>
</html>