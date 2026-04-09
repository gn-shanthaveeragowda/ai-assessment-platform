<?php
require_once 'api/db.php';

$message = '';
$messageType = '';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (empty($email) || empty($new_pass)) {
        $message = "Please fill in all fields.";
        $messageType = "error";
    } elseif ($new_pass !== $confirm_pass) {
        $message = "New passwords do not match.";
        $messageType = "error";
    } else {
      
        $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($update->execute([$new_hash, $user['id']])) {
                $message = "✅ Success! Password for <strong>" . htmlspecialchars($user['name']) . "</strong> (" . $user['role'] . ") changed.";
                $messageType = "success";
            } else {
                $message = "Database error during update.";
                $messageType = "error";
            }
        } else {
            $message = "❌ User not found with that email.";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Password Tool</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-gray-800">🔑 Password Reset Tool</h1>
            <p class="text-gray-500 text-sm mt-2">For local XAMPP development only</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User Email</label>
                <input type="email" name="email" required placeholder="e.g., admin@quizportal.com" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input type="password" name="new_password" required placeholder="Enter new password" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
             <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <input type="password" name="confirm_password" required placeholder="Repeat new password" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-all">
                Reset Password
            </button>
        </form>

        <div class="mt-6 text-center border-t pt-4">
            <a href="index.html" class="text-blue-600 hover:underline">Go to Login Page</a>
        </div>
    </div>
</body>
</html>