<?php
session_start();
include '../PHP/dbcon.php';

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

$limit = 25;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $limit;

function getTotalStudents($conn, $search_student, $course_id_filter, $year_id_filter, $section_id_filter, $status_id_filter) {
    $countQuery = "SELECT COUNT(*) AS total FROM users_tbl u WHERE 1";
    if (!empty($search_student)) {
        $countQuery .= " AND (u.student_number LIKE '%" . mysqli_real_escape_string($conn, $search_student) . "%' OR u.first_name LIKE '%" . mysqli_real_escape_string($conn, $search_student) . "%' OR u.last_name LIKE '%" . mysqli_real_escape_string($conn, $search_student) . "%')";
    }
    if (!empty($course_id_filter)) {
        $countQuery .= " AND u.course_id = '" . mysqli_real_escape_string($conn, $course_id_filter) . "'";
    }
    if (!empty($year_id_filter)) {
        $countQuery .= " AND u.year_id = '" . mysqli_real_escape_string($conn, $year_id_filter) . "'";
    }
    if (!empty($section_id_filter)) {
        $countQuery .= " AND u.section_id = '" . mysqli_real_escape_string($conn, $section_id_filter) . "'";
    }
    if (!empty($status_id_filter)) {
        $countQuery .= " AND u.status_id = '" . mysqli_real_escape_string($conn, $status_id_filter) . "'";
    }
    $countResult = mysqli_query($conn, $countQuery);
    $countRow = mysqli_fetch_assoc($countResult);
    return $countRow['total'];
}

$courseOptions = [];
if ($conn) {
    $courseQuery = "SELECT course_id, course_name FROM course_tbl ORDER BY course_name ASC";
    $courseResult = mysqli_query($conn, $courseQuery);
    if ($courseResult) {
        while ($row = mysqli_fetch_assoc($courseResult)) {
            $courseOptions[] = $row;
        }
    }
}

$yearOptions = [];
if ($conn) {
    $yearQuery = "SELECT year_id, year FROM year_tbl ORDER BY year ASC";
    $yearResult = mysqli_query($conn, $yearQuery);
    if ($yearResult) {
        while ($row = mysqli_fetch_assoc($yearResult)) {
            $yearOptions[] = $row;
        }
    }
}

$sectionOptions = [];
if ($conn) {
    $sectionQuery = "SELECT section_id, section_name FROM section_tbl ORDER BY section_name ASC";
    $sectionResult = mysqli_query($conn, $sectionQuery);
    if ($sectionResult) {
        while ($row = mysqli_fetch_assoc($sectionResult)) {
            $sectionOptions[] = $row;
        }
    }
}

$statusOptions = [];
if ($conn) {
    $statusQuery = "SELECT status_id, status_name FROM status_tbl ORDER BY status_name ASC";
    $statusResult = mysqli_query($conn, $statusQuery);
    if ($statusResult) {
        while ($row = mysqli_fetch_assoc($statusResult)) {
            $statusOptions[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="stylesheet" href="../CSS/announcement_style.css">
    <link rel="stylesheet" href="./user_management.css">
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
         <a href="../user-management/user_management.php" class="active-nav">User Management</a>
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
    <div class="container">
        <h1>User Management</h1>
        <div class="tabs">
            <button class="tab active" data-tab="students"><i class="fas fa-user-graduate"></i> Students</button>
            <button class="tab" data-tab="admins"><i class="fas fa-user-tie"></i> Admins</button>
            <button class="tab" data-tab="security"><i class="fas fa-user-shield"></i> Security</button>
        </div>

        <div id="students-content" class="tab-content active">
            
            <div id="student-list-view">
                <div class="controls" id="student-controls">
                    <div class="main-controls-wrapper">
                        <div class="left-control-group">
                             <div class="search-field-group">
                                 <i class="fas fa-search"></i>
                                <input type="text" id="student-search-input" name="search" placeholder="Search by Student Number or Name..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            </div>
                            <div class="filter-dropdown">
                                <button class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
                                <div class="filter-content">
                                    <form method="GET" action="" id="student-filter-form">
                                        <input type="hidden" name="tab" value="students">
                                        <select name="course" onchange="this.form.submit()"><option value="">All Courses</option>
                                            <?php
                                            foreach ($courseOptions as $row) {
                                                $selected = isset($_GET['course']) && $_GET['course'] == $row['course_id'] ? 'selected' : '';
                                                echo "<option value='{$row['course_id']}' $selected>" . htmlspecialchars($row['course_name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                        <select name="year" onchange="this.form.submit()"><option value="">All Years</option>
                                            <?php
                                            foreach ($yearOptions as $row) {
                                                $selected = isset($_GET['year']) && $_GET['year'] == $row['year_id'] ? 'selected' : '';
                                                echo "<option value='{$row['year_id']}' $selected>" . htmlspecialchars($row['year']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                        <select name="section" onchange="this.form.submit()"><option value="">All Sections</option>
                                            <?php
                                            foreach ($sectionOptions as $row) {
                                                $selected = isset($_GET['section']) && $_GET['section'] == $row['section_id'] ? 'selected' : '';
                                                echo "<option value='{$row['section_id']}' $selected>" . htmlspecialchars($row['section_name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                        <select name="status" onchange="this.form.submit()"><option value="">All Statuses</option>
                                            <?php
                                            foreach ($statusOptions as $row) {
                                                $selected = isset($_GET['status']) && $_GET['status'] == $row['status_id'] ? 'selected' : '';
                                                echo "<option value='{$row['status_id']}' $selected>" . htmlspecialchars($row['status_name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="right-control-group">
                            <button type="button" id="open-import-student-modal-btn" class="control-button secondary"><i class="fas fa-file-import"></i> Import</button>
                            <button type="button" id="toggle-student-history-btn" class="control-button secondary"><i class="fas fa-history"></i> History</button>
                            <button type="button" id="refresh-student-list-btn" class="control-button secondary"><i class="fas fa-sync-alt"></i> Refresh</button>
                            <button type="button" id="open-add-student-modal-btn" class="control-button primary"><i class="fas fa-plus"></i> Add Student</button>
                        </div>
                    </div>
                </div>
                <table id="student-table">
                    <thead><tr><th>Student Number</th><th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Email</th><th>Course</th><th>Year</th><th>Section</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php
                        if ($conn) {
                            $search_student = isset($_GET['search']) && (!isset($_GET['tab']) || $_GET['tab'] === 'students') ? mysqli_real_escape_string($conn, $_GET['search']) : '';
                            $course_id_filter = isset($_GET['course']) ? mysqli_real_escape_string($conn, $_GET['course']) : '';
                            $year_id_filter = isset($_GET['year']) ? mysqli_real_escape_string($conn, $_GET['year']) : '';
                            $section_id_filter = isset($_GET['section']) ? mysqli_real_escape_string($conn, $_GET['section']) : '';
                            $status_id_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

                            $total_students = getTotalStudents($conn, $search_student, $course_id_filter, $year_id_filter, $section_id_filter, $status_id_filter);
                            $total_pages = ceil($total_students / $limit);

                            $query = "SELECT u.student_number, u.last_name, u.first_name, u.middle_name, u.email, u.course_id, c.course_name, u.year_id, y.year, u.section_id, s.section_name, u.status_id, st.status_name, u.new_until FROM users_tbl u LEFT JOIN course_tbl c ON u.course_id = c.course_id LEFT JOIN year_tbl y ON u.year_id = y.year_id LEFT JOIN section_tbl s ON u.section_id = s.section_id LEFT JOIN status_tbl st ON u.status_id = st.status_id WHERE 1";
                            if (!empty($search_student)) { $query .= " AND (u.student_number LIKE '%$search_student%' OR u.first_name LIKE '%$search_student%' OR u.last_name LIKE '%$search_student%')"; }
                            if (!empty($course_id_filter)) { $query .= " AND u.course_id = '$course_id_filter'"; }
                            if (!empty($year_id_filter)) { $query .= " AND u.year_id = '$year_id_filter'"; }
                            if (!empty($section_id_filter)) { $query .= " AND u.section_id = '$section_id_filter'"; }
                            if (!empty($status_id_filter)) { $query .= " AND u.status_id = '$status_id_filter'"; }
                            $query .= " ORDER BY u.last_name ASC, u.first_name ASC LIMIT $limit OFFSET $offset";
                            $result = mysqli_query($conn, $query);

                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $student_data_json = htmlspecialchars(json_encode([
                                        'student_number' => $row['student_number'],
                                        'first_name' => $row['first_name'],
                                        'middle_name' => $row['middle_name'],
                                        'last_name' => $row['last_name'],
                                        'email' => $row['email'],
                                        'course_id' => $row['course_id'],
                                        'year_id' => $row['year_id'],
                                        'section_id' => $row['section_id'],
                                        'status_id' => $row['status_id']
                                    ]), ENT_QUOTES, 'UTF-8');
                                    $status_class = strtolower(htmlspecialchars($row['status_name'])) == 'active' ? 'status-active' : 'status-inactive';

                                    $new_badge = '';
                                    if (!empty($row['new_until'])) {
                                        $new_until_timestamp = strtotime($row['new_until']);
                                        if (time() < $new_until_timestamp) {
                                            $new_badge = "<span class='new-badge' data-new-until='{$new_until_timestamp}'>NEW</span>";
                                        }
                                    }

                                    echo "<tr>";
                                    echo "<td>".htmlspecialchars($row['student_number'])." ".$new_badge."</td>";
                                    echo "<td>".htmlspecialchars($row['last_name'])."</td>";
                                    echo "<td>".htmlspecialchars($row['first_name'])."</td>";
                                    echo "<td>".htmlspecialchars($row['middle_name'])."</td>";
                                    echo "<td>".htmlspecialchars($row['email'])."</td>";
                                    echo "<td>".htmlspecialchars($row['course_name'])."</td>";
                                    echo "<td>".htmlspecialchars($row['year'])."</td>";
                                    echo "<td>".htmlspecialchars($row['section_name'])."</td>";
                                    echo "<td><span class='status-badge ".$status_class."'>".htmlspecialchars($row['status_name'])."</span></td>";
                                    echo "<td><div class='table-action-buttons'><button class='edit-btn student-edit-btn' data-student='".$student_data_json."'><i class='fas fa-pencil-alt'></i> Edit</button><button type='button' class='delete-btn student-delete-trigger-btn' data-id='".htmlspecialchars($row['student_number'])."' data-name='".htmlspecialchars($row['first_name'])." ".htmlspecialchars($row['last_name'])."' data-type='student'><i class='fas fa-trash-alt'></i> Delete</button></div></td>";
                                    echo "</tr>";
                                }
                            } else { echo "<tr><td colspan='10'>No student data available</td></tr>"; }
                        } else { echo "<tr><td colspan='10'>Database connection error.</td></tr>"; }
                        ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-controls">
                        <a href="?tab=students&page=<?php echo max(1, $current_page - 1); ?>&course=<?php echo htmlspecialchars($_GET['course'] ?? ''); ?>&year=<?php echo htmlspecialchars($_GET['year'] ?? ''); ?>&section=<?php echo htmlspecialchars($_GET['section'] ?? ''); ?>&status=<?php echo htmlspecialchars($_GET['status'] ?? ''); ?>&search=<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" class="pagination-btn <?php echo ($current_page == 1) ? 'disabled' : ''; ?>"><i class="fas fa-angle-left"></i> Previous</a>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <a href="?tab=students&page=<?php echo $p; ?>&course=<?php echo htmlspecialchars($_GET['course'] ?? ''); ?>&year=<?php echo htmlspecialchars($_GET['year'] ?? ''); ?>&section=<?php echo htmlspecialchars($_GET['section'] ?? ''); ?>&status=<?php echo htmlspecialchars($_GET['status'] ?? ''); ?>&search=<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" class="pagination-btn pagination-page-btn <?php echo ($p == $current_page) ? 'active' : ''; ?>"><?php echo $p; ?></a>
                        <?php endfor; ?>
                        <a href="?tab=students&page=<?php echo min($total_pages, $current_page + 1); ?>&course=<?php echo htmlspecialchars($_GET['course'] ?? ''); ?>&year=<?php echo htmlspecialchars($_GET['year'] ?? ''); ?>&section=<?php echo htmlspecialchars($_GET['section'] ?? ''); ?>&status=<?php echo htmlspecialchars($_GET['status'] ?? ''); ?>&search=<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" class="pagination-btn <?php echo ($current_page == $total_pages) ? 'disabled' : ''; ?>">Next <i class="fas fa-angle-right"></i></a>
                    </div>
                <?php endif; ?>
            </div>
            <div id="student-history-view" style="display: none;">
                <div class="controls"><div class="main-controls-wrapper"><div class="left-control-group"><h3 style="margin: 0;">Student Action History</h3></div><div class="right-control-group"><button type="button" id="back-to-student-list-btn" class="control-button secondary"><i class="fas fa-arrow-left"></i> Back to Student List</button></div></div></div>
                <table id="student-history-table">
                    <thead><tr><th>Timestamp</th><th>Performed By</th><th>Action</th><th>Target Student</th><th>Details</th></tr></thead>
                    <tbody>
                        <?php
                        if ($conn) {
                            $historyQuery = "SELECT timestamp, performed_by_admin_name, action_type, target_user_identifier, details FROM user_management_history WHERE target_user_type = 'Student' ORDER BY timestamp DESC LIMIT 200";
                            $historyResult = mysqli_query($conn, $historyQuery);
                            if ($historyResult && mysqli_num_rows($historyResult) > 0) {
                                while ($historyRow = mysqli_fetch_assoc($historyResult)) {
                                    $action_class = ''; $action_type = strtolower($historyRow['action_type']);
                                    if (strpos($action_type, 'add') !== false || strpos($action_type, 'import') !== false) { $action_class = 'action-add'; } elseif (strpos($action_type, 'edit') !== false) { $action_class = 'action-edit'; } elseif (strpos($action_type, 'delete') !== false || strpos($action_type, 'deactivate') !== false) { $action_class = 'action-delete'; }
                                    echo "<tr><td>".htmlspecialchars(date('M d, Y, h:i A', strtotime($historyRow['timestamp'])))."</td><td>".htmlspecialchars($historyRow['performed_by_admin_name'])."</td><td><span class='action-badge ".$action_class."'>".htmlspecialchars($historyRow['action_type'])."</span></td><td>".htmlspecialchars($historyRow['target_user_identifier'])."</td><td class='history-details'>".$historyRow['details']."</td></tr>";
                                }
                            } else { echo "<tr><td colspan='5'>No history found for students.</td></tr>"; }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="admins-content" class="tab-content">
            <div id="admin-list-view">
                <div class="controls" id="admin-controls">
                    <div class="main-controls-wrapper">
                        <div class="left-control-group">
                           <form method="GET" action="" id="admin-filter-form">
                               <input type="hidden" name="tab" value="admins">
                               <div class="search-field-group">
                                   <i class="fas fa-search"></i>
                                   <input type="text" name="admin_search" placeholder="Search by Name or Email" value="<?php echo isset($_GET['admin_search']) && isset($_GET['tab']) && $_GET['tab'] === 'admins' ? htmlspecialchars($_GET['admin_search']) : ''; ?>">
                               </div>
                           </form>
                        </div>
                        <div class="right-control-group">
                            <button type="button" id="open-import-admin-modal-btn" class="control-button secondary"><i class="fas fa-file-import"></i> Import Admins</button>
                            <button type="button" id="toggle-admin-history-btn" class="control-button secondary"><i class="fas fa-history"></i> View History</button>
                            <button type="button" id="refresh-admin-list-btn" class="control-button secondary"><i class="fas fa-sync-alt"></i> Refresh List</button>
                            <button type="button" id="open-add-admin-modal-btn" class="control-button primary"><i class="fas fa-plus"></i> Add Admin</button>
                        </div>
                    </div>
                </div>
                <table id="admin-table">
                    <thead><tr><th>Position</th><th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Email</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php
                        if ($conn) {
                            $admin_search = isset($_GET['admin_search']) && isset($_GET['tab']) && $_GET['tab'] === 'admins' ? mysqli_real_escape_string($conn, $_GET['admin_search']) : '';
                            $adminQuery = "SELECT ai.admin_id, ai.firstname, ai.middlename, ai.lastname, ai.Position, a.username, a.email, ai.status_id, st.status_name FROM admin_info_tbl ai JOIN admins a ON ai.admin_id = a.id LEFT JOIN status_tbl st ON ai.status_id = st.status_id WHERE 1";
                            if (!empty($admin_search)) { $adminQuery .= " AND (ai.firstname LIKE '%$admin_search%' OR ai.middlename LIKE '%$admin_search%' OR ai.lastname LIKE '%$admin_search%' OR a.email LIKE '%$admin_search%' OR ai.Position LIKE '%$admin_search%')"; }
                            $adminQuery .= " ORDER BY ai.lastname ASC, ai.firstname ASC";
                            $adminResult = mysqli_query($conn, $adminQuery);
                            if ($adminResult && mysqli_num_rows($adminResult) > 0) {
                                while ($adminRow = mysqli_fetch_assoc($adminResult)) {
                                    $adminFullName = htmlspecialchars($adminRow['firstname'] . ' ' . $adminRow['lastname']);
                                    $admin_data_json = htmlspecialchars(json_encode(['admin_id' => $adminRow['admin_id'],'first_name' => $adminRow['firstname'],'middle_name' => $adminRow['middlename'],'last_name' => $adminRow['lastname'],'position' => $adminRow['Position'],'username' => $adminRow['username'],'email' => $adminRow['email'],'status_id' => $adminRow['status_id']]), ENT_QUOTES, 'UTF-8');
                                    $status_class = strtolower(htmlspecialchars($adminRow['status_name'] ?? '')) == 'active' ? 'status-active' : 'status-inactive';
                                    echo "<tr><td>".htmlspecialchars($adminRow['Position'])."</td><td>".htmlspecialchars($adminRow['lastname'])."</td><td>".htmlspecialchars($adminRow['firstname'])."</td><td>".htmlspecialchars($adminRow['middlename'])."</td><td>".htmlspecialchars($adminRow['email'])."</td><td><span class='status-badge ".$status_class."'>".htmlspecialchars($adminRow['status_name'] ?? 'Unknown')."</span></td><td><div class='table-action-buttons'><button class='edit-btn admin-edit-btn' data-admin='".$admin_data_json."'><i class='fas fa-pencil-alt'></i> Edit</button><button type='button' class='delete-btn admin-delete-trigger-btn' data-id='".htmlspecialchars($adminRow['admin_id'])."' data-name='".$adminFullName."' data-type='admin'><i class='fas fa-trash-alt'></i> Delete</button></div></td></tr>";
                                }
                            } else { echo "<tr><td colspan='7'>No admin data available</td></tr>"; }
                        } else { echo "<tr><td colspan='7'>Database connection error.</td></tr>"; }
                        ?>
                    </tbody>
                </table>
            </div>
            <div id="admin-history-view" style="display: none;">
                 <div class="controls"><div class="main-controls-wrapper"><div class="left-control-group"><h3 style="margin: 0;">Admin Action History</h3></div><div class="right-control-group"><button type="button" id="back-to-admin-list-btn" class="control-button secondary"><i class="fas fa-arrow-left"></i> Back to Admin List</button></div></div></div>
                <table id="admin-history-table">
                    <thead><tr><th>Timestamp</th><th>Performed By</th><th>Action</th><th>Target Admin</th><th>Details</th></tr></thead>
                    <tbody>
                        <?php
                        if ($conn) {
                            $historyQuery = "SELECT timestamp, performed_by_admin_name, action_type, target_user_identifier, details FROM user_management_history WHERE target_user_type = 'Admin' ORDER BY timestamp DESC LIMIT 200";
                            $historyResult = mysqli_query($conn, $historyQuery);
                            if ($historyResult && mysqli_num_rows($historyResult) > 0) {
                                while ($historyRow = mysqli_fetch_assoc($historyResult)) {
                                    $action_class = ''; $action_type = strtolower($historyRow['action_type']);
                                    if (strpos($action_type, 'add') !== false) { $action_class = 'action-add'; } elseif (strpos($action_type, 'edit') !== false) { $action_class = 'action-edit'; } elseif (strpos($action_type, 'delete') !== false) { $action_class = 'action-delete'; }
                                    echo "<tr><td>".htmlspecialchars(date('M d, Y, h:i A', strtotime($historyRow['timestamp'])))."</td><td>".htmlspecialchars($historyRow['performed_by_admin_name'])."</td><td><span class='action-badge ".$action_class."'>".htmlspecialchars($historyRow['action_type'])."</span></td><td>".htmlspecialchars($historyRow['target_user_identifier'])."</td><td class='history-details'>".$historyRow['details']."</td></tr>";
                                }
                            } else { echo "<tr><td colspan='5'>No history found for admins.</td></tr>"; }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="security-content" class="tab-content">
            <div id="security-list-view">
                <div class="controls" id="security-controls">
                    <div class="main-controls-wrapper">
                        <div class="left-control-group">
                           <form method="GET" action="" id="security-filter-form">
                               <input type="hidden" name="tab" value="security">
                               <div class="search-field-group">
                                    <i class="fas fa-search"></i>
                                   <input type="text" name="security_search" placeholder="Search by Name or Email" value="<?php echo isset($_GET['security_search']) && isset($_GET['tab']) && $_GET['tab'] === 'security' ? htmlspecialchars($_GET['security_search']) : ''; ?>">
                               </div>
                           </form>
                        </div>
                        <div class="right-control-group">
                             <button type="button" id="open-import-security-modal-btn" class="control-button secondary"><i class="fas fa-file-import"></i> Import Security</button>
                            <button type="button" id="toggle-security-history-btn" class="control-button secondary"><i class="fas fa-history"></i> View History</button>
                            <button type="button" id="refresh-security-list-btn" class="control-button secondary"><i class="fas fa-sync-alt"></i> Refresh List</button>
                            <button type="button" id="open-add-security-modal-btn" class="control-button primary"><i class="fas fa-plus"></i> Add Security</button>
                        </div>
                    </div>
                </div>
                <table id="security-table">
                    <thead><tr><th>Position</th><th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Email</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php
                        if ($conn) {
                            $security_search = isset($_GET['security_search']) && isset($_GET['tab']) && $_GET['tab'] === 'security' ? mysqli_real_escape_string($conn, $_GET['security_search']) : '';
                            $securityQuery = "SELECT si.security_id, si.firstname, si.middlename, si.lastname, si.Position, s.email, si.status_id, st.status_name FROM security_info si JOIN security s ON si.security_id = s.id LEFT JOIN status_tbl st ON si.status_id = st.status_id WHERE 1";
                            if (!empty($security_search)) { $securityQuery .= " AND (si.firstname LIKE '%$security_search%' OR si.middlename LIKE '%$security_search%' OR si.lastname LIKE '%$security_search%' OR s.email LIKE '%$security_search%' OR si.Position LIKE '%$security_search%')"; }
                            $securityQuery .= " ORDER BY si.lastname ASC, si.firstname ASC";
                            $securityResult = mysqli_query($conn, $securityQuery);
                            if ($securityResult && mysqli_num_rows($securityResult) > 0) {
                                while ($securityRow = mysqli_fetch_assoc($securityResult)) {
                                    $securityFullName = htmlspecialchars($securityRow['firstname'] . ' ' . $securityRow['lastname']);
                                    $security_data_json = htmlspecialchars(json_encode(['security_id' => $securityRow['security_id'],'first_name' => $securityRow['firstname'],'middle_name' => $securityRow['middlename'],'last_name' => $securityRow['lastname'],'position' => $securityRow['Position'], 'email' => $securityRow['email'],'status_id' => $securityRow['status_id']]), ENT_QUOTES, 'UTF-8');
                                    $status_class = strtolower(htmlspecialchars($securityRow['status_name'] ?? '')) == 'active' ? 'status-active' : 'status-inactive';
                                    echo "<tr><td>".htmlspecialchars($securityRow['Position'])."</td><td>".htmlspecialchars($securityRow['lastname'])."</td><td>".htmlspecialchars($securityRow['firstname'])."</td><td>".htmlspecialchars($securityRow['middlename'])."</td><td>".htmlspecialchars($securityRow['email'])."</td><td><span class='status-badge ".$status_class."'>".htmlspecialchars($securityRow['status_name'] ?? 'Unknown')."</span></td><td><div class='table-action-buttons'><button class='edit-btn security-edit-btn' data-security='".$security_data_json."'><i class='fas fa-pencil-alt'></i> Edit</button><button type='button' class='delete-btn security-delete-trigger-btn' data-id='".htmlspecialchars($securityRow['security_id'])."' data-name='".$securityFullName."' data-type='security'><i class='fas fa-trash-alt'></i> Delete</button></div></td></tr>";
                                }
                            } else { echo "<tr><td colspan='7'>No security data available</td></tr>"; }
                        } else { echo "<tr><td colspan='7'>Database connection error.</td></tr>"; }
                        ?>
                    </tbody>
                </table>
            </div>
            <div id="security-history-view" style="display: none;">
                 <div class="controls"><div class="main-controls-wrapper"><div class="left-control-group"><h3 style="margin: 0;">Security Action History</h3></div><div class="right-control-group"><button type="button" id="back-to-security-list-btn" class="control-button secondary"><i class="fas fa-arrow-left"></i> Back to Security List</button></div></div></div>
                <table id="security-history-table">
                    <thead><tr><th>Timestamp</th><th>Performed By</th><th>Action</th><th>Target Security</th><th>Details</th></tr></thead>
                    <tbody>
                        <?php
                        if ($conn) {
                            $historyQuery = "SELECT timestamp, performed_by_admin_name, action_type, target_user_identifier, details FROM user_management_history WHERE target_user_type = 'Security' ORDER BY timestamp DESC LIMIT 200";
                            $historyResult = mysqli_query($conn, $historyQuery);
                            if ($historyResult && mysqli_num_rows($historyResult) > 0) {
                                while ($historyRow = mysqli_fetch_assoc($historyResult)) {
                                    $action_class = ''; $action_type = strtolower($historyRow['action_type']);
                                    if (strpos($action_type, 'add') !== false) { $action_class = 'action-add'; } elseif (strpos($action_type, 'edit') !== false) { $action_class = 'action-edit'; } elseif (strpos($action_type, 'delete') !== false) { $action_class = 'action-delete'; }
                                    echo "<tr><td>".htmlspecialchars(date('M d, Y, h:i A', strtotime($historyRow['timestamp'])))."</td><td>".htmlspecialchars($historyRow['performed_by_admin_name'])."</td><td><span class='action-badge ".$action_class."'>".htmlspecialchars($historyRow['action_type'])."</span></td><td>".htmlspecialchars($historyRow['target_user_identifier'])."</td><td class='history-details'>".$historyRow['details']."</td></tr>";
                                }
                            } else { echo "<tr><td colspan='5'>No history found for security.</td></tr>"; }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="edit-student-modal" class="modal"><div class="modal-content"><span class="close-modal">&times;</span><h3>Edit Student</h3><form id="edit-student-form"><input type="hidden" id="original-student-number" name="original_student_number"><div><label>Student Number:</label><input type="text" id="edit-student-number" name="student_number" required></div><div><label>First Name:</label><input type="text" id="edit-student-first-name" name="first_name" required></div><div><label>Middle Name:</label><input type="text" id="edit-student-middle-name" name="middle_name"></div><div><label>Last Name:</label><input type="text" id="edit-student-last-name" name="last_name" required></div><div><label>Email:</label><input type="email" id="edit-student-email" name="email" required></div><div><label>Course:</label><select id="edit-student-course" name="course_id" required><option value="">Select Course</option><?php foreach ($courseOptions as $row) { echo "<option value='{$row['course_id']}'>".htmlspecialchars($row['course_name'])."</option>"; } ?></select></div><div><label>Year:</label><select id="edit-student-year" name="year_id" required><option value="">Select Year</option><?php foreach ($yearOptions as $row) { echo "<option value='{$row['year_id']}'>".htmlspecialchars($row['year'])."</option>"; } ?></select></div><div><label>Section:</label><select id="edit-student-section" name="section_id" required><option value="">Select Section</option><?php foreach ($sectionOptions as $row) { echo "<option value='{$row['section_id']}'>".htmlspecialchars($row['section_name'])."</option>"; } ?></select></div><div><label>Status:</label><select id="edit-student-status" name="status_id" required><option value="">Select Status</option><?php foreach ($statusOptions as $row) { echo "<option value='{$row['status_id']}'>".htmlspecialchars($row['status_name'])."</option>"; } ?></select></div><button type="submit"><i class="fas fa-save"></i> Update Student</button></form></div></div>
    <div id="add-student-modal" class="modal"><div class="modal-content"><span class="close-modal">&times;</span><h3>Add New Student</h3><form id="add-student-form"><div><label>Student Number:</label><input type="text" name="student_number" required></div><div><label>First Name:</label><input type="text" name="first_name" required></div><div><label>Middle Name:</label><input type="text" name="middle_name"></div><div><label>Last Name:</label><input type="text" name="last_name" required></div><div><label>Email:</label><input type="email" name="email" required></div><div><label>Course:</label><select name="course_id" required><option value="">Select Course</option><?php foreach ($courseOptions as $row) { echo "<option value='{$row['course_id']}'>".htmlspecialchars($row['course_name'])."</option>"; } ?></select></div><div><label>Year:</label><select name="year_id" required><option value="">Select Year</option><?php foreach ($yearOptions as $row) { echo "<option value='{$row['year_id']}'>".htmlspecialchars($row['year'])."</option>"; } ?></select></div><div><label>Section:</label><select name="section_id" required><option value="">Select Section</option><?php foreach ($sectionOptions as $row) { echo "<option value='{$row['section_id']}'>".htmlspecialchars($row['section_name'])."</option>"; } ?></select></div><div><label>Status:</label><select name="status_id" required><option value="">Select Status</option><?php foreach ($statusOptions as $row) { echo "<option value='{$row['status_id']}'>".htmlspecialchars($row['status_name'])."</option>"; } ?></select></div><button type="submit"><i class="fas fa-plus"></i> Add Student</button></form></div></div>
    
    <div id="add-admin-modal" class="modal"><div class="modal-content"><span class="close-modal">&times;</span><h3>Add New Admin</h3><form id="add-admin-form"><div><label>First Name:</label><input type="text" name="first_name" required></div><div><label>Middle Name:</label><input type="text" name="middle_name"></div><div><label>Last Name:</label><input type="text" name="last_name" required></div><div><label>Email (will be used as Username):</label><input type="email" name="email" required></div><button type="submit"><i class="fas fa-plus"></i> Add Admin</button></form></div></div>
    <div id="edit-admin-modal" class="modal"><div class="modal-content"><span class="close-modal">&times;</span><h3>Edit Admin</h3><form id="edit-admin-form"><input type="hidden" id="edit-admin-id" name="admin_id"><div><label>First Name:</label><input type="text" id="edit-admin-first-name" name="first_name" required></div><div><label>Middle Name:</label><input type="text" id="edit-admin-middle-name" name="middle_name"></div><div><label>Last Name:</label><input type="text" id="edit-admin-last-name" name="last_name" required></div><div><label>Position:</label><input type="text" id="edit-admin-position" name="position" required></div><div><label>Email:</label><input type="email" id="edit-admin-email" name="email" required></div><div><label>New Password:</label><input type="password" id="edit-admin-password" name="password" placeholder="Leave blank to keep current password"></div><div><label>Status:</label><select id="edit-admin-status" name="status_id" required><option value="">Select Status</option><?php foreach ($statusOptions as $row) { echo "<option value='{$row['status_id']}'>".htmlspecialchars($row['status_name'])."</option>"; } ?></select></div><button type="submit"><i class="fas fa-save"></i> Update Admin</button></form></div></div>
    
    <div id="add-security-modal" class="modal"><div class="modal-content"><span class="close-modal">&times;</span><h3>Add New Security</h3><form id="add-security-form"><div><label>First Name:</label><input type="text" name="first_name" required></div><div><label>Middle Name:</label><input type="text" name="middle_name"></div><div><label>Last Name:</label><input type="text" name="last_name" required></div><div><label>Email (will be used as Username):</label><input type="email" name="email" required></div><button type="submit"><i class="fas fa-plus"></i> Add Security</button></form></div></div>
    <div id="edit-security-modal" class="modal"><div class="modal-content"><span class="close-modal">&times;</span><h3>Edit Security</h3><form id="edit-security-form"><input type="hidden" id="edit-security-id" name="security_id"><div><label>First Name:</label><input type="text" id="edit-security-first-name" name="first_name" required></div><div><label>Middle Name:</label><input type="text" id="edit-security-middle-name" name="middle_name"></div><div><label>Last Name:</label><input type="text" id="edit-security-last-name" name="last_name" required></div><div><label>Position:</label><input type="text" id="edit-security-position" name="position" required></div><div><label>Email:</label><input type="email" id="edit-security-email" name="email" required></div><div><label>New Password:</label><input type="password" id="edit-security-password" name="password" placeholder="Leave blank to keep current password"></div><div><label>Status:</label><select id="edit-security-status" name="status_id" required><option value="">Select Status</option><?php foreach ($statusOptions as $row) { echo "<option value='{$row['status_id']}'>".htmlspecialchars($row['status_name'])."</option>"; } ?></select></div><button type="submit"><i class="fas fa-save"></i> Update Security</button></form></div></div>

    <div id="import-student-modal" class="modal">
        <div class="modal-content import-modal-content">
            <span class="close-modal">&times;</span>
            <h3>Import Student Data</h3>
            <p>Choose an import action and upload your file.</p>
            <form id="import-student-form" enctype="multipart/form-data">
                <div class="import-type-container">
                    <label class="import-type-card">
                        <input type="radio" name="import_type" value="activate" checked>
                        <div class="card-content">
                            <i class="fas fa-user-plus"></i>
                            <h4>Import & Activate</h4>
                            <p>Upload a list of new students to create and activate their accounts.</p>
                            <button type="button" id="download-student-csv-template-btn" class="control-button template-button"><i class="fas fa-download"></i> Download Template</button>
                        </div>
                    </label>
                    <label class="import-type-card">
                        <input type="radio" name="import_type" value="deactivate">
                        <div class="card-content">
                             <i class="fas fa-user-slash"></i>
                            <h4>Deactivate Students</h4>
                            <p>Upload a list of student numbers to set their accounts to 'Inactive'.</p>
                            <button type="button" id="download-deactivate-csv-template-btn" class="control-button template-button"><i class="fas fa-download"></i> Download Template</button>
                        </div>
                    </label>
                </div>
                
                <div class="file-drop-area">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Drag & drop your CSV/XLSX file here, or click to select a file.</p>
                    <input type="file" id="student_csv_file" name="csv_file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                    <p id="file-name-display"></p>
                </div>
                
                <button type="submit" class="control-button primary import-submit-btn" id="import-student-submit-btn" disabled><i class="fas fa-upload"></i> Import Data</button>
            </form>
        </div>
    </div>


    <div id="import-admin-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Import Admins from CSV</h3>
            <p>Please upload a CSV file with admin data. The CSV should contain a header row with the following columns:</p>
            <ul>
                <li><strong><code>email</code></strong> (will be used as username and email; must be unique)</li>
                <li><strong><code>password</code></strong> (plain-text password; will be hashed)</li>
                <li><strong><code>first_name</code></strong></li>
                <li><strong><code>middle_name</code></strong> (optional)</li>
                <li><strong><code>last_name</code></strong></li>
            </ul>
            <p>All imported admins will be set to <strong>'Active' status'</strong> and <strong>'Admin' position/role'</strong> automatically.</p>
            <form id="import-admin-form" enctype="multipart/form-data">
                <div>
                    <label for="admin_csv_file">Upload CSV File:</label>
                    <input type="file" id="admin_csv_file" name="csv_file" accept=".csv" required>
                </div>
                <button type="submit"><i class="fas fa-upload"></i> Import Admins</button>
            </form>
        </div>
    </div>

    <div id="import-security-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Import Security Personnel from CSV</h3>
            <p>Please upload a CSV file with security data. The CSV should contain a header row with the following columns:</p>
            <ul>
                <li><strong><code>email</code></strong> (will be used as username and email; must be unique)</li>
                <li><strong><code>password</code></strong> (plain-text password; will be hashed)</li>
                <li><strong><code>first_name</code></strong></li>
                <li><strong><code>middle_name</code></strong> (optional)</li>
                <li><strong><code>last_name</code></strong></li>
            </ul>
            <p>All imported security personnel will be set to <strong>'Active' status'</strong> and <strong>'Security Guard' position/role'</strong> automatically.</p>
            <form id="import-security-form" enctype="multipart/form-data">
                <div>
                    <label for="security_csv_file">Upload CSV File:</label>
                    <input type="file" id="security_csv_file" name="csv_file" accept=".csv" required>
                </div>
                <button type="submit"><i class="fas fa-upload"></i> Import Security</button>
            </form>
        </div>
    </div>


    <div id="delete-confirm-modal" class="modal"><div class="modal-content"><span class="close-modal">&times;</span><h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3><p id="delete-confirm-text">Are you sure you want to delete <span id="delete-item-type-placeholder"></span>: <strong id="delete-item-identifier-placeholder"></strong>?</p><div class="delete-confirm-actions"><button type="button" id="confirm-delete-action-btn" class="delete-btn"><i class="fas fa-trash-alt"></i> Confirm Delete</button><button type="button" id="cancel-delete-action-btn" class="edit-btn"><i class="fas fa-times"></i> Cancel</button></div></div></div>
    <div id="custom-toast-notification" class="toast-notification"><span id="toast-notification-message"></span></div>
</main>
    <script src="./user_management.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const studentSearchInput = document.getElementById('student-search-input');
            const studentTableBody = document.querySelector('#student-table tbody');
            const paginationControls = document.querySelector('.pagination-controls');

            let debounceTimer;

            if (studentSearchInput) {
                studentSearchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        const searchTerm = studentSearchInput.value;
                        const urlParams = new URLSearchParams(window.location.search);
                        urlParams.set('search', searchTerm);
                        urlParams.set('page', '1'); 

                        const queryString = urlParams.toString();

                        fetch(`search_students_ajax.php?${queryString}`)
                            .then(response => response.text())
                            .then(html => {
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(html, 'text/html');
                                const newTbody = doc.querySelector('#student-table tbody');
                                const newPagination = doc.querySelector('.pagination-controls');

                                if (newTbody) {
                                    studentTableBody.innerHTML = newTbody.innerHTML;
                                } else {
                                    studentTableBody.innerHTML = '<tr><td colspan="10">Error loading data.</td></tr>';
                                }

                                if (paginationControls) {
                                    if (newPagination && newPagination.innerHTML.trim() !== '') {
                                        paginationControls.innerHTML = newPagination.innerHTML;
                                        paginationControls.style.display = 'flex';
                                    } else {
                                        paginationControls.innerHTML = '';
                                        paginationControls.style.display = 'none';
                                    }
                                }
                                
                                window.history.pushState({}, '', `?${queryString}`);

                            }).catch(error => {
                                console.error('Error fetching search results:', error);
                                studentTableBody.innerHTML = `<tr><td colspan="10">Failed to search. ${error}</td></tr>`;
                            });
                    }, 500);
                });
            }
        });
    </script>
    <script src="../JS/admin_header_script.js?v=<?php echo time(); ?>"></script>
</body>
</html>