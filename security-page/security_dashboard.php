<?php
date_default_timezone_set('Asia/Manila');

function getGreeting() {
    $hour = date('H');
    if ($hour < 12) return "Good Morning, Security!";
    if ($hour < 18) return "Good Afternoon, Security!";
    return "Good Evening, Security!";
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $response = ['data' => null, 'error' => null];
    
    @require '../PHP/dbcon.php'; 
    if (!isset($conn) || $conn->connect_error) {
        $response['error'] = "Database connection failed.";
        echo json_encode($response);
        exit();
    }

    try {
        if ($_GET['action'] == 'get_courses') {
            $sql = "SELECT course_id, course_name FROM course_tbl ORDER BY course_name";
            $result = $conn->query($sql);
            $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
        }
        
        if ($_GET['action'] == 'get_years') {
            $sql = "SELECT year_id, year FROM year_tbl ORDER BY year";
            $result = $conn->query($sql);
            $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
        }

        if ($_GET['action'] == 'get_dashboard_data') {
            $courseFilter = isset($_GET['course']) && $_GET['course'] !== 'all' ? $_GET['course'] : null;
            $yearFilter = isset($_GET['year']) && $_GET['year'] !== 'all' ? $_GET['year'] : null;
            $startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : null;
            $endDate = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : null;

            $whereClauses = "";
            $params = [];
            $types = "";

            if ($courseFilter) { $whereClauses .= " AND u.course_id = ?"; $params[] = $courseFilter; $types .= "i"; }
            if ($yearFilter) { $whereClauses .= " AND u.year_id = ?"; $params[] = $yearFilter; $types .= "i"; }
            if ($startDate) { $whereClauses .= " AND DATE(v.violation_date) >= ?"; $params[] = $startDate; $types .= "s"; }
            if ($endDate) { $whereClauses .= " AND DATE(v.violation_date) <= ?"; $params[] = $endDate; $types .= "s"; }
            
            $violationsQuery = "SELECT vt.violation_type, COUNT(v.violation_id) as count FROM violation_tbl v JOIN users_tbl u ON v.student_number = u.student_number JOIN violation_type_tbl vt ON v.violation_type = vt.violation_type_id WHERE 1 {$whereClauses} GROUP BY vt.violation_type ORDER BY count DESC";
            
            $stmt_violations = $conn->prepare($violationsQuery);
            if (!empty($params)) $stmt_violations->bind_param($types, ...$params);
            $stmt_violations->execute();
            $violationData = $stmt_violations->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_violations->close();
            
            $response['data'] = [
                'violation' => ['labels' => array_column($violationData, 'violation_type'), 'data' => array_column($violationData, 'count')]
            ];
        }

    } catch (Exception $e) {
        $response['error'] = "A fatal error occurred: " . $e->getMessage();
    }
    
    if ($conn) $conn->close();
    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="./security_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="page-container" id="pageContainer">
        <aside class="side-menu">
            <div class="menu-header">
                <img src="../IMAGE/Tracker-logo.png" alt="PUP Logo" class="menu-logo">
                <button class="close-btn" id="closeMenuBtn">&times;</button>
            </div>
            <nav class="menu-nav">
                <a href="security_dashboard.php" class="nav-item active"><i class="fas fa-chart-bar"></i> Dashboard</a>
                <a href="security_violation_page.php" class="nav-item"><i class="fas fa-exclamation-triangle"></i> Violations</a>
                <a href="security_account.php" class="nav-item"><i class="fas fa-user-shield"></i> My Account</a>
                <a href="../PHP/logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <div class="page-wrapper" id="pageWrapper">
            <header class="main-header">
                <div class="header-content">
                    <div class="logo"><img src="../IMAGE/Tracker-logo.png" alt="PUP Logo"></div>
                    <nav class="main-nav">
                        <a href="security_dashboard.php" class="active-nav">Dashboard</a>
                        <a href="security_violation_page.php">Violations</a>
                    </nav>
                    <div class="user-icons">
                        <a href="notification.html" class="notification"><svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13.586V10c0-3.217-2.185-5.927-5.145-6.742C13.562 2.52 12.846 2 12 2s-1.562.52-1.855 1.258C7.185 4.073 5 6.783 5 10v3.586l-1.707 1.707A.996.996 0 0 0 3 16v2a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-2a.996.996 0 0 0-.293-.707L19 13.586zM19 17H5v-.586l1.707-1.707A.996.996 0 0 0 7 14v-4c0-2.757 2.243-5 5-5s5 2.243 5 5v4c0 .266.105.52.293.707L19 16.414V17zm-7 5a2.98 2.98 0 0 0 2.818-2H9.182A2.98 2.98 0 0 0 12 22z"/></svg></a>
                        <a href="security_account.php" class="admin-profile"><svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></a>
                    </div>
                    <button class="menu-toggle" id="openMenuBtn"><i class="fas fa-bars"></i></button>
                </div>
            </header>

            <main>
                <div class="admin-wrapper" id="dashboard-content">
                    <div class="loading-overlay" id="loadingOverlay"><div class="spinner"></div></div>
                    <div class="dashboard-header">
                        <div class="header-text">
                            <h1 class="page-main-title">Security Dashboard</h1>
                            <p class="page-subtitle"><?php echo getGreeting(); ?></p>
                        </div>
                         <div class="filters-accordion">
                            <button id="filtersToggleBtn" class="filters-toggle-btn">
                                <i class="fas fa-filter"></i>
                                <span>Filter Dashboard</span>
                                <i class="fas fa-chevron-down arrow-icon"></i>
                            </button>
                            <div class="controls-container" id="filtersContainer">
                                <div class="filter-group">
                                    <label for="courseFilter">Course:</label>
                                    <div class="select-wrapper"><select id="courseFilter" name="courseFilter"><option value="all">All Courses</option></select></div>
                                </div>
                                <div class="filter-group">
                                    <label for="yearFilter">Year:</label>
                                    <div class="select-wrapper"><select id="yearFilter" name="yearFilter"><option value="all">All Years</option></select></div>
                                </div>
                                <div class="filter-group">
                                    <label for="dateRangePicker">Date Range:</label>
                                    <div class="date-range-wrapper">
                                        <i class="fas fa-calendar-alt"></i>
                                        <input type="text" id="dateRangePicker" placeholder="Select Date Range">
                                    </div>
                                    <input type="hidden" id="startDateFilter">
                                    <input type="hidden" id="endDateFilter">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-grid">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h2 class="chart-title">Violations Summary</h2>
                                <p class="chart-insight" id="violationInsight"></p>
                            </div>
                            <div class="chart-body" id="violationChartContainer"><canvas id="violationChart"></canvas></div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
        <div class="overlay" id="overlay"></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="./security_scripts.js"></script>
</body>
</html>