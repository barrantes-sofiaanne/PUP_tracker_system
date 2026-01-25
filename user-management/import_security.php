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

        // Expected headers for Security CSV
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

            // Auto-fill position to 'Security Guard', status_id to 'Active' (ID 1), and role_id to 'Security' (ID 3)
            $position = "Security Guard"; // As per security_info table default
            $status_id = 1; // Assuming 1 is 'Active' in status_tbl
            $roles_id = 3;  // Assuming 3 is 'Security' in roles_tbl

            if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
                $failedEntries[] = "Missing required data for security user: " . implode(', ', $rowData);
                continue;
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Check for existing email in security table
            $check_query = "SELECT email FROM security WHERE email = '$email'";
            $check_result = mysqli_query($conn, $check_query);

            if (mysqli_num_rows($check_result) > 0) {
                $failedEntries[] = "Duplicate entry: Security Email '{$email}' already exists.";
                continue;
            }

            // Start transaction
            mysqli_begin_transaction($conn);
            try {
                // Insert into security table first
                $insert_security_credentials_query = "INSERT INTO security (email, password) VALUES (?, ?)";
                $stmt_credentials = mysqli_prepare($conn, $insert_security_credentials_query);
                mysqli_stmt_bind_param($stmt_credentials, "ss", $email, $hashed_password);
                mysqli_stmt_execute($stmt_credentials);
                $security_id = mysqli_insert_id($conn); // Get the auto-generated ID

                if ($security_id) {
                    // Insert into security_info table
                    $insert_security_info_query = "INSERT INTO security_info (security_id, firstname, middlename, lastname, position, status_id, role_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_info = mysqli_prepare($conn, $insert_security_info_query);
                    mysqli_stmt_bind_param($stmt_info, "issssii", $security_id, $first_name, $middle_name, $last_name, $position, $status_id, $roles_id);

                    if (mysqli_stmt_execute($stmt_info)) {
                        mysqli_commit($conn);
                        $importedCount++;
                        // Log the action
                        $details = "Imported security user: <b>" . htmlspecialchars($first_name) . " " . htmlspecialchars($last_name) . "</b> (Email: " . htmlspecialchars($email) . ")";
                        $log_query = "INSERT INTO user_management_history (performed_by_admin_name, action_type, target_user_type, target_user_identifier, details) VALUES (?, ?, ?, ?, ?)";
                        $log_stmt = mysqli_prepare($conn, $log_query);
                        $action_type = "Import Security";
                        $target_user_type = "Security";
                        $target_user_identifier = $email;
                        mysqli_stmt_bind_param($log_stmt, "sssss", $admin_name, $action_type, $target_user_type, $target_user_identifier, $details);
                        mysqli_stmt_execute($log_stmt);
                        mysqli_stmt_close($log_stmt);

                    } else {
                        mysqli_rollback($conn);
                        $failedEntries[] = "Failed to insert security info for {$email}: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt_info);
                } else {
                    mysqli_rollback($conn);
                    $failedEntries[] = "Failed to create security credentials for {$email}: " . mysqli_error($conn);
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
            $response['message'] = "Successfully imported $importedCount security personnel.";
            if (!empty($failedEntries)) {
                $response['message'] .= " Some entries failed: " . implode('; ', $failedEntries);
                $response['success'] = false;
            }
        } else {
            $response['message'] = "No security personnel were imported. " . (!empty($failedEntries) ? "Errors: " . implode('; ', $failedEntries) : "Please check your CSV file and ensure it contains valid data and headers.");
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