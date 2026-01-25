<?php
$defaultPasswordToSet = "pup123";

$hashedPassword = password_hash($defaultPasswordToSet, PASSWORD_DEFAULT);

echo "<p>If you want to set the default password to: <strong>" . htmlspecialchars($defaultPasswordToSet) . "</strong></p>";
echo "<p>The HASHED version you need to put in your database is:</p>";
echo "<p><strong style='font-family: monospace; font-size: 1.2em; color: green;'>" . htmlspecialchars($hashedPassword) . "</strong></p>";
echo "<p><em>Copy the entire hashed string above (it usually starts with $2y$10$).</em></p>";
?>