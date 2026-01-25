<?php
include '../PHP/dbcon.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $security_id = $_POST['security_id'] ?? 0;
    $email = $_POST['email'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $position = $_POST['position'] ?? '';
    $status_id = $_POST['status_id'] ?? 0;
    $password = $_POST['password'] ?? '';

    if (empty($security_id) || empty($email) || empty($first_name) || empty($last_name) || empty($status_id)) {
        echo json_encode(['success' => false, 'error' => 'Required fields are missing.']);
        exit;
    }

    mysqli_begin_transaction($conn);

    try {
        $stmt1 = $conn->prepare("UPDATE security_info SET firstname=?, middlename=?, lastname=?, Position=?, status_id=? WHERE security_id=?");
        $stmt1->bind_param("ssssii", $first_name, $middle_name, $last_name, $position, $status_id, $security_id);
        $stmt1->execute();
        
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("UPDATE security SET email=?, password=? WHERE id=?");
            $stmt2->bind_param("ssi", $email, $hashed_password, $security_id);
        } else {
            $stmt2 = $conn->prepare("UPDATE security SET email=? WHERE id=?");
            $stmt2->bind_param("si", $email, $security_id);
        }
        $stmt2->execute();
        
        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Security user updated successfully.']);
        
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