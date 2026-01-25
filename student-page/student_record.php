<?php
require_once "../PHP/dbcon.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['action']) && $_POST['action'] == 'request_sanction') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Authentication failed.'];

    if (isset($_SESSION['user_student_number'], $_POST['violation_type_id']) && isset($conn)) {
        $student_number = $_SESSION['user_student_number'];
        $violation_type_id = $_POST['violation_type_id'];

        $check_stmt = $conn->prepare("SELECT request_id FROM sanction_requests_tbl WHERE student_number = ? AND violation_type_id = ? AND is_active = 1");
        $check_stmt->bind_param("si", $student_number, $violation_type_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $response['message'] = 'You already have a pending request for this violation.';
        } else {
            $conn->begin_transaction();
            try {
                $insert_stmt = $conn->prepare("INSERT INTO sanction_requests_tbl (student_number, violation_type_id) VALUES (?, ?)");
                $insert_stmt->bind_param("si", $student_number, $violation_type_id);
                $insert_stmt->execute();
                
                $student_name_stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users_tbl WHERE student_number = ?");
                $student_name_stmt->bind_param("s", $student_number);
                $student_name_stmt->execute();
                $name_result = $student_name_stmt->get_result()->fetch_assoc();
                $student_name = $name_result ? $name_result['full_name'] : $student_number;

                $notif_message = "Student " . $student_name . " has requested a sanction.";
                $notif_link = "../updated-admin-sanction/admin_sanction.php?tab=sanction-requests";
                $notif_stmt = $conn->prepare("INSERT INTO admin_notifications_tbl (message, link) VALUES (?, ?)");
                $notif_stmt->bind_param("ss", $notif_message, $notif_link);
                $notif_stmt->execute();

                $conn->commit();
                $response['success'] = true;
                $response['message'] = 'Sanction request sent successfully!';

            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = 'Error: Could not submit your request. ' . $e->getMessage();
            }
        }
        $check_stmt->close();
    } else {
        $response['message'] = 'Invalid request data. Please log in again.';
    }
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION["current_user_id"]) || !isset($_SESSION["user_student_number"])) {
    header("Location: student_login.php");
    exit();
}

$session_user_id = $_SESSION["current_user_id"];
$student_stud_number_from_session = $_SESSION["user_student_number"];

$unread_notifications_header = [];
$unread_notification_count_header = 0;

if (isset($conn)) {
    $sql_notifications_list_header = "SELECT notification_id, message, created_at, link FROM notifications_tbl WHERE student_number = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT 5";
    if ($stmt_notifications_list_header = $conn->prepare($sql_notifications_list_header)) {
        $stmt_notifications_list_header->bind_param("s", $student_stud_number_from_session);
        $stmt_notifications_list_header->execute();
        $result_notifications_list_header = $stmt_notifications_list_header->get_result();
        while ($row_notif_h = $result_notifications_list_header->fetch_assoc()) {
            $unread_notifications_header[] = $row_notif_h;
        }
        $stmt_notifications_list_header->close();
    }

    $sql_notifications_count_header = "SELECT COUNT(*) as total_unread FROM notifications_tbl WHERE student_number = ? AND is_read = FALSE";
    if ($stmt_notifications_count_header = $conn->prepare($sql_notifications_count_header)) {
        $stmt_notifications_count_header->bind_param("s", $student_stud_number_from_session);
        $stmt_notifications_count_header->execute();
        $result_count_h = $stmt_notifications_count_header->get_result()->fetch_assoc();
        $unread_notification_count_header = $result_count_h['total_unread'] ?? 0;
        $stmt_notifications_count_header->close();
    }
}

$student_details = null; 
$violations_log = [];
$violation_summary = [];
$sanction_records = []; 
$student_stud_number_for_page_violations = $student_stud_number_from_session;
$page_error = null;
$year_display = "N/A";

$sql_student_info = "SELECT u.first_name, u.middle_name, u.last_name, u.student_number, u.year_id, c.course_name, s.section_name
                       FROM users_tbl u
                       LEFT JOIN course_tbl c ON u.course_id = c.course_id
                       LEFT JOIN section_tbl s ON u.section_id = s.section_id
                       WHERE u.user_id = ?";

if (isset($conn) && $stmt_info = $conn->prepare($sql_student_info)) {
    $stmt_info->bind_param("i", $session_user_id);
    $stmt_info->execute();
    $result_info = $stmt_info->get_result();
    if ($result_info->num_rows == 1) {
        $student_details = $result_info->fetch_assoc();
        $_SESSION['user_full_name'] = ($student_details['first_name'] ?? '') . ' ' . ($student_details['last_name'] ?? '');
        if (isset($student_details['year_id']) && !empty($student_details['year_id'])) {
            $year_details_sql = "SELECT year FROM year_tbl WHERE year_id = ?";
            if($stmt_year = $conn->prepare($year_details_sql)){
                $stmt_year->bind_param("i", $student_details['year_id']);
                $stmt_year->execute();
                $year_res = $stmt_year->get_result();
                if($year_row = $year_res->fetch_assoc()){
                    $year_display = $year_row['year'];
                }
                $stmt_year->close();
            }
        }
    }
    $stmt_info->close();
}

$active_requests = [];
$pending_sanctions = [];
if (isset($conn) && !empty($student_stud_number_for_page_violations)) {
    $stmt_req = $conn->prepare("SELECT violation_type_id FROM sanction_requests_tbl WHERE student_number = ? AND is_active = 1");
    $stmt_req->bind_param("s", $student_stud_number_for_page_violations);
    $stmt_req->execute();
    $result_req = $stmt_req->get_result();
    while ($req_row = $result_req->fetch_assoc()) {
        $active_requests[] = $req_row['violation_type_id'];
    }
    $stmt_req->close();

    $stmt_pending = $conn->prepare("SELECT DISTINCT v.violation_type FROM student_sanction_records_tbl ssr JOIN violation_tbl v ON ssr.violation_id = v.violation_id WHERE ssr.student_number = ? AND ssr.status = 'Pending'");
    $stmt_pending->bind_param("s", $student_stud_number_for_page_violations);
    $stmt_pending->execute();
    $result_pending = $stmt_pending->get_result();
    while ($pending_row = $result_pending->fetch_assoc()) {
        $pending_sanctions[] = $pending_row['violation_type'];
    }
    $stmt_pending->close();
}

$all_sanctions = [];
if (isset($conn)) {
    $sanction_sql = "SELECT violation_type_id, offense_level, disciplinary_sanction FROM disciplinary_sanctions";
    if ($result_sanctions = $conn->query($sanction_sql)) {
        while ($row = $result_sanctions->fetch_assoc()) {
            $all_sanctions[$row['violation_type_id'] . '_' . $row['offense_level']] = $row['disciplinary_sanction'];
        }
    }
}

if (isset($conn) && !empty($student_stud_number_for_page_violations)) {
    $sql_summary_violations = "SELECT v.violation_id, vc.category_name, vt.violation_type, v.violation_date, v.description AS remarks, vt.violation_type_id
                                 FROM violation_tbl v
                                 JOIN violation_type_tbl vt ON v.violation_type = vt.violation_type_id
                                 LEFT JOIN violation_category_tbl vc ON vt.violation_category_id = vc.violation_category_id
                                 WHERE v.student_number = ?
                                 AND NOT EXISTS (
                                     SELECT 1 FROM student_sanction_records_tbl ssr
                                     WHERE ssr.violation_id = v.violation_id AND ssr.status = 'Completed'
                                 )
                                 ORDER BY v.violation_date DESC, vc.category_name ASC, vt.violation_type ASC";

    if ($stmt_summary = $conn->prepare($sql_summary_violations)) {
        $stmt_summary->bind_param("s", $student_stud_number_for_page_violations);
        $stmt_summary->execute();
        $result_summary = $stmt_summary->get_result();
        
        $temp_summary_data = [];
        while ($row = $result_summary->fetch_assoc()) {
            $violationTypeId = $row['violation_type_id'];
            $key = $violationTypeId;

            if (!isset($temp_summary_data[$key])) {
                $temp_summary_data[$key] = ['category' => $row['category_name'] ?? 'Uncategorized', 'type' => $row['violation_type'], 'violation_type_id' => $violationTypeId, 'count' => 0, 'remark_instances' => [] ];
            }
            $temp_summary_data[$key]['count']++;
            if (!empty(trim($row['remarks']))) {
                $temp_summary_data[$key]['remark_instances'][] = trim($row['remarks']);
            }
        }
        $stmt_summary->close();
        
        foreach($temp_summary_data as $key => $data_item){
            $count = $data_item['count'];
            $offense_level_str = match ($count) { 1 => '1st Offense', 2 => '2nd Offense', 3 => '3rd Offense', default => $count . 'th Offense', };
            $data_item['offense_level'] = $offense_level_str;

            if ($count > 1) { $data_item['remark_display'] = '(Multiple instances - see log)'; } 
            elseif (!empty($data_item['remark_instances'])) { $data_item['remark_display'] = $data_item['remark_instances'][0]; } 
            else { $data_item['remark_display'] = 'No remarks'; }

            $lookup_key = $data_item['violation_type_id'] . '_' . $offense_level_str;
            $sanction_text = $all_sanctions[$lookup_key] ?? 'N/A';
            $data_item['disciplinary_sanction'] = $sanction_text;
            
            $sanction_lower = strtolower($sanction_text);
            $is_warning = str_contains($sanction_lower, 'warning') || $sanction_text === 'N/A';
            $data_item['violation_status'] = $is_warning ? 'Warning' : 'Sanction';
            
            if (in_array($data_item['violation_type_id'], $active_requests)) {
                $data_item['workflow_status'] = 'Requested';
            } elseif (in_array($data_item['violation_type_id'], $pending_sanctions)) {
                $data_item['workflow_status'] = 'Pending';
            } else {
                $data_item['workflow_status'] = 'Actionable';
            }
            $violation_summary[] = $data_item;
        }
        
        usort($violation_summary, function($a, $b) {
            $catComp = strcmp($a['category'], $b['category']);
            if ($catComp == 0) return strcmp($a['type'], $b['type']);
            return $catComp;
        });
    }
}

if (isset($conn) && !empty($student_stud_number_for_page_violations)) {
    $sql_log_violations = "SELECT v.violation_id, vc.category_name, vt.violation_type, v.violation_date, v.description AS remarks
                             FROM violation_tbl v
                             JOIN violation_type_tbl vt ON v.violation_type = vt.violation_type_id
                             LEFT JOIN violation_category_tbl vc ON vt.violation_category_id = vc.violation_category_id
                             WHERE v.student_number = ?
                             ORDER BY v.violation_date DESC";
    if ($stmt_log = $conn->prepare($sql_log_violations)) {
        $stmt_log->bind_param("s", $student_stud_number_for_page_violations);
        $stmt_log->execute();
        $result_log = $stmt_log->get_result();
        while($log_row = $result_log->fetch_assoc()) {
            $violations_log[] = $log_row;
        }
        $stmt_log->close();
    }
}


if (isset($conn) && !empty($student_stud_number_for_page_violations)) {
    $sql_sanction_records = "SELECT ssr.record_id, ssr.date_assigned, ssr.status, ds.disciplinary_sanction, v.description AS violation_remarks, vt.violation_type AS violation_type_name
                               FROM student_sanction_records_tbl ssr
                               JOIN disciplinary_sanctions ds ON ssr.assigned_sanction_id = ds.disciplinary_sanction_id
                               LEFT JOIN violation_tbl v ON ssr.violation_id = v.violation_id
                               LEFT JOIN violation_type_tbl vt ON v.violation_type = vt.violation_type_id
                               WHERE ssr.student_number = ?
                               ORDER BY ssr.date_assigned DESC";
    
    if ($stmt_sanction_records = $conn->prepare($sql_sanction_records)) {
        $stmt_sanction_records->bind_param("s", $student_stud_number_for_page_violations);
        $stmt_sanction_records->execute();
        $result_sanction_records = $stmt_sanction_records->get_result();
        while ($row = $result_sanction_records->fetch_assoc()) {
            $sanction_records[] = $row;
        }
        $stmt_sanction_records->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Record</title>
    <link rel="stylesheet" href="./student_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .offense-tag-requested, .offense-tag-pending {
            background-color: #e6f7ff;
            color: #1890ff;
        }
        .btn-secondary:disabled {
            background-color: #f5f5f5;
            color: rgba(0, 0, 0, 0.25);
            border-color: #d9d9d9;
            cursor: not-allowed;
        }
        .btn i.fa-spinner { animation: fa-spin 1s infinite linear; }
        .notification-item a {
            text-decoration: none;
            color: inherit;
            display: block;
        }
    </style>
</head>
<body>
    
<header class="main-header">
    <div class="header-content">
        <div class="logo">
            <a href="./student_dashboard.php"><img src="../IMAGE/Tracker-logo.png" alt="PUP Logo"></a>
        </div>
        <nav class="main-nav" id="primary-navigation">
            <div class="nav-links">
                <a href="./student_dashboard.php">Home</a>
                <a href="./student_record.php" class="active-nav">Record</a>
                <a href="./student_announcements.php">Announcements</a>
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
                    </div>
                    <ul class="notification-list">
                         <?php if (!empty($unread_notifications_header)): ?>
                             <?php foreach ($unread_notifications_header as $notification_h): ?>
                                 <li class="notification-item">
                                     <a href="<?php echo htmlspecialchars($notification_h['link'] ?? '#'); ?>" data-notification-id="<?php echo htmlspecialchars($notification_h['notification_id']); ?>">
                                         <p class="notification-message"><?php echo htmlspecialchars($notification_h['message']); ?></p>
                                         <span class="notification-timestamp"><?php echo date("M d, Y, h:i a", strtotime($notification_h['created_at'])); ?></span>
                                     </a>
                                 </li>
                             <?php endforeach; ?>
                         <?php else: ?>
                             <li class="no-notifications">No new notifications.</li>
                         <?php endif; ?>
                    </ul>
                </div>
            </div>
            <a href="./student_account.php" class="profile-icon admin desktop-only">
                <svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </a>
        </div>
    </div>
</header>

<main>
    <div class="record-wrapper">
    <?php if ($student_details): ?>
        <h1 class="page-main-title">Student Record</h1>
        <div class="info-block">
            <p class="student-name"><?php echo htmlspecialchars($student_details['first_name'] ?? '') . " " . htmlspecialchars($student_details['middle_name'] ?? '') . " " . htmlspecialchars($student_details['last_name'] ?? ''); ?></p>
            <p class="meta-text"><strong>Student Number:</strong> <?php echo htmlspecialchars($student_details['student_number'] ?? ''); ?></p>
            <div class="meta-info-group">
                <p class="meta-text"><strong>Course:</strong> <span><?php echo htmlspecialchars($student_details['course_name'] ?? 'N/A'); ?></span></p>
                <p class="meta-text"><strong>Year:</strong> <span><?php echo htmlspecialchars($year_display); ?></span></p>
                <p class="meta-text"><strong>Section:</strong> <span><?php echo htmlspecialchars($student_details['section_name'] ?? 'N/A'); ?></span></p>
            </div>
        </div>

        <div class="tabs-navigation">
            <button class="tab-button active-tab-button" data-tab="violationRecordContent">Violation Record</button>
            <button class="tab-button" data-tab="sanctionRecordContent">Sanction Record</button>
        </div>

        <div id="violationRecordContent" class="tab-content active-tab">
            <div class="scrollable-tables-area">
                <h3 class="section-title">Summary by Violation</h3>
                <div class="table-container">
                    <table class="data-table" id="summaryViolationTable">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Violation Type</th>
                                <th>Offense Level</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Disciplinary Sanction</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($violation_summary)): ?>
                                <?php foreach ($violation_summary as $summary_item): ?>
                                    <tr>
                                        <td data-label="Category"><?php echo htmlspecialchars($summary_item['category']); ?></td>
                                        <td data-label="Violation Type"><?php echo htmlspecialchars($summary_item['type']); ?></td>
                                        <td data-label="Offense Level"><?php echo htmlspecialchars($summary_item['offense_level']); ?></td>
                                        <td data-label="Status">
                                            <span class="offense-tag <?php echo ($summary_item['violation_status'] == 'Sanction') ? 'offense-tag-sanction' : 'offense-tag-warning'; ?>">
                                                <?php echo htmlspecialchars($summary_item['violation_status']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Remarks"><?php echo nl2br(htmlspecialchars($summary_item['remark_display'])); ?></td>
                                        <td data-label="Disciplinary Sanction"><?php echo htmlspecialchars($summary_item['disciplinary_sanction']); ?></td>
                                        <td data-label="Action">
                                            <?php if ($summary_item['workflow_status'] === 'Requested'): ?>
                                                <button class="btn btn-secondary" disabled><i class="fas fa-clock"></i> Requested</button>
                                            <?php elseif ($summary_item['workflow_status'] === 'Pending'): ?>
                                                <button class="btn btn-secondary" disabled><i class="fas fa-hourglass-half"></i> Pending</button>
                                            <?php else: ?>
                                                <button class="btn btn-primary request-sanction-btn-row" 
                                                        data-violation-type-id="<?php echo htmlspecialchars($summary_item['violation_type_id']); ?>" 
                                                        <?php if ($summary_item['violation_status'] !== 'Sanction') echo 'disabled'; ?>>
                                                    Request Sanction
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="no-records-message">No outstanding violations found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <h3 class="section-title">Individual Violations Log</h3>
                <div class="table-container">
                    <table class="data-table" id="individualViolationsTable">
                        <thead>
                            <tr>
                                <th>Violation Type</th>
                                <th>Date of Violation</th>
                                <th>Category</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($violations_log)): ?>
                                <?php foreach ($violations_log as $record): ?>
                                    <tr>
                                        <td data-label="Violation Type"><?php echo htmlspecialchars($record['violation_type']); ?></td>
                                        <td data-label="Date"><?php echo htmlspecialchars(date("M d, Y, h:i a", strtotime($record['violation_date']))); ?></td>
                                        <td data-label="Category"><?php echo htmlspecialchars($record['category_name'] ?? 'N/A'); ?></td>
                                        <td data-label="Remarks"><?php echo nl2br(htmlspecialchars(trim($record['remarks'] ?? '') === '' ? 'No remarks' : $record['remarks'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="no-records-message">No individual violation records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="sanctionRecordContent" class="tab-content">
             <h3 class="section-title">Your Sanction Records</h3>
             <div class="table-container">
                 <table class="data-table" id="sanctionRecordsTable">
                     <thead>
                         <tr>
                             <th>Violation Type</th>
                             <th>Status</th>
                             <th>Date Assigned</th>
                             <th>Sanction Details</th>
                             <th>Violation Remarks</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php if (!empty($sanction_records)): ?>
                             <?php foreach ($sanction_records as $sanction): ?>
                                 <tr>
                                     <td data-label="Violation Type"><?php echo htmlspecialchars($sanction['violation_type_name'] ?? 'N/A'); ?></td>
                                     <td data-label="Status">
                                         <?php
                                         $statusClass = '';
                                         switch (strtolower($sanction['status'])) {
                                             case 'pending': $statusClass = 'offense-tag-warning'; break;
                                             case 'completed': $statusClass = 'offense-tag-completed'; break;
                                         }
                                         echo "<span class='offense-tag " . $statusClass . "'>" . htmlspecialchars($sanction['status']) . "</span>";
                                         ?>
                                     </td>
                                     <td data-label="Date Assigned"><?php echo htmlspecialchars(date("M d, Y, h:i a", strtotime($sanction['date_assigned']))); ?></td>
                                     <td data-label="Sanction Details"><?php echo nl2br(htmlspecialchars($sanction['disciplinary_sanction'] ?? 'N/A')); ?></td>
                                     <td data-label="Violation Remarks"><?php echo nl2br(htmlspecialchars(trim($sanction['violation_remarks'] ?? '') === '' ? 'No remarks' : $sanction['violation_remarks'])); ?></td>
                                 </tr>
                             <?php endforeach; ?>
                         <?php else: ?>
                             <tr>
                                 <td colspan="5" class="no-records-message">No sanction records found.</td>
                             </tr>
                         <?php endif; ?>
                     </tbody>
                 </table>
             </div>
           </div>
    <?php else: ?>
        <div class="info-block">
            <h2 class="student-name">Student Details Not Found</h2>
        </div>
    <?php endif; ?>
    </div>
</main>

<div id="confirmationOverlay" class="overlay-container" style="display:none;">
    <div class="dialog-content">
        <p id="confirmationMessageText"></p>
        <button id="closeOverlayButton" class="button-grey">Close</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const notificationToggle = document.getElementById('notificationLinkToggle');
    const notificationDropdown = document.getElementById('notificationsDropdownContent');

    if (notificationToggle && notificationDropdown) {
        notificationToggle.addEventListener('click', (event) => {
            event.preventDefault();
            notificationDropdown.classList.toggle('show');
        });

        window.addEventListener('click', (event) => {
            if (!notificationToggle.contains(event.target) && !notificationDropdown.contains(event.target)) {
                notificationDropdown.classList.remove('show');
            }
        });
    }

    const summaryTable = document.getElementById('summaryViolationTable');
    const overlay = document.getElementById('confirmationOverlay');
    const overlayMessage = document.getElementById('confirmationMessageText');
    const closeOverlayBtn = document.getElementById('closeOverlayButton');

    if (closeOverlayBtn) {
        closeOverlayBtn.addEventListener('click', () => {
            overlay.style.display = 'none';
        });
    }

    const showConfirmation = (message, isSuccess) => {
        if(overlay && overlayMessage) {
            overlayMessage.textContent = message;
            overlayMessage.style.color = isSuccess ? 'green' : 'red';
            overlay.style.display = 'flex';
        } else {
            alert(message);
        }
    };

    if (summaryTable) {
        summaryTable.addEventListener('click', async (e) => {
            const button = e.target.closest('.request-sanction-btn-row');

            if (!button || button.disabled) {
                return;
            }

            const violationTypeId = button.dataset.violationTypeId;
            if (!violationTypeId) {
                return;
            }
            
            const originalButtonText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            const formData = new FormData();
            formData.append('action', 'request_sanction');
            formData.append('violation_type_id', violationTypeId);

            try {
                const response = await fetch('student_record.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    window.location.reload();
                } else {
                    button.disabled = false;
                    button.innerHTML = originalButtonText;
                    showConfirmation(result.message || 'An unknown error occurred.', false);
                }

            } catch (error) {
                button.disabled = false;
                button.innerHTML = originalButtonText;
                showConfirmation('A network error occurred. Please try again.', false);
            }
        });
    }

    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            tabButtons.forEach(btn => btn.classList.remove('active-tab-button'));
            tabContents.forEach(content => content.classList.remove('active-tab'));

            button.classList.add('active-tab-button');
            document.getElementById(button.dataset.tab).classList.add('active-tab');
        });
    });
});
</script>

<?php if (isset($conn)) { $conn->close(); } ?>
</body>
</html>