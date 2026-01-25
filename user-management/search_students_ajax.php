<?php
include '../PHP/dbcon.php';

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
    if($countRow = mysqli_fetch_assoc($countResult)) {
        return $countRow['total'];
    }
    return 0;
}

if ($conn) {
    $search_student = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
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

    echo '<table id="student-table"><tbody>';
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
            if (!empty($row['new_until']) && time() < strtotime($row['new_until'])) {
                $new_badge = "<span class='new-badge'>NEW</span>";
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
            echo "<td><div class='table-action-buttons'><button class='edit-btn student-edit-btn' data-student='".$student_data_json."'><i class='fas fa-pencil-alt'></i> Edit</button><button type='button' class='delete-btn student-delete-trigger-btn' data-id='".htmlspecialchars($row['student_number'])."' data-name='".htmlspecialchars($row['first_name'] . ' ' . $row['last_name'])."' data-type='student'><i class='fas fa-trash-alt'></i> Delete</button></div></td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='10'>No results found for your search.</td></tr>";
    }
    echo '</tbody></table>';

    if ($total_pages > 1):
        echo '<div class="pagination-controls">';
        $base_url = "?tab=students&search=".urlencode($search_student)."&course=".urlencode($course_id_filter)."&year=".urlencode($year_id_filter)."&section=".urlencode($section_id_filter)."&status=".urlencode($status_id_filter);
        if ($current_page > 1) {
            echo '<a href="'.$base_url.'&page='.($current_page - 1).'" class="pagination-btn"><i class="fas fa-angle-left"></i> Previous</a>';
        }
        for ($p = 1; $p <= $total_pages; $p++) {
            $active_class = ($p == $current_page) ? 'active' : '';
            echo '<a href="'.$base_url.'&page='.$p.'" class="pagination-btn pagination-page-btn '.$active_class.'">'.$p.'</a>';
        }
        if ($current_page < $total_pages) {
            echo '<a href="'.$base_url.'&page='.($current_page + 1).'" class="pagination-btn">Next <i class="fas fa-angle-right"></i></a>';
        }
        echo '</div>';
    endif;
}
?>