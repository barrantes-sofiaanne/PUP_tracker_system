<?php
require_once '../PHP/dbcon.php';
session_start();

if (!isset($_SESSION["current_user_id"]) || !isset($_SESSION["user_student_number"])) {
    if (isset($_GET['source']) && $_GET['source'] === 'ajax') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit();
    }
    header("Location: ./student_login.php");
    exit();
}

$student_stud_number = $_SESSION["user_student_number"];
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$redirect_url = './student_dashboard.php';

if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $decoded_redirect = urldecode($_GET['redirect']);
    if (parse_url($decoded_redirect, PHP_URL_HOST) === null || parse_url($decoded_redirect, PHP_URL_HOST) === $_SERVER['HTTP_HOST']) {
        if (substr($decoded_redirect, 0, 1) === '.' || substr($decoded_redirect, 0, 1) === '/') {
            $redirect_url = $decoded_redirect;
        }
    }
}

if ($notification_id > 0 && isset($conn)) {
    $sql_mark_read = "UPDATE notifications_tbl SET is_read = TRUE 
                        WHERE notification_id = ? AND student_number = ?";
    if ($stmt_mark_read = $conn->prepare($sql_mark_read)) {
        $stmt_mark_read->bind_param("is", $notification_id, $student_stud_number);
        if ($stmt_mark_read->execute()) {
            if (isset($_GET['source']) && $_GET['source'] === 'ajax') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                $stmt_mark_read->close();
                $conn->close();
                exit();
            }
        } else {
            error_log("Error executing mark_read for notification_id " . $notification_id . ": " . $stmt_mark_read->error);
            if (isset($_GET['source']) && $_GET['source'] === 'ajax') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Database update error.']);
                $stmt_mark_read->close();
                $conn->close();
                exit();
            }
        }
        $stmt_mark_read->close();
    } else {
        error_log("Error preparing mark_read query for notification_id " . $notification_id . ": " . $conn->error);
        if (isset($_GET['source']) && $_GET['source'] === 'ajax') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database preparation error.']);
            if(isset($conn)) $conn->close();
            exit();
        }
    }
} else {
    if (isset($_GET['source']) && $_GET['source'] === 'ajax') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID or DB connection error.']);
        if(isset($conn)) $conn->close();
        exit();
    }
}

if (isset($conn)) {
    $conn->close();
}

if (!(isset($_GET['source']) && $_GET['source'] === 'ajax')) {
    header("Location: " . htmlspecialchars($redirect_url)); 
    exit();
}
?>