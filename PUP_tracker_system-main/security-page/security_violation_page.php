<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include '../PHP/dbcon.php';

if (isset($_REQUEST['action']) && !isset($_REQUEST['generate_pdf'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    if ($_REQUEST['action'] == 'search_student_for_violation' && isset($_GET['student_search_number'])) {
        $studentNumber = trim($_GET['student_search_number']);
        $sql = "SELECT u.student_number, u.first_name, u.middle_name, u.last_name, 
                       COALESCE(c.course_name, 'N/A') as course_name, 
                       COALESCE(y.year, 'N/A') as year,
                       COALESCE(s.section_name, 'N/A') as section_name
                FROM users_tbl u
                LEFT JOIN course_tbl c ON u.course_id = c.course_id
                LEFT JOIN year_tbl y ON u.year_id = y.year_id
                LEFT JOIN section_tbl s ON u.section_id = s.section_id
                WHERE u.student_number = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $studentNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($student = $result->fetch_assoc()) {
            $response = ['success' => true, 'student' => $student];
        } else {
            $response['message'] = 'Student not found.';
        }
        $stmt->close();
    } elseif ($_REQUEST['action'] == 'get_violation_types_for_category' && isset($_GET['category_id'])) {
        $categoryId = $_GET['category_id'];
        $sql = "SELECT violation_type_id, violation_type FROM violation_type_tbl WHERE violation_category_id = ? ORDER BY violation_type";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();
        $types = $result->fetch_all(MYSQLI_ASSOC);
        $response = ['success' => true, 'types' => $types];
        $stmt->close();
    } elseif ($_SERVER["REQUEST_METHOD"] == "POST" && $_REQUEST['action'] == 'add_violation') {
        $studentNumber = trim($_POST['studentNumber'] ?? '');
        $violationTypeId = trim($_POST['violationType'] ?? '');
        $remarks = trim($_POST['violationRemarks'] ?? '');
        $recorder_id = $_SESSION['security_id'] ?? 0;

        if (empty($studentNumber) || empty($violationTypeId)) {
            $response['message'] = 'Student Number and Violation Type are required.';
        } else {
            $sql = "INSERT INTO violation_tbl (student_number, violation_type, description, violation_date, recorder_id) VALUES (?, ?, ?, NOW(), ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sisi", $studentNumber, $violationTypeId, $remarks, $recorder_id);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Violation added successfully!'];
                } else {
                    $response['message'] = 'Database error: Failed to add violation.';
                }
                $stmt->close();
            } else {
                $response['message'] = 'Database error: Failed to prepare statement.';
            }
        }
    } elseif ($_SERVER["REQUEST_METHOD"] == "POST" && $_REQUEST['action'] == 'filter_violations') {
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        list($html, $pagination_data) = generate_violation_table_body($conn, $page);
        
        $response['success'] = true;
        $response['message'] = 'Table updated.';
        $response['html'] = $html;
        $response['pagination'] = $pagination_data;
    }


    echo json_encode($response);
    $conn->close();
    exit();
}

function getIconForCategory($categoryName) {
    switch (strtoupper(trim($categoryName))) {
        case 'ACADEMIC INTEGRITY': return 'fas fa-graduation-cap';
        case 'ID VIOLATION': return 'fas fa-id-card';
        case 'DRESS CODE POLICY': return 'fas fa-user-tie';
        case 'EVENTS AND VISITORS': return 'fas fa-calendar-check';
        case 'STUDENT CONDUCT': return 'fas fa-gavel';
        case 'UNIVERSITY PROPERTY AND FACILITIES': return 'fas fa-building';
        default: return 'fas fa-exclamation-circle';
    }
}

function generate_violation_table_body($conn, $page = 1, $limit = 15) {
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $course = isset($_POST['course_id']) ? $_POST['course_id'] : '';
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : '';

    $where = [];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $where[] = "(u.student_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $searchTerm = "%{$search}%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm);
        $types .= 'sss';
    }
    if (!empty($course)) {
        $where[] = "u.course_id = ?";
        $params[] = $course;
        $types .= 'i';
    }
    if (!empty($startDate)) {
        $where[] = "DATE(v.violation_date) >= ?";
        $params[] = $startDate;
        $types .= 's';
    }
    if (!empty($endDate)) {
        $where[] = "DATE(v.violation_date) <= ?";
        $params[] = $endDate;
        $types .= 's';
    }
    
    $sql_count_base = "SELECT COUNT(DISTINCT u.student_number) as total
                       FROM users_tbl u
                       JOIN violation_tbl v ON u.student_number = v.student_number";
    $sql_count = $sql_count_base;
    if (!empty($where)) {
        $sql_count .= " WHERE " . implode(" AND ", $where);
    }
    
    $stmt_count = $conn->prepare($sql_count);
    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $count_result = $stmt_count->get_result()->fetch_assoc();
    $total_records = $count_result['total'] ?? 0;
    $total_pages = ceil($total_records / $limit);
    $stmt_count->close();
    
    $offset = ($page - 1) * $limit;

    $sql_students = "SELECT DISTINCT u.student_number, u.first_name, u.middle_name, u.last_name, 
                                         c.course_name, y.year, s.section_name 
                                 FROM users_tbl u
                                 JOIN violation_tbl v ON u.student_number = v.student_number
                                 LEFT JOIN course_tbl c ON u.course_id = c.course_id
                                 LEFT JOIN year_tbl y ON u.year_id = y.year_id
                                 LEFT JOIN section_tbl s ON u.section_id = s.section_id";

    if (!empty($where)) {
        $sql_students .= " WHERE " . implode(" AND ", $where);
    }

    $sql_students .= " ORDER BY u.last_name ASC, u.first_name ASC LIMIT ? OFFSET ?";
    
    $params_with_pagination = $params;
    array_push($params_with_pagination, $limit, $offset);
    $types_with_pagination = $types . 'ii';
    
    $stmt_students = $conn->prepare($sql_students);
    if (!empty($params_with_pagination)) {
        $stmt_students->bind_param($types_with_pagination, ...$params_with_pagination);
    }
    
    $stmt_students->execute();
    $result_students = $stmt_students->get_result();
    $output = '';

    if ($result_students && $result_students->num_rows > 0) {
        $sanction_sql_check = "SELECT disciplinary_sanction FROM disciplinary_sanctions WHERE violation_type_id = ? AND offense_level = ?";
        $stmt_sanction_check = $conn->prepare($sanction_sql_check);
        
        $violations_sql_base = "SELECT vt.violation_type, v.violation_type as violation_type_id, vc.category_name, MAX(v.violation_date) as latest_date,
                                       (SELECT COUNT(*) FROM violation_tbl WHERE student_number = ? AND violation_type = v.violation_type) as offense_count,
                                       (SELECT v_inner.description FROM violation_tbl v_inner WHERE v_inner.student_number = v.student_number AND v_inner.violation_type = v.violation_type ORDER BY v_inner.violation_date DESC LIMIT 1) as latest_description
                                  FROM violation_tbl v
                                  JOIN violation_type_tbl vt ON v.violation_type = vt.violation_type_id
                                  JOIN violation_category_tbl vc ON vt.violation_category_id = vc.violation_category_id";

        while ($student_row = $result_students->fetch_assoc()) {
            $student_number = $student_row['student_number'];

            $violation_where = ["v.student_number = ?"];
            $violation_params = [$student_number, $student_number];
            $violation_types = "ss";

            if(!empty($startDate)){
                $violation_where[] = "DATE(v.violation_date) >= ?";
                $violation_params[] = $startDate;
                $violation_types .= "s";
            }
            if(!empty($endDate)){
                $violation_where[] = "DATE(v.violation_date) <= ?";
                $violation_params[] = $endDate;
                $violation_types .= "s";
            }
            $violations_sql = $violations_sql_base . " WHERE " . implode(" AND ", $violation_where) . " GROUP BY v.student_number, v.violation_type ORDER BY latest_date DESC";
            $stmt_violations = $conn->prepare($violations_sql);
            $stmt_violations->bind_param($violation_types, ...$violation_params);

            $stmt_violations->execute();
            $violations_result = $stmt_violations->get_result();
            $violations_data = $violations_result->fetch_all(MYSQLI_ASSOC);
            
            if (empty($violations_data)) continue;

            $total_violation_types = count($violations_data);
            $sanction_count = 0;
            $warning_count = 0;
            
            foreach ($violations_data as $v_check) {
                $oc = $v_check['offense_count'];
                $vt_id = $v_check['violation_type_id'];
                $ol_str = ($oc == 1) ? '1st Offense' : (($oc == 2) ? '2nd Offense' : (($oc == 3) ? '3rd Offense' : $oc . 'th Offense'));
                $stmt_sanction_check->bind_param("is", $vt_id, $ol_str);
                $stmt_sanction_check->execute();
                $res = $stmt_sanction_check->get_result();
                if ($s_row = $res->fetch_assoc()) {
                    if (!empty($s_row['disciplinary_sanction']) && stripos($s_row['disciplinary_sanction'], 'warning') === false) {
                        $sanction_count++;
                    } else { $warning_count++; }
                } else { $warning_count++; }
            }
            
            $student_id_safe = preg_replace('/[^a-zA-Z0-9_-]/', '-', $student_number);
            $group_border_class = $sanction_count > 0 ? 'group-border-sanction' : 'group-border-warning';
            $full_name = htmlspecialchars($student_row['first_name'] . ' ' . $student_row['last_name']);

            $output .= "<tr class='student-summary-row " . $group_border_class . "' data-target='details-for-{$student_id_safe}'>";
            
            $output .= "<td data-label='Student Number'><div class='mobile-card-header'><div class='student-id-group'><span class='card-title-name'>{$full_name}</span><span class='card-title-id'>{$student_row['student_number']}</span></div><div class='summary-badge-group'><span class='badge-pill status-sanction'>{$sanction_count}</span><span class='badge-pill status-warning'>{$warning_count}</span><i class='fas fa-chevron-right expand-icon'></i></div></div></td>";
            $output .= "<td data-label='First Name'>" . htmlspecialchars($student_row['first_name']) . "</td>";
            $output .= "<td data-label='Middle Name'>" . htmlspecialchars($student_row['middle_name'] ?? '') . "</td>";
            $output .= "<td data-label='Last Name'>" . htmlspecialchars($student_row['last_name']) . "</td>";
            $output .= "<td data-label='Course'>" . htmlspecialchars($student_row['course_name'] ?? 'N/A') . "</td>";
            $output .= "<td data-label='Year'>" . htmlspecialchars($student_row['year'] ?? 'N/A') . "</td>";
            $output .= "<td data-label='Section'>" . htmlspecialchars($student_row['section_name'] ?? 'N/A') . "</td>";
            $output .= "<td data-label='Violation Summary' class='text-center'>{$total_violation_types} Types <span class='badge-pill status-sanction summary-badge'>{$sanction_count}</span> <span class='badge-pill status-warning summary-badge'>{$warning_count}</span></td>";
            $output .= "</tr>";

            $output .= "<tr class='violation-detail-row' id='details-for-{$student_id_safe}'><td colspan='8' class='details-container-cell'><div class='details-wrapper'>";
            foreach ($violations_data as $violation_row) {
                $offense_count = $violation_row['offense_count'];
                $offense_level_display_str = ($offense_count == 1) ? '1st Offense' : (($offense_count == 2) ? '2nd Offense' : (($offense_count == 3) ? '3rd Offense' : $offense_count . 'th Offense'));
                $status_text = 'Warning'; $status_class = 'status-warning'; $status_icon = 'fa-exclamation-triangle';
                
                $stmt_sanction_check->bind_param("is", $violation_row['violation_type_id'], $offense_level_display_str);
                $stmt_sanction_check->execute();
                $sanction_result = $stmt_sanction_check->get_result();
                if ($sanction_row = $sanction_result->fetch_assoc()) {
                    if (!empty($sanction_row['disciplinary_sanction']) && stripos($sanction_row['disciplinary_sanction'], 'warning') === false) {
                        $status_text = 'Sanction'; $status_class = 'status-sanction'; $status_icon = 'fa-gavel';
                    }
                }
                
                $output .= "<div class='violation-entry'>";
                $output .= "<div class='violation-main'><span class='violation-type'><i class='" . getIconForCategory($violation_row['category_name']) . "'></i> " . htmlspecialchars($violation_row['violation_type'] ?? 'Unknown') . "</span><div class='violation-context'><span class='violation-date'><i class='fas fa-calendar-alt'></i> " . htmlspecialchars(date("F j, Y, g:i a", strtotime($violation_row['latest_date']))) . "</span>";
                $output .= "<span class='violation-remarks'>Remarks: " . (!empty($violation_row['latest_description']) ? htmlspecialchars($violation_row['latest_description']) : 'No remarks provided') . "</span></div></div>";
                $output .= "<div class='violation-actions'>";
                $output .= "<span class='badge-pill offense-level-badge'>" . htmlspecialchars($offense_level_display_str) . "</span>";
                $output .= "<span class='badge-pill " . $status_class . "'><i class='fas " . $status_icon . "'></i> " . htmlspecialchars($status_text) . "</span>";
                $output .= "<a href='security_violation_details.php?student_number=" . urlencode($student_number) . "' class='more-details-btn'><i class='fas fa-info-circle'></i> More Details</a>";
                $output .= "</div></div>";
            }
            $output .= "</div></td></tr>";
            $stmt_violations->close();
        }
        $stmt_sanction_check->close();
    } else {
        $output = "<tr><td colspan='8' class='no-records-cell'>No student violations match the current filters.</td></tr>";
    }
    $stmt_students->close();
    
    $pagination_data = [
        'currentPage' => $page,
        'totalPages' => $total_pages,
        'totalRecords' => $total_records
    ];

    return [$output, $pagination_data];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Security | Student Violations</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="security_violation.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div id="toast-notification" class="toast"></div>

    <div class="page-container" id="pageContainer">
        <aside class="side-menu">
            <div class="menu-header">
                <img src="../IMAGE/Tracker-logo.png" alt="PUP Logo" class="menu-logo">
                <button class="close-btn" id="closeMenuBtn">&times;</button>
            </div>
            <nav class="menu-nav">
                <a href="security_dashboard.php" class="nav-item"><i class="fas fa-chart-bar"></i> Dashboard</a>
                <a href="security_violation_page.php" class="nav-item active"><i class="fas fa-exclamation-triangle"></i> Violations</a>
                <a href="security_account.php" class="nav-item"><i class="fas fa-user-shield"></i> My Account</a>
                <a href="../PHP/logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <div class="page-wrapper" id="pageWrapper">
            <header class="main-header">
                <div class="header-content">
                    <div class="logo"><img src="../IMAGE/Tracker-logo.png" alt="PUP Logo"></div>
                    <nav class="main-nav">
                        <a href="security_dashboard.php">Dashboard</a>
                        <a href="security_violation_page.php" class="active-nav">Violations</a>
                    </nav>
                    <div class="user-icons">
                        <a href="notification.html" class="notification"><svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13.586V10c0-3.217-2.185-5.927-5.145-6.742C13.562 2.52 12.846 2 12 2s-1.562.52-1.855 1.258C7.185 4.073 5 6.783 5 10v3.586l-1.707 1.707A.996.996 0 0 0 3 16v2a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-2a.996.996 0 0 0-.293-.707L19 13.586zM19 17H5v-.586l1.707-1.707A.996.996 0 0 0 7 14v-4c0-2.757 2.243-5 5-5s5 2.243 5 5v4c0 .266.105.52.293.707L19 16.414V17zm-7 5a2.98 2.98 0 0 0 2.818-2H9.182A2.98 2.98 0 0 0 12 22z"/></svg></a>
                        <a href="security_account.php" class="admin-profile"><svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></a>
                    </div>
                    <button class="menu-toggle" id="openMenuBtn"><i class="fas fa-bars"></i></button>
                </div>
            </header>

            <main class="container">
                <div class="page-header">
                    <h1>Student Violation Records</h1>
                </div>
                
                <div class="mobile-filter-header">
                    <button type="button" id="toggleFilterBtn" class="filter-toggle-btn">
                        <span><i class="fas fa-filter"></i> Filter & Search</span>
                        <i class="fas fa-chevron-down filter-arrow"></i>
                    </button>
                </div>

                <div id="filterContainer" class="filter-container">
                    <form id="filter-form" class="filter-controls">
                        <select name="course_id" id="courseFilter">
                            <option value="">Filter by Course</option>
                            <?php
                                if ($conn) {
                                    $courseQuery = "SELECT course_id, course_name FROM course_tbl ORDER BY course_name ASC";
                                    $courseResult = $conn->query($courseQuery);
                                    if ($courseResult) {
                                        while ($row = $courseResult->fetch_assoc()) {
                                            echo "<option value='" . htmlspecialchars($row['course_id']) . "'>" . htmlspecialchars($row['course_name']) . "</option>";
                                        }
                                    } 
                                }
                            ?>
                        </select>
                        <div class="date-range-wrapper">
                            <i class="fas fa-calendar-alt"></i>
                            <input type="text" id="dateRangePicker" placeholder="Filter by Date Range">
                        </div>
                        <input type="hidden" name="start_date" id="startDateFilter">
                        <input type="hidden" name="end_date" id="endDateFilter">

                        <div class="search-filter-group">
                            <input type="text" name="search" id="searchFilter" placeholder="Search Student Number or Name...">
                        </div>
                        <div class="action-buttons-group">
                            <button type="button" id="refreshBtn" class="filter-btn refresh-btn"><i class="fas fa-sync-alt"></i> Refresh List</button>
                            <button type="button" id="generateReportBtn" class="filter-btn report-btn"><i class="fas fa-file-pdf"></i> Generate Report</button>
                            <button type="button" id="addViolationBtn" class="filter-btn add-btn"><i class="fas fa-plus"></i> Add Violation</button>
                        </div>
                    </form>
                </div>

                <div class="main-table-scroll-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Number</th>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Last Name</th>
                                <th>Course</th>
                                <th>Year</th>
                                <th>Section</th>
                                <th class="text-center">Violation Summary</th>
                            </tr>
                        </thead>
                        <tbody id="violationTableBody">
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container" id="paginationContainer">
                </div>
            </main>
        </div>
        <div class="overlay" id="overlay"></div>
    </div>
    
    <button type="button" id="fabAddViolation" class="fab" title="Add Violation">
        <i class="fas fa-plus"></i>
    </button>

    <div id="violationModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Student Violation</h2>
                <button id="closeModalBtn" class="close-modal-button">&times;</button>
            </div>
            <div id="modalBody" class="modal-body">
                <div id="modalMessage" class="modal-message-box" style="display: none;"></div>
                
                <div id="searchStep">
                    <label for="studentNumberSearchInput">Search Student Number:</label>
                    <div class="search-container">
                        <input type="text" id="studentNumberSearchInput" placeholder="Enter Student Number">
                        <button type="button" id="executeStudentSearchBtn" class="search-btn"><i class="fas fa-search"></i> Search</button>
                    </div>
                    <div id="searchLoadingIndicator" style="display:none; text-align:center; padding: 20px;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
                    <div id="studentSearchResultArea" style="display: none; margin-top: 1rem;"></div>
                </div>

                <form id="violationForm" style="display: none;">
                    <input type="hidden" name="action" value="add_violation">
                    <input type="hidden" id="studentNumber" name="studentNumber" />
                    <div id="confirmedStudentInfo" class="student-info-box static"></div>
                    
                    <label for="violationCategory">Violation Category:</label>
                    <select id="violationCategory" name="violationCategory" required>
                        <option value="">Select Violation Category</option>
                        <?php
                        if ($conn) {
                            $catSqlModal = "SELECT violation_category_id, category_name FROM violation_category_tbl ORDER BY category_name ASC";
                            $catResultModal = $conn->query($catSqlModal);
                            if ($catResultModal && $catResultModal->num_rows > 0) {
                                while ($catRowModal = $catResultModal->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($catRowModal['violation_category_id']) . '">' . htmlspecialchars($catRowModal['category_name']) . '</option>';
                                }
                            }
                        }
                        ?>
                    </select>
                    
                    <label for="violationType">Violation Type:</label>
                    <select id="violationType" name="violationType" required disabled>
                        <option value="">Select Category First</option>
                    </select>

                    <label for="violationRemarks">Remarks:</label>
                    <textarea id="violationRemarks" name="violationRemarks" rows="3" placeholder="Enter reason or details for the violation..."></textarea>
                    
                    <div class="modal-actions">
                        <button type="submit" id="submitViolationBtn" class="action-btn add-btn"><i class="fas fa-plus"></i> Add Violation</button>
                        <button type="button" id="changeStudentBtn" class="action-btn change-btn"><i class="fas fa-undo"></i> Change Student</button>
                        <button type="button" id="cancelFormBtn" class="action-btn cancel-btn"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="security_violation_page.js?v=<?php echo time(); ?>"></script>
</body>
</html>
<?php
if ($conn) {
    $conn->close();
}
?>