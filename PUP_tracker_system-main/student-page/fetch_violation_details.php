<?php
require '../PHP/dbcon.php';

header('Content-Type: application/json');

// Check for ID
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing violation type ID']);
    exit;
}

$typeId = intval($_GET['id']);

// Fetch the violation type and category
$sql = "
    SELECT vt.violation_type, vc.category_name
    FROM violation_type_tbl vt
    JOIN violation_category_tbl vc ON vt.violation_category_id = vc.violation_category_id
    WHERE vt.violation_type_id = ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'SQL prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $typeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // Fetch disciplinary sanctions
    $sanctionSql = "
        SELECT offense_level, disciplinary_sanction
        FROM disciplinary_sanctions
        WHERE violation_type_id = ?
        ORDER BY offense_level ASC
    ";

    $stmtSanctions = $conn->prepare($sanctionSql);
    if (!$stmtSanctions) {
        echo json_encode(['success' => false, 'message' => 'Sanction SQL prepare failed: ' . $conn->error]);
        exit;
    }

    $stmtSanctions->bind_param("i", $typeId);
    $stmtSanctions->execute();
    $sanctionResult = $stmtSanctions->get_result();

    $sanctions = [];
    while ($sanction = $sanctionResult->fetch_assoc()) {
        $sanctions[] = [
            'offense_level' => ' ' . $sanction['offense_level'],
            'sanction' => $sanction['disciplinary_sanction']
        ];
    }

    echo json_encode([
        'success' => true,
        'type' => $row['violation_type'],
        'category' => $row['category_name'],
        'sanctions' => $sanctions
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Violation type not found']);
}
?>
