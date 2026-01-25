<?php
include '../PHP/dbcon.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $security_id = $_POST['security_id'] ?? 0;

    if (empty($security_id)) {
        echo json_encode(['success' => false, 'error' => 'Security ID is missing.']);
        exit;
    }

    mysqli_begin_transaction($conn);

    try {
        $stmt1 = $conn->prepare("DELETE FROM security_info WHERE security_id = ?");
        $stmt1->bind_param("i", $security_id);
        $stmt1->execute();
        
        $stmt2 = $conn->prepare("DELETE FROM security WHERE id = ?");
        $stmt2->bind_param("i", $security_id);
        $stmt2->execute();
        
        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Security user deleted successfully.']);
        
    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $exception->getMessage()]);
    } finally {
        if (isset($stmt1)) $stmt1->close();
        if (isset($stmt2)) $stmt2->close();
        $conn->close();
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>