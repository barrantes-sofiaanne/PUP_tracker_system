<?php
include '../PHP/dbcon.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Code of Discipline</title>
  <link rel="stylesheet" href="../search violations/styles.css">
</head>
<body>
  <div class="container">
    <header>
      <h1>Code of Discipline</h1>
      <p>Summary of University Violations</p>
    </header>

    <div class="search-wrapper">
      <input type="text" id="searchInput" placeholder="Search violation...">
    </div>

    <ul id="violationList">
      <?php
      $search = isset($_GET['search']) ? trim($_GET['search']) : '';
      $query = "SELECT * FROM violations";

      if (!empty($search)) {
          $query .= " WHERE title = ?";
          $stmt = $conn->prepare($query);
          $stmt->bind_param("s", $search);
      } else {
          $stmt = $conn->prepare($query);
      }

      if ($stmt) {
          $stmt->execute();
          $result = $stmt->get_result();

          while ($row = $result->fetch_assoc()) {
              echo "<li><a href='view.php?id={$row['id']}' class='violation-link'>{$row['id']}. {$row['title']}</a></li>";
          }
      } else {
          echo "<li>Query error: " . $conn->error . "</li>";
      }
      ?>
    </ul>
  </div>

  <script src="../search violations/script.js"></script>
</body>
</html>
