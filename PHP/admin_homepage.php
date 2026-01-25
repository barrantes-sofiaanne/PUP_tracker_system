<?php
include 'dbcon.php';

//course
$course_query = "
    SELECT c.course_name, COUNT(*) AS total
    FROM users_tbl AS s
    JOIN course_tbl c ON s.course_id = c.course_id
    GROUP BY c.course_name
    ORDER BY total DESC
";
$course_result = $conn->query($course_query);

$courses = [];
$course_totals = [];

while ($row = $course_result->fetch_assoc()) {
    $courses[] = $row['course_name'];
    $course_totals[] = $row['total'];
}

// violation

// Query to get violation count by type
$violation_query = "
    SELECT vt.violation_type AS type_name, COUNT(*) AS total
    FROM violation_tbl v
    JOIN violation_type_tbl vt ON v.violation_type = vt.violation_type_id
    GROUP BY vt.violation_type
    ORDER BY total DESC
";

$violation_result = $conn->query($violation_query);

// Arrays for Chart.js
$violation_labels = [];
$violation_totals = [];

while ($row = $violation_result->fetch_assoc()) {
    $violation_labels[] = $row['type_name'];
    $violation_totals[] = (int)$row['total'];
}

$response = [
    'courses' => [
        'labels' => $courses,
        'data' => $course_totals
    ],
    'violation' => [
        'labels' => $violation_labels,
        'data' => $violation_totals
    ]
];

echo json_encode($response);

$conn->close();
?>


