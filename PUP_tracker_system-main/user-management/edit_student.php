<?php
ob_start();
session_start();
require '../PHP/dbcon.php';
require_once './history_logger.php';

$response = ["success" => false, "error" => "An unknown error occurred."];

function get_lookup_name($conn, $table, $name_col, $id_col, $id) {
    if (empty($id) || empty($table)) return 'N/A';
    $stmt = $conn->prepare("SELECT $name_col FROM $table WHERE $id_col = ?");
    if (!$stmt) return 'Error';
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result[$name_col] ?? 'Unknown';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $original_student_number = trim($_POST['original_student_number'] ?? '');
    $student_number = trim($_POST['student_number'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course_id = (int)($_POST['course_id'] ?? 0);
    $year_id = (int)($_POST['year_id'] ?? 0);
    $section_id = (int)($_POST['section_id'] ?? 0);
    $status_id = (int)($_POST['status_id'] ?? 0);

    if (empty($original_student_number) || empty($student_number) || empty($first_name) || empty($last_name) || empty($email) || $course_id <= 0 || $year_id <= 0 || $section_id <= 0 || $status_id <= 0) {
        $response['error'] = "One or more required fields are missing or invalid.";
    } else {
        // Fetch old data for comparison before updating
        $old_data_stmt = $conn->prepare("SELECT * FROM users_tbl WHERE student_number = ?");
        $old_data_stmt->bind_param("s", $original_student_number);
        $old_data_stmt->execute();
        $old_data = $old_data_stmt->get_result()->fetch_assoc();
        $old_data_stmt->close();
        
        $updateQuery = "UPDATE users_tbl SET student_number = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?, course_id = ?, year_id = ?, section_id = ?, status_id = ? WHERE student_number = ?";
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("sssssiiiis", $student_number, $first_name, $middle_name, $last_name, $email, $course_id, $year_id, $section_id, $status_id, $original_student_number);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0 && $old_data) {
                $response["success"] = true;
                $response["message"] = "Student updated successfully.";
                unset($response["error"]);
                
                $details_array = [];
                if (($old_data['student_number'] ?? '') != $student_number) { $details_array[] = "<b>Student No:</b> '".htmlspecialchars($old_data['student_number'])."' → '".htmlspecialchars($student_number)."'"; }
                if (($old_data['first_name'] ?? '') != $first_name) { $details_array[] = "<b>First Name:</b> '".htmlspecialchars($old_data['first_name'])."' → '".htmlspecialchars($first_name)."'"; }
                if (($old_data['last_name'] ?? '') != $last_name) { $details_array[] = "<b>Last Name:</b> '".htmlspecialchars($old_data['last_name'])."' → '".htmlspecialchars($last_name)."'"; }
                if (($old_data['email'] ?? '') != $email) { $details_array[] = "<b>Email:</b> '".htmlspecialchars($old_data['email'])."' → '".htmlspecialchars($email)."'"; }
                
                if (($old_data['course_id'] ?? 0) != $course_id) {
                    $old_name = get_lookup_name($conn, 'course_tbl', 'course_name', 'course_id', $old_data['course_id']);
                    $new_name = get_lookup_name($conn, 'course_tbl', 'course_name', 'course_id', $course_id);
                    $details_array[] = "<b>Course:</b> '".htmlspecialchars($old_name)."' → '".htmlspecialchars($new_name)."'";
                }
                if (($old_data['year_id'] ?? 0) != $year_id) {
                    $old_name = get_lookup_name($conn, 'year_tbl', 'year', 'year_id', $old_data['year_id']);
                    $new_name = get_lookup_name($conn, 'year_tbl', 'year', 'year_id', $year_id);
                    $details_array[] = "<b>Year:</b> '".htmlspecialchars($old_name)."' → '".htmlspecialchars($new_name)."'";
                }
                if (($old_data['section_id'] ?? 0) != $section_id) {
                    $old_name = get_lookup_name($conn, 'section_tbl', 'section_name', 'section_id', $old_data['section_id']);
                    $new_name = get_lookup_name($conn, 'section_tbl', 'section_name', 'section_id', $section_id);
                    $details_array[] = "<b>Section:</b> '".htmlspecialchars($old_name)."' → '".htmlspecialchars($new_name)."'";
                }
                if (($old_data['status_id'] ?? 0) != $status_id) {
                    $old_name = get_lookup_name($conn, 'status_tbl', 'status_name', 'status_id', $old_data['status_id']);
                    $new_name = get_lookup_name($conn, 'status_tbl', 'status_name', 'status_id', $status_id);
                    $details_array[] = "<b>Status:</b> '".htmlspecialchars($old_name)."' → '".htmlspecialchars($new_name)."'";
                }

                $log_details = empty($details_array) ? 'Profile updated with no logged field changes.' : implode("<br>", $details_array);
                log_user_action($conn, 'Edit Student', 'Student', $student_number, $log_details);
            } else {
                $response["success"] = true;
                $response["message"] = "No changes were made to the student's data.";
            }
        } else {
            $response["error"] = "Failed to execute student update: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    $response["error"] = "Invalid request method.";
}

mysqli_close($conn);
ob_end_clean();
header('Content-Type: application/json');
echo json_encode($response);
?>