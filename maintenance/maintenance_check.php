<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_maintenance_mode = true; 

if ($is_maintenance_mode) {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: maintenance/maintenance.php");
        exit();
    }
}
?>