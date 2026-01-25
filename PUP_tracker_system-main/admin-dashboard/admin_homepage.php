<?php
date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
@require '../PHP/dbcon.php'; 

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


function getGreeting() {
    $hour = date('H');
    if ($hour < 12) return "Good Morning, Admin!";
    if ($hour < 18) return "Good Afternoon, Admin!";
    return "Good Evening, Admin!";
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $response = ['data' => null, 'error' => null];
    
    if (!isset($conn) || $conn->connect_error) {
        $response['error'] = "Database connection failed.";
        echo json_encode($response);
        exit();
    }

    try {
        if ($_GET['action'] == 'get_courses') {
            $sql = "SELECT course_id, course_name FROM course_tbl ORDER BY course_name";
            $result = $conn->query($sql);
            if (!$result) throw new Exception("Course query failed: " . $conn->error);
            $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
        }
        
        if ($_GET['action'] == 'get_years') {
            $sql = "SELECT year_id, year FROM year_tbl ORDER BY year";
            $result = $conn->query($sql);
            if (!$result) throw new Exception("Year query failed: " . $conn->error);
            $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
        }

        if ($_GET['action'] == 'get_dashboard_data') {
            $courseFilter = isset($_GET['course']) && $_GET['course'] !== 'all' ? $_GET['course'] : null;
            $yearFilter = isset($_GET['year']) && $_GET['year'] !== 'all' ? $_GET['year'] : null;
            $periodFilter = isset($_GET['period']) ? $_GET['period'] : 'all';

            $whereClauses = "";
            $params = [];
            $types = "";
            if ($courseFilter) { $whereClauses .= " AND u.course_id = ?"; $params[] = $courseFilter; $types .= "i"; }
            if ($yearFilter) { $whereClauses .= " AND u.year_id = ?"; $params[] = $yearFilter; $types .= "i"; }

            $studentsQuery = "SELECT c.course_name, COUNT(u.user_id) as count FROM users_tbl u JOIN course_tbl c ON u.course_id = c.course_id WHERE 1 {$whereClauses} GROUP BY c.course_name ORDER BY c.course_name";
            $stmt_students = $conn->prepare($studentsQuery);
            if (!$stmt_students) throw new Exception("Student query failed: " . $conn->error);
            if (!empty($params)) $stmt_students->bind_param($types, ...$params);
            $stmt_students->execute();
            $courseData = $stmt_students->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_students->close();

            $violationsQuery = "SELECT vt.violation_type, COUNT(v.violation_id) as count FROM violation_tbl v JOIN users_tbl u ON v.student_number = u.student_number JOIN violation_type_tbl vt ON v.violation_type = vt.violation_type_id WHERE 1 {$whereClauses}";
            switch ($periodFilter) {
                case 'today': $violationsQuery .= " AND DATE(v.violation_date) = CURDATE()"; break;
                case 'week': $violationsQuery .= " AND YEARWEEK(v.violation_date, 1) = YEARWEEK(CURDATE(), 1)"; break;
                case 'month': $violationsQuery .= " AND MONTH(v.violation_date) = MONTH(CURDATE()) AND YEAR(v.violation_date) = YEAR(CURDATE())"; break;
                case 'year': $violationsQuery .= " AND YEAR(v.violation_date) = YEAR(CURDATE())"; break;
            }
            $violationsQuery .= " GROUP BY vt.violation_type ORDER BY count DESC";
            
            $stmt_violations = $conn->prepare($violationsQuery);
            if (!$stmt_violations) throw new Exception("Violation query failed: " . $conn->error);
            if (!empty($params)) $stmt_violations->bind_param($types, ...$params);
            $stmt_violations->execute();
            $violationData = $stmt_violations->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_violations->close();
            
            $response['data'] = [
                'courses' => ['labels' => array_column($courseData, 'course_name'), 'data' => array_column($courseData, 'count')],
                'violation' => ['labels' => array_column($violationData, 'violation_type'), 'data' => array_column($violationData, 'count')]
            ];
        }

    } catch (Exception $e) {
        $response['error'] = "A fatal error occurred: " . $e->getMessage();
    }
    
    if (isset($conn)) $conn->close();
    echo json_encode($response);
    exit();
}

$totalStudents = $conn->query("SELECT COUNT(*) as count FROM users_tbl")->fetch_assoc()['count'] ?? 0;
$totalViolations = $conn->query("SELECT COUNT(*) as count FROM violation_tbl")->fetch_assoc()['count'] ?? 0;
$totalCourses = $conn->query("SELECT COUNT(*) as count FROM course_tbl")->fetch_assoc()['count'] ?? 0;
$topViolationResult = $conn->query("SELECT vt.violation_type, COUNT(v.violation_id) as count FROM violation_tbl v JOIN violation_type_tbl vt ON v.violation_type = vt.violation_type_id GROUP BY vt.violation_type ORDER BY count DESC LIMIT 1")->fetch_assoc();
$topViolation = $topViolationResult['violation_type'] ?? 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./admin_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    
    <header class="main-header">
       <div class="header-content">
         <div class="logo"><img src="../IMAGE/Tracker-logo.png" alt="PUP Logo"></div>
         <nav class="main-nav">
             <a href="./admin_homepage.php" class="active-nav">Home</a>
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
            <a href="../PHP/admin_account.php" class="admin-profile">
                <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </a>
         </div>
       </div>
    </header>

<main>
    <div class="admin-wrapper" id="dashboard-content">
        <div class="loading-overlay" id="loadingOverlay"><div class="spinner"></div></div>
        <div class="dashboard-header">
            <div>
                <h1 class="page-main-title">Admin Dashboard</h1>
                <p class="page-subtitle"><?php echo getGreeting(); ?></p>
            </div>
            <div class="controls-container">
                <div class="filter-group">
                    <label for="courseFilter">Course:</label>
                    <div class="select-wrapper"><select id="courseFilter" name="courseFilter"><option value="all">All Courses</option></select></div>
                </div>
                <div class="filter-group">
                    <label for="yearFilter">Year:</label>
                    <div class="select-wrapper"><select id="yearFilter" name="yearFilter"><option value="all">All Years</option></select></div>
                </div>
                <div class="filter-group">
                    <label for="datePeriod">Date:</label>
                    <div class="select-wrapper"><select id="datePeriod" name="datePeriod"><option value="all">All Time</option><option value="today">Today</option><option value="week">This Week</option><option value="month">This Month</option><option value="year">This Year</option></select></div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></div>
                <div class="stat-info"><h3 class="stat-title">Total Students</h3><p class="stat-value"><?php echo $totalStudents; ?></p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L1 21h22M12 6l7.55 13H4.45M11 10h2v6h-2zm0 8h2v2h-2z"/></svg></div>
                <div class="stat-info"><h3 class="stat-title">Total Violations</h3><p class="stat-value"><?php echo $totalViolations; ?></p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/></svg></div>
                <div class="stat-info"><h3 class="stat-title">Courses Offered</h3><p class="stat-value"><?php echo $totalCourses; ?></p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18 17h-2v-1c0-.55-.45-1-1-1s-1 .45-1 1v1h-2v-1c0-.55-.45-1-1-1s-1 .45-1 1v1H8v-1c0-.55-.45-1-1-1s-1 .45-1 1v1H4V7h16v10h-2zm-9-5c0-.55-.45-1-1-1s-1 .45-1 1 .45 1 1 1 1-.45 1-1zm4 0c0-.55-.45-1-1-1s-1 .45-1 1 .45 1 1 1 1-.45 1-1zM19 3c.55 0 1 .45 1 1v1H4V4c0-.55.45-1 1-1h1V1h2v2h8V1h2v2h1z"/></svg></div>
                <div class="stat-info"><h3 class="stat-title">Top Violation</h3><p class="stat-value small-text"><?php echo $topViolation; ?></p></div>
            </div>
        </div>

        <div class="dashboard-grid simple">
            <div class="chart-card">
                <h2 class="chart-title">Students by Course</h2>
                <p class="chart-insight" id="courseInsight"></p>
                <div class="chart-body" id="courseChartContainer"><canvas id="courseChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h2 class="chart-title">Violations Summary</h2>
                <p class="chart-insight" id="violationInsight"></p>
                 <div class="chart-body" id="violationChartContainer"><canvas id="violationChart"></canvas></div>
            </div>
        </div>
    </div>
</main>
<script src="../JS/admin_header_script.js?v=<?php echo time(); ?>"></script>
<script src="./admin_scripts.js"></script>
</body>
</html>
<?php if(isset($conn)) $conn->close(); ?>