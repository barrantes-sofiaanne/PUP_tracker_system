<?php
session_start();
include '../PHP/dbcon.php';

$securityPageData = [
    'isLoggedIn' => false,
    'firstName' => 'N/A',
    'middleName' => 'N/A',
    'lastName' => 'N/A',
    'position' => 'N/A',
    'email' => 'N/A',
    'errorMessage' => null
];

if (isset($_SESSION['security_id'])) {
    $securityPageData['isLoggedIn'] = true;
    $security_id = $_SESSION['security_id'];

    if ($conn) {
        $query = "SELECT si.firstname, si.middlename, si.lastname, si.position, s.email
                  FROM security_info si
                  JOIN security s ON si.security_id = s.id
                  WHERE si.security_id = ?";

        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $security_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $securityDetails = $result->fetch_assoc();
                    $securityPageData['firstName'] = htmlspecialchars($securityDetails['firstname']);
                    $securityPageData['middleName'] = $securityDetails['middlename'] ? htmlspecialchars($securityDetails['middlename']) : '';
                    $securityPageData['lastName'] = htmlspecialchars($securityDetails['lastname']);
                    $securityPageData['position'] = htmlspecialchars($securityDetails['position']);
                    $securityPageData['email'] = htmlspecialchars($securityDetails['email']);
                } else {
                    $securityPageData['errorMessage'] = 'Security details not found in the database.';
                }
            } else {
                $securityPageData['errorMessage'] = 'Failed to execute database query: ' . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $securityPageData['errorMessage'] = 'Failed to prepare database query: ' . htmlspecialchars($conn->error);
        }
        $conn->close();
    } else {
        $securityPageData['errorMessage'] = 'Database connection could not be established.';
    }
} else {
    $securityPageData['errorMessage'] = 'Not authorized or session has expired. Please log in.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Account Information</title>
    <link rel="stylesheet" href="./security_account_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>

<div class="page-container" id="pageContainer">
    <aside class="side-menu">
        <div class="menu-header">
            <img src="../IMAGE/Tracker-logo.png" alt="PUP Logo" class="menu-logo">
            <button class="close-btn" id="closeMenuBtn">&times;</button>
        </div>
        <nav class="menu-nav">
            <a href="security_dashboard.php" class="nav-item"><i class="fas fa-chart-bar"></i> Dashboard</a>
            <a href="security_violation_page.php" class="nav-item"><i class="fas fa-exclamation-triangle"></i> Violations</a>
            <a href="security_account.php" class="nav-item active"><i class="fas fa-user-shield"></i> My Account</a>
            <a href="../PHP/logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <div class="page-wrapper" id="pageWrapper">
        <header class="main-header">
            <div class="header-content">
                <div class="logo"><img src="../IMAGE/Tracker-logo.png" alt="PUP Logo"></div>
                <nav class="main-nav">
                    <a href="security_dashboard.php">Dashboard</a>
                    <a href="security_violation_page.php">Violations</a>
                    <a href="security_account.php" class="active-nav">My Account</a>
                </nav>
                <div class="user-icons">
                    <a href="notification.html" class="notification"><svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13.586V10c0-3.217-2.185-5.927-5.145-6.742C13.562 2.52 12.846 2 12 2s-1.562.52-1.855 1.258C7.185 4.073 5 6.783 5 10v3.586l-1.707 1.707A.996.996 0 0 0 3 16v2a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-2a.996.996 0 0 0-.293-.707L19 13.586zM19 17H5v-.586l1.707-1.707A.996.996 0 0 0 7 14v-4c0-2.757 2.243-5 5-5s5 2.243 5 5v4c0 .266.105.52.293.707L19 16.414V17zm-7 5a2.98 2.98 0 0 0 2.818-2H9.182A2.98 2.98 0 0 0 12 22z"/></svg></a>
                    <a href="security_account.php" class="admin-profile active-nav"><svg class="header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></a>
                </div>
                <button class="menu-toggle" id="openMenuBtn"><i class="fas fa-bars"></i></button>
            </div>
        </header>

        <main class="main-content">
            <div class="account-container">
                <h1>Account Information</h1>
                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-user icon-style"></i>First Name</span>
                        <span class="info-value" id="securityFirstName"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-user icon-style"></i>Middle Name</span>
                        <span class="info-value" id="securityMiddleName"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-user icon-style"></i>Last Name</span>
                        <span class="info-value" id="securityLastName"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-briefcase icon-style"></i>Position</span>
                        <span class="info-value" id="securityPosition"></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-envelope icon-style"></i>Email</span>
                        <span class="info-value" id="securityEmail"></span>
                    </div>
                </div>
                <button id="signOutBtn" class="sign-out-btn">Sign Out</button>
                <div id="errorMessageDisplay"></div>
            </div>
        </main>
    </div>
    <div class="overlay" id="overlay"></div>
</div>

<script>
    const securityData = <?php echo json_encode($securityPageData); ?>;
</script>
<script src="security_account.js?v=<?php echo time(); ?>"></script>
</body>
</html>