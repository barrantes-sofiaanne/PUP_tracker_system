<?php
// Use this script to generate a hashed password.
// 1. Change the value of $plainPassword to the password you want to hash.
// 2. Upload this file to your server and run it in the browser.
// 3. Copy the hashed password output.
// 4. Update the 'password' column in your 'security' table with this new hash.
// 5. IMPORTANT: Delete this file from your server after use for security.

$plainPassword = 'mika12345'; // <-- IMPORTANT: Change this to your desired password

$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

echo "Plain Password: " . htmlspecialchars($plainPassword) . "<br>";
echo "Hashed Password: " . htmlspecialchars($hashedPassword);
?>
