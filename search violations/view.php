<?php
include '../PHP/dbcon.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$sql = "SELECT * FROM violations WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "<h2>Violation #{$row['id']}</h2>";
    echo "<p>{$row['title']}</p>";
    echo "<a href='index.php'>← Back to list</a>";
} else {
    echo "<p>Violation not found.</p>";
}
?>