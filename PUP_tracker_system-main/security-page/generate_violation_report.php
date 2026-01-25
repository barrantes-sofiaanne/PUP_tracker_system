<?php
session_start();
include '../PHP/dbcon.php';
require_once __DIR__ . '/../vendor/autoload.php';

class PDF extends FPDF
{
    // Page header
    function Header()
    {
        $this->Image('../IMAGE/Tracker-logo.png', 10, 6, 20);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(30, 10, 'PUPT Student Violation Report', 0, 0, 'C');
        $this->Ln(15);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Student Header Row
    function StudentHeader($student_row)
    {
        $this->SetFillColor(230, 230, 230); // Light Gray
        $this->SetFont('Arial', 'B', 10);
        $fullName = $student_row['last_name'] . ', ' . $student_row['first_name'] . ' ' . ($student_row['middle_name'][0] ?? '') . '.';
        $infoText = 'Student: ' . $fullName . '  |  Number: ' . $student_row['student_number'];
        $this->Cell(190, 8, $infoText, 1, 1, 'L', true);
    }
    
    // Violation Details Table Header
    function ViolationHeader()
    {
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(128, 0, 0); // Maroon
        $this->SetTextColor(255, 255, 255);
        $this->Cell(90, 7, 'VIOLATION TYPE & REMARKS', 1, 0, 'C', true);
        $this->Cell(30, 7, 'OFFENSE LEVEL', 1, 0, 'C', true);
        $this->Cell(40, 7, 'DATE RECORDED', 1, 0, 'C', true);
        $this->Cell(30, 7, 'STATUS', 1, 0, 'C', true);
        $this->Ln();
    }
}

// --- DATA FETCHING ---

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course = isset($_GET['course_id']) ? $_GET['course_id'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$sql_students = "SELECT DISTINCT u.student_number, u.first_name, u.middle_name, u.last_name, 
                                 c.course_name, y.year, s.section_name 
                   FROM users_tbl u
                   JOIN violation_tbl v ON u.student_number = v.student_number
                   LEFT JOIN course_tbl c ON u.course_id = c.course_id
                   LEFT JOIN year_tbl y ON u.year_id = y.year_id
                   LEFT JOIN section_tbl s ON u.section_id = s.section_id";

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

if (!empty($where)) {
    $sql_students .= " WHERE " . implode(" AND ", $where);
}
$sql_students .= " ORDER BY u.last_name ASC, u.first_name ASC";

$stmt_students = $conn->prepare($sql_students);
if (!empty($params)) {
    $stmt_students->bind_param($types, ...$params);
}

$stmt_students->execute();
$result_students = $stmt_students->get_result();

// --- PDF GENERATION ---

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'Report Generated On: ' . date('F j, Y, h:i a'), 0, 1);
$pdf->SetFont('Arial', '', 9);
$filter_text = 'Filters Applied: ';
$applied_filters = [];
if (!empty($course)) {
    $c_sql = "SELECT course_name from course_tbl where course_id = ? limit 1";
    $c_stmt = $conn->prepare($c_sql);
    $c_stmt->bind_param("i", $course);
    $c_stmt->execute();
    $c_res = $c_stmt->get_result();
    $c_row = $c_res->fetch_assoc();
    $applied_filters[] = 'Course - ' . ($c_row['course_name'] ?? 'N/A');
}
if (!empty($startDate) && !empty($endDate)) {
    $applied_filters[] = 'Dates from ' . date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate));
}
if (!empty($search)) {
    $applied_filters[] = 'Search for "' . $search . '"';
}
if (empty($applied_filters)) {
    $applied_filters[] = 'None';
}
$pdf->MultiCell(0, 5, $filter_text . implode('; ', $applied_filters), 0, 1);
$pdf->Ln(5);

if ($result_students->num_rows > 0) {
    $sanction_sql_check = "SELECT disciplinary_sanction FROM disciplinary_sanctions WHERE violation_type_id = ? AND offense_level = ?";
    $stmt_sanction_check = $conn->prepare($sanction_sql_check);
    
    // Base SQL for violation details
    $violations_sql_base = "SELECT vt.violation_type, v.violation_type as violation_type_id, 
                                   (SELECT COUNT(*) FROM violation_tbl WHERE student_number = ? AND violation_type = v.violation_type) as offense_count,
                                   (SELECT v_inner.description FROM violation_tbl v_inner WHERE v_inner.student_number = v.student_number AND v_inner.violation_type = v.violation_type ORDER BY v_inner.violation_date DESC LIMIT 1) as latest_description,
                                   MAX(v.violation_date) as latest_date
                             FROM violation_tbl v
                             JOIN violation_type_tbl vt ON v.violation_type = vt.violation_type_id";

    while ($student_row = $result_students->fetch_assoc()) {
        $pdf->StudentHeader($student_row);
        
        $student_number = $student_row['student_number'];

        // ---- FIX STARTS HERE: Dynamically build WHERE clause for the violation details query ----
        $violation_where = ["v.student_number = ?"];
        $violation_params = [$student_number, $student_number];
        $violation_types = "ss";

        if (!empty($startDate)) {
            $violation_where[] = "DATE(v.violation_date) >= ?";
            $violation_params[] = $startDate;
            $violation_types .= "s";
        }
        if (!empty($endDate)) {
            $violation_where[] = "DATE(v.violation_date) <= ?";
            $violation_params[] = $endDate;
            $violation_types .= "s";
        }
        $violations_sql = $violations_sql_base . " WHERE " . implode(" AND ", $violation_where) . " GROUP BY v.student_number, v.violation_type ORDER BY latest_date DESC";
        $stmt_violations = $conn->prepare($violations_sql);
        $stmt_violations->bind_param($violation_types, ...$violation_params);
        // ---- FIX ENDS HERE ----

        $stmt_violations->execute();
        $violations_result = $stmt_violations->get_result();

        if ($violations_result->num_rows > 0) {
            $pdf->ViolationHeader();
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Arial', '', 8);

            while ($violation_row = $violations_result->fetch_assoc()) {
                $offense_count = $violation_row['offense_count'];
                $offense_level_display_str = ($offense_count == 1) ? '1st Offense' : (($offense_count == 2) ? '2nd Offense' : (($offense_count == 3) ? '3rd Offense' : $offense_count . 'th Offense'));
                
                $status_text = 'Warning';
                $stmt_sanction_check->bind_param("is", $violation_row['violation_type_id'], $offense_level_display_str);
                $stmt_sanction_check->execute();
                $sanction_result = $stmt_sanction_check->get_result();
                if ($sanction_row = $sanction_result->fetch_assoc()) {
                    if (!empty($sanction_row['disciplinary_sanction']) && stripos($sanction_row['disciplinary_sanction'], 'warning') === false) {
                        $status_text = 'Sanction';
                    }
                }

                $violationText = $violation_row['violation_type'];
                if (!empty($violation_row['latest_description'])) {
                    $violationText .= "\nRemarks: " . $violation_row['latest_description'];
                }

                $y_before = $pdf->GetY();
                $pdf->MultiCell(90, 5, $violationText, 1, 'L');
                $y_after = $pdf->GetY();
                $cellHeight = $y_after - $y_before;
                $pdf->SetXY($pdf->GetX() + 90, $y_before);

                $pdf->Cell(30, $cellHeight, $offense_level_display_str, 1, 0, 'C');
                $pdf->Cell(40, $cellHeight, date("M j, Y, g:i a", strtotime($violation_row['latest_date'])), 1, 0, 'C');
                $pdf->Cell(30, $cellHeight, $status_text, 1, 1, 'C');
            }
        } else {
             $pdf->SetFont('Arial', 'I', 8);
             $pdf->Cell(190, 7, 'No violations found for this student within the selected filters.', 1, 1, 'C');
        }
        $pdf->Ln(5);
    }
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(190, 10, 'No student records found for the selected filters.', 1, 0, 'C');
}

$pdf->Output('D', 'PUPT_Violation_Report_'.date('Y-m-d').'.pdf');
$conn->close();
?>