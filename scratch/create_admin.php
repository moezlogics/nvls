<?php
/**
 * scratch/create_admin.php — CLI tool to create/reset the main admin user.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access denied. This script can only be run via CLI/terminal.");
}

require_once dirname(__DIR__) . '/db.php';

$username = 'nagriadmin';
$email = 'admin@kitabnagri.pk';
$password = 'Nagri@9988'; // Custom secure password

$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("
    INSERT INTO admin_users (username, email, password, role) 
    VALUES (?, ?, ?, 'main_admin') 
    ON DUPLICATE KEY UPDATE password = ?, role = 'main_admin'
");

if ($stmt) {
    $stmt->bind_param("ssss", $username, $email, $hash, $hash);
    if ($stmt->execute()) {
        echo "\n============================================\n";
        echo "   ADMIN USER CREATED/RESET SUCCESSFULLY!\n";
        echo "============================================\n";
        echo "Username : " . $username . "\n";
        echo "Email    : " . $email . "\n";
        echo "Password : " . $password . "\n";
        echo "Role     : main_admin\n";
        echo "============================================\n\n";
    } else {
        echo "Error executing statement: " . $stmt->error . "\n";
    }
    $stmt->close();
} else {
    echo "Prepare statement failed: " . $conn->error . "\n";
}
