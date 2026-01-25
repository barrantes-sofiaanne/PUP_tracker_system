<?php
$plain_password = "admin123";
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
echo "Password: " . $plain_password . "<br>";
echo "Hashed Password: " . $hashed_password;
?>