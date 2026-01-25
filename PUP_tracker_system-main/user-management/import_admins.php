<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../PHP/dbcon.php'; // Adjust path as necessary for your db connection
session_start();

// Ensure this script is only accessible by authorized admins
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$response = ['success' => false, 'message' => ''];
$admin_name = $_SESSION['admin_name'] ?? 'Admin'; // Get admin name from session for history logging

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $csvFile = $_FILES['csv_file']['tmp_name'];
    $fileType = mime_content_type($csvFile);

    // Basic validation for CSV file type
    if ($fileType !== 'text/csv' && $fileType !== 'application/vnd.ms-excel') {
        $response['message'] = 'Invalid file type. Please upload a CSV file.';
        echo json_encode($response);
        exit();
    }

    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ","); // Read the header row

        // Expected headers for Admin CSV
        $expectedHeaders = ['email', 'password', 'first_name', 'middle_name', 'last_name'];
        $missingHeaders = array_diff($expectedHeaders, $header);
        if (!empty($missingHeaders)) {
            $response['message'] = 'Missing required CSV headers: ' . implode(', ', $missingHeaders);
            fclose($handle);
            echo json_encode($response);
            exit();
        }

        $importedCount = 0;
        $failedEntries = [];
        $columnMapping = array_flip($header);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowData = [];
            foreach ($columnMapping as $csvHeader => $index) {
                $rowData[$csvHeader] = $data[$index];
            }

            $email = mysqli_real_escape_string($conn, $rowData['email'] ?? '');
            $password = $rowData['password'] ?? '';
            $first_name = mysqli_real_escape_string($conn, $rowData['first_name'] ?? '');
            $middle_name = mysqli_real_escape_string($conn, $rowData['middle_name'] ?? '');
            $last_name = mysqli_real_escape_string($conn, $rowData['last_name'] ?? '');

            // Auto-fill position to 'Admin', status_id to 'Active' (ID 1), and role_id to 'Admin' (ID 1)
            $position = "Admin";
            $status_id = 1; // Assuming 1 is 'Active' in status_tbl
            $roles_id = 1;  // Assuming 1 is 'Admin' in roles_tbl

            if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
                $failedEntries[] = "Missing required data for admin: " . implode(', ', $rowData);
                continue;
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Check for existing email in admins table
            $check_query = "SELECT email FROM admins WHERE email = '$email'";
            $check_result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($check_result) > 0) {
                $failedEntries[] = "Duplicate entry: Admin Email '{$email}' already exists.";
                continue;
            }

            // Start transaction
            mysqli_begin_transaction($conn);
            try {
                // Insert into admins table first
                $insert_admin_credentials_query = "INSERT INTO admins (username, email, password) VALUES (?, ?, ?)";
                $stmt_credentials = mysqli_prepare($conn, $insert_admin_credentials_query);
                mysqli_stmt_bind_param($stmt_credentials, "sss", $email, $email, $hashed_password); // Username is typically email for admins
                mysqli_stmt_execute($stmt_credentials);
                $admin_id = mysqli_insert_id($conn); // Get the auto-generated ID

                if ($admin_id) {
                    // Insert into admin_info_tbl
                    $insert_admin_info_query = "INSERT INTO admin_info_tbl (admin_id, Position, firstname, middlename, lastname, status_id, role_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_info = mysqli_prepare($conn, $insert_admin_info_query);
                    mysqli_stmt_bind_param($stmt_info, "issssii", $admin_id, $position, $first_name, $middle_name, $last_name, $status_id, $roles_id);

                    if (mysqli_stmt_execute($stmt_info)) {
                        mysqli_commit($conn);
                        $importedCount++;
                        // Log the action
                        $details = "Imported admin: <b>" . htmlspecialchars($first_name) . " " . htmlspecialchars($last_name) . "</b> (Email: " . htmlspecialchars($email) . ")";
                        $log_query = "INSERT INTO user_management_history (performed_by_admin_name, action_type, target_user_type, target_user_identifier, details) VALUES (?, ?, ?, ?, ?)";
                        $log_stmt = mysqli_prepare($conn, $log_query);
                        $action_type = "Import Admin";
                        $target_user_type = "Admin";
                        $target_user_identifier = $email;
                        mysqli_stmt_bind_param($log_stmt, "sssss", $admin_name, $action_type, $target_user_type, $target_user_identifier, $details);
                        mysqli_stmt_execute($log_stmt);
                        mysqli_stmt_close($log_stmt);

                    } else {
                        mysqli_rollback($conn);
                        $failedEntries[] = "Failed to insert admin info for {$email}: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt_info);
                } else {
                    mysqli_rollback($conn);
                    $failedEntries[] = "Failed to create admin credentials for {$email}: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt_credentials);
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $failedEntries[] = "Transaction failed for {$email}: " . $e->getMessage();
            }
        }
        fclose($handle);

        if ($importedCount > 0) {
            $response['success'] = true;
            $response['message'] = "Successfully imported $importedCount admins.";
            if (!empty($failedEntries)) {
                $response['message'] .= " Some entries failed: " . implode('; ', $failedEntries);
                $response['success'] = false;
            }
        } else {
            $response['message'] = "No admins were imported. " . (!empty($failedEntries) ? "Errors: " . implode('; ', $failedEntries) : "Please check your CSV file and ensure it contains valid data and headers.");
        }
    } else {
        $response['message'] = 'Error opening CSV file.';
    }
} else {
    $response['message'] = 'No CSV file uploaded or invalid request method.';
}

echo json_encode($response);
exit();
?>