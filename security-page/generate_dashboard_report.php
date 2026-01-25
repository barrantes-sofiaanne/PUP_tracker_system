<?php
session_start();
include '../PHP/dbcon.php';
require_once __DIR__ . '/../vendor/autoload.php';

class PDF extends FPDF
{
    // Page header
    function Header()
    {
        $this->Image('../IMAGE/Tracker-logo.png', 10, 8, 20);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'PUPT Security Dashboard Report', 0, 1, 'C');
        $this->Ln(5);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Stat Card - Corrected for readability
    function StatCard($title, $value, $color)
    {
        // Draw the top cell (the number part)
        $this->SetFillColor($color[0], $color[1], $color[2]);
        $this->SetFont('Arial', 'B', 14);
        // --- FIX: Set text color to a dark gray for readability ---
        $this->SetTextColor(31, 41, 55); 
        $this->Cell(63.3, 10, $value, 1, 0, 'C', true);

        // Set the cursor for the bottom cell (the title part)
        $this->SetXY($this->GetX() - 63.3, $this->GetY() + 10);

        // Draw the bottom cell
        $this->SetFillColor(243, 244, 246);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(55, 65, 81);
        $this->Cell(63.3, 8, $title, 1, 0, 'C', true);

        // Reset the cursor to the end of the card for the next one
        $this->SetXY($this->GetX(), $this->GetY() - 10);
    }
}

// --- Receive POST data ---
$courseFilter = isset($_POST['course']) && $_POST['course'] !== 'all' ? $_POST['course'] : null;
$yearFilter = isset($_POST['year']) && $_POST['year'] !== 'all' ? $_POST['year'] : null;
$startDate = isset($_POST['start_date']) && !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$endDate = isset($_POST['end_date']) && !empty($_POST['end_date']) ? $_POST['end_date'] : null;
$chart1_image_b64 = $_POST['chart1_image'] ?? null;
$chart2_image_b64 = $_POST['chart2_image'] ?? null;

// --- Fetch Stats Data ---
$baseJoin = "FROM violation_tbl v JOIN users_tbl u ON v.student_number = u.student_number";
$whereClauses = "WHERE 1";
$params = [];
$types = "";

if ($courseFilter) { $whereClauses .= " AND u.course_id = ?"; $params[] = $courseFilter; $types .= "i"; }
if ($yearFilter) { $whereClauses .= " AND u.year_id = ?"; $params[] = $yearFilter; $types .= "i"; }
if ($startDate) { $whereClauses .= " AND DATE(v.violation_date) >= ?"; $params[] = $startDate; $types .= "s"; }
if ($endDate) { $whereClauses .= " AND DATE(v.violation_date) <= ?"; $params[] = $endDate; $types .= "s"; }

// Get Total Violations
$totalViolationsQuery = "SELECT COUNT(v.violation_id) as count $baseJoin $whereClauses";
$stmt = $conn->prepare($totalViolationsQuery);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalViolations = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
$stmt->close();

// Get Sanction/Warning Count
$sanctionsLookup = [];
$sanction_sql = "SELECT violation_type_id, offense_level, disciplinary_sanction FROM disciplinary_sanctions";
$sanction_result = $conn->query($sanction_sql);
while($row = $sanction_result->fetch_assoc()) {
    $sanctionsLookup[$row['violation_type_id']][$row['offense_level']] = $row['disciplinary_sanction'];
}

$offenseCountsQuery = "SELECT v.student_number, v.violation_type, COUNT(*) as count $baseJoin $whereClauses GROUP BY v.student_number, v.violation_type";
$stmt = $conn->prepare($offenseCountsQuery);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$offenses_result = $stmt->get_result();

$totalSanctions = 0;
$totalWarnings = 0;
while($offense = $offenses_result->fetch_assoc()) {
    $oc = $offense['count'];
    $vt_id = $offense['violation_type'];
    $ol_str = ($oc == 1) ? '1st Offense' : (($oc == 2) ? '2nd Offense' : (($oc == 3) ? '3rd Offense' : $oc . 'th Offense'));

    $sanction_text = $sanctionsLookup[$vt_id][$ol_str] ?? null;

    if ($sanction_text && stripos($sanction_text, 'warning') === false) {
        $totalSanctions++;
    } else {
        $totalWarnings++;
    }
}
$stmt->close();

// --- PDF GENERATION ---

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Report Filter Info
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'Report Generated On: ' . date('F j, Y, h:i a'), 0, 1);
$pdf->SetFont('Arial', '', 9);
$pdf->Ln(5);

// Draw Stat Cards
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Key Statistics', 0, 1, 'L');
$pdf->SetY($pdf->GetY() + 2);
$initialX = $pdf->GetX();
$pdf->StatCard('Total Violations', $totalViolations, [219, 239, 255]); // Light Blue
$pdf->SetXY($initialX + 63.3, $pdf->GetY());
$pdf->StatCard('Total Sanctions', $totalSanctions, [254, 226, 226]); // Light Red
$pdf->SetXY($initialX + 126.6, $pdf->GetY());
$pdf->StatCard('Total Warnings', $totalWarnings, [254, 249, 195]); // Light Yellow
$pdf->Ln(25);


// Handle images
$temp_files = [];
if ($chart1_image_b64 && $chart1_image_b64 !== 'null') {
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart1_image_b64));
    $temp_path = sys_get_temp_dir() . '/' . uniqid('chart_') . '.png';
    file_put_contents($temp_path, $data);
    $temp_files[] = $temp_path;
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Top 8 Violations', 0, 1, 'L');
    $pdf->Image($temp_path, 10, $pdf->GetY(), 190);
    $pdf->Ln(95);
}

if ($chart2_image_b64 && $chart2_image_b64 !== 'null') {
    $pdf->AddPage();
    
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart2_image_b64));
    $temp_path = sys_get_temp_dir() . '/' . uniqid('chart_') . '.png';
    file_put_contents($temp_path, $data);
    $temp_files[] = $temp_path;

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Violations Over Time', 0, 1, 'L');
    $pdf->Image($temp_path, 10, $pdf->GetY(), 190);
    $pdf->Ln(95);
}

$pdf->Output('D', 'PUPT_Dashboard_Report_'.date('Y-m-d').'.pdf');

foreach($temp_files as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}
$conn->close();
?>