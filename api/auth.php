<?php
// 1. Start Output Buffering & Hide HTML Errors
ob_start();
ini_set('display_errors', 0); 
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    // 2. Connect to Database safely
    if (!file_exists('db.php')) { throw new Exception("db.php file is missing."); }
    require_once 'db.php';

    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    $action = $_GET['action'] ?? null;

    // --- LOGIN ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['email']) || empty($data['password'])) {
            throw new Exception('Email and password required.');
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if ($user && password_verify($data['password'], $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['dept'] = $user['department'] ?? '';
            $_SESSION['sem'] = $user['semester'] ?? '';
            
            // Safe Log (Ignore if table missing)
            try { $pdo->prepare("INSERT INTO login_logs (user_id) VALUES (?)")->execute([$user['id']]); } catch (Exception $e) {}

            echo json_encode(['success' => true, 'role' => $user['role'], 'redirect' => 'dashboard.php']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        }
        exit;
    }

    // --- REGISTER ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
        $data = json_decode(file_get_contents('php://input'), true);
        $dept = null; $sem = null; $tid = null;
        
        if ($data['role'] === 'teacher') {
            if (empty($data['teacher_code'])) throw new Exception('Teacher Code required.');
            $stmt = $pdo->prepare("SELECT id, department FROM teacher_ids WHERE code = ? AND is_used = 0");
            $stmt->execute([$data['teacher_code']]);
            $teacherInfo = $stmt->fetch();
            if (!$teacherInfo) throw new Exception('Invalid Teacher Code.');
            $tid = $teacherInfo['id'];
            $dept = $teacherInfo['department'];
        } else {
            $dept = !empty($data['department']) ? $data['department'] : null;
            $sem = !empty($data['semester']) ? $data['semester'] : null;
        }

        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) throw new Exception('Missing fields.');

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, department, semester) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), $data['role'], $dept, $sem]);
            if ($tid) $pdo->prepare("UPDATE teacher_ids SET is_used = 1 WHERE id = ?")->execute([$tid]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Registered! Please login.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $msg = ($e->getCode() == 23000) ? 'Email already registered.' : 'DB Error';
            echo json_encode(['success' => false, 'message' => $msg]);
        }
        exit;
    }

    // --- FORGOT PASSWORD ---
    if ($action === 'forgot_request') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if ($user) {
            $code = rand(100000, 999999);
            try {
                $pdo->prepare("UPDATE users SET reset_token = ? WHERE id = ?")->execute([$code, $user['id']]);
                echo json_encode(['success' => true, 'message' => 'Code sent!', 'debug_code' => $code]);
            } catch(Exception $e) { throw new Exception("DB Error: Missing reset_token column."); }
        } else {
            echo json_encode(['success' => false, 'message' => 'If email exists, code sent.']);
        }
        exit;
    }

    if ($action === 'forgot_reset') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ?");
        $stmt->execute([$data['email'], $data['code']]);
        $user = $stmt->fetch();

        if ($user) {
            $new_hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL WHERE id = ?")->execute([$new_hash, $user['id']]);
            echo json_encode(['success' => true, 'message' => 'Password Reset Successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid Email or Code.']);
        }
        exit;
    }

    // --- LOGOUT ---
    if ($action === 'logout') {
        session_destroy();
        header("Location: ../index.html");
        exit;
    }

} catch (Exception $e) {
    ob_clean(); // Clear HTML errors
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
}
?>