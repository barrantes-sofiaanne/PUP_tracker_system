<?php
session_start();
include '../PHP/dbcon.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_session_id = $_SESSION['admin_id'] ?? null;

if ($admin_session_id === null) {
    header("Location: ../admin-login/admin_login.php"); 
    exit();
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

$adminPageData = [
    'isLoggedIn' => false,
    'firstName' => 'N/A',
    'middleName' => 'N/A',
    'lastName' => 'N/A',
    'position' => 'N/A',
    'email' => 'N/A',
    'errorMessage' => null
];

if (isset($_SESSION['admin_id'])) {
    $adminPageData['isLoggedIn'] = true;
    $admin_id = $_SESSION['admin_id'];

    if ($conn) {
        $query = "SELECT ai.firstname, ai.middlename, ai.lastname, ai.Position, a.email
                  FROM admin_info_tbl ai
                  JOIN admins a ON ai.admin_id = a.id
                  WHERE ai.admin_id = ?";

        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $admin_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $adminDetails = $result->fetch_assoc();
                    $adminPageData['firstName'] = htmlspecialchars($adminDetails['firstname']);
                    $adminPageData['middleName'] = $adminDetails['middlename'] ? htmlspecialchars($adminDetails['middlename']) : '';
                    $adminPageData['lastName'] = htmlspecialchars($adminDetails['lastname']);
                    $adminPageData['position'] = htmlspecialchars($adminDetails['Position']);
                    $adminPageData['email'] = htmlspecialchars($adminDetails['email']);
                } else {
                    $adminPageData['errorMessage'] = 'Admin details not found in the database.';
                }
            } else {
                $adminPageData['errorMessage'] = 'Failed to execute database query: ' . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $adminPageData['errorMessage'] = 'Failed to prepare database query: ' . htmlspecialchars($conn->error);
        }
        $conn->close();
    } else {
        $adminPageData['errorMessage'] = 'Database connection could not be established.';
    }
} else {
    $adminPageData['errorMessage'] = 'Not authorized or session has expired. Please log in.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Account Information</title>
    <link rel="stylesheet" href="../CSS/admin_account.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
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
            <a href="./admin_account.php" class="admin-profile">
                <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </a>
         </div>
       </div>
    </header>

    <main class="container">
        <div class="account-container">
            <h1>Account Information</h1>
            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">
                        <i class="fas fa-user icon-style"></i>
                        <strong>First Name:</strong>
                    </span>
                    <span class="info-value" id="adminFirstName"></span>
                </div>
                <div class="info-row">
                    <span class="info-label">
                        <i class="fas fa-user icon-style"></i>
                        <strong>Middle Name:</strong>
                    </span>
                    <span class="info-value" id="adminMiddleName"></span>
                </div>
                <div class="info-row">
                    <span class="info-label">
                        <i class="fas fa-user icon-style"></i>
                        <strong>Last Name:</strong>
                    </span>
                    <span class="info-value" id="adminLastName"></span>
                </div>
                <div class="info-row">
                    <span class="info-label">
                        <i class="fas fa-briefcase icon-style"></i>
                        <strong>Position:</strong>
                    </span>
                    <span class="info-value" id="adminPosition"></span>
                </div>
                <div class="info-row">
                    <span class="info-label">
                        <i class="fas fa-envelope icon-style"></i>
                        <strong>Email:</strong>
                    </span>
                    <span class="info-value" id="adminEmail"></span>
                </div>
            </div>
            <button id="signOutBtn">Sign Out</button>
            <div id="errorMessageDisplay" style="color: red; margin-top: 15px;"></div>
        </div>
    </main>
    
    <script src="../JS/admin_header_script.js?v=<?php echo time(); ?>"></script>
    <script>
        const adminData = <?php echo json_encode($adminPageData); ?>;

        document.addEventListener("DOMContentLoaded", function() {
            const adminFirstNameElement = document.getElementById("adminFirstName");
            const adminMiddleNameElement = document.getElementById("adminMiddleName");
            const adminLastNameElement = document.getElementById("adminLastName");
            const adminPositionElement = document.getElementById("adminPosition");
            const adminEmailElement = document.getElementById("adminEmail");
            const signOutBtn = document.getElementById("signOutBtn");
            const errorMessageDisplayElement = document.getElementById("errorMessageDisplay");

            if (adminData.isLoggedIn && !adminData.errorMessage) {
                adminFirstNameElement.textContent = adminData.firstName;
                adminMiddleNameElement.textContent = adminData.middleName || '';
                adminLastNameElement.textContent = adminData.lastName;
                adminPositionElement.textContent = adminData.position;
                adminEmailElement.textContent = adminData.email;
            } else {
                adminFirstNameElement.textContent = 'N/A';
                adminMiddleNameElement.textContent = 'N/A';
                adminLastNameElement.textContent = 'N/A';
                adminPositionElement.textContent = 'N/A';
                adminEmailElement.textContent = 'N/A';
                if (errorMessageDisplayElement && adminData.errorMessage) {
                    errorMessageDisplayElement.textContent = "Error: " + adminData.errorMessage;
                }
                if (adminData.errorMessage && (adminData.errorMessage.includes("Not authorized") || adminData.errorMessage.includes("session has expired"))) {
                    setTimeout(() => {
                        window.location.href = './login.html';
                    }, 3000);
                }
            }

            if (signOutBtn) {
                signOutBtn.addEventListener("click", function() {
                    window.location.href = '../PHP/logout.php?role=admins';
                });
            }
        });
    </script>
</body>
</html>