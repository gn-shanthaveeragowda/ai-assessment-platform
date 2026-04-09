<?php
require_once 'api/db.php';

$new_password = 'password123';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$email = 'admin@quizportal.com';

try {
    // 1. Try to update existing admin
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashed_password, $email]);

    if ($stmt->rowCount() > 0) {
        echo "✅ Success! Admin password reset to: <b>$new_password</b>";
    } else {
        // 2. If update failed, admin might not exist, so insert it.
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES ('Admin', ?, ?, 'admin')");
        if ($stmt->execute([$email, $hashed_password])) {
             echo "✅ Success! Admin account created with password: <b>$new_password</b>";
        } else {
             echo "❌ Error: Could not create admin.";
        }
    }
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage();
}
?>