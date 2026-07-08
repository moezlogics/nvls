<?php
/**
 * temp_create_admin.php — Temporary script to create/reset the main admin user.
 * IMPORTANT: Delete this file from the server immediately after running it!
 */
require_once __DIR__ . '/db.php';

$username = 'nagriadmin';
$email = 'admin@kitabnagri.pk';
$password = 'Nagri@9988'; // Secure admin password

$hash = password_hash($password, PASSWORD_BCRYPT);

$q = "INSERT INTO admin_users (username, email, password, role) 
      VALUES ('$username', '$email', '$hash', 'main_admin') 
      ON DUPLICATE KEY UPDATE password='$hash', role='main_admin'";

echo "<div style='font-family:sans-serif; max-width:500px; margin:50px auto; padding:20px; border:1px solid #ddd; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05);'>";

if ($conn->query($q)) {
    echo "<h2 style='color:#10b981; margin-top:0;'>Success! Admin User Created</h2>";
    echo "<p><strong>Username:</strong> <code style='background:#f3f4f6; padding:2px 6px; border-radius:4px;'>$username</code></p>";
    echo "<p><strong>Password:</strong> <code style='background:#f3f4f6; padding:2px 6px; border-radius:4px;'>$password</code></p>";
    echo "<p style='color:#ef4444; font-weight:bold; margin-top:20px;'>⚠️ SECURITY WARNING:<br>Delete this file (temp_create_admin.php) from your server immediately so no one else can run it!</p>";
} else {
    echo "<h2 style='color:#ef4444; margin-top:0;'>Database Error</h2>";
    echo "<p>" . htmlspecialchars($conn->error) . "</p>";
}

echo "</div>";
