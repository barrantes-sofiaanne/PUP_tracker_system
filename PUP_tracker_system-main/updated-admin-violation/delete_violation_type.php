<?php
include '../PHP/dbcon.php';

$response = ['success' => false, 'message' => 'Invalid request.'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['violation_type_id'])) {
    $violationTypeId = trim($_POST['violation_type_id']);

    if (!empty($violationTypeId)) {
        $stmt = $conn->prepare("DELETE FROM violation_type_tbl WHERE violation_type_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $violationTypeId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Violation type deleted successfully.';
                } else {
                    $response['message'] = 'Violation type not found or already deleted.';
                }
            } else {
                $response['message'] = 'Error deleting violation type: ' . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $response['message'] = 'Error preparing statement: ' . htmlspecialchars($conn->error);
        }
    } else {
        $response['message'] = 'Violation type ID is missing.';
    }
}

header('Content-Type: application/json');
echo json_encode($response);
$conn->close();
?>