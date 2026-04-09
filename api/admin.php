<?php
require_once 'db.php';

// Prevent HTML errors affecting JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$action = $_GET['action'] ?? null;

try {
    // --- 1. DASHBOARD DATA ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'dashboard_data') {
        
        // Fetch recent login logs
        $logs = $pdo->query("SELECT u.name, u.role, l.login_time FROM login_logs l JOIN users u ON l.user_id = u.id ORDER BY l.login_time DESC LIMIT 5")->fetchAll();
        
        // Fetch recent teacher IDs
        // Ensure we select all columns explicitly for clarity
        $ids = $pdo->query("SELECT id, code, department, assigned_to, is_used FROM teacher_ids ORDER BY id DESC LIMIT 10")->fetchAll();
        
        // Fetch subjects
        $subjects = $pdo->query("SELECT * FROM subjects ORDER BY department, semester")->fetchAll();
   
        // Fetch departments
        $depts = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
        
        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'teacher_ids' => $ids,
            'subjects' => $subjects,
            'departments' => $depts
        ]);
        exit;
    }

    // --- 2. ADD DEPARTMENT ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_dept') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['name'])) {
            throw new Exception('Name required.');
        }
        
        $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
        $stmt->execute([strtoupper(trim($data['name']))]);
        echo json_encode(['success' => true, 'message' => 'Department added successfully.']);
        exit;
    }

    // --- 3. ADD SUBJECT ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_subject') {
        $data = json_decode(file_get_contents('php://input'), true);
    
        if (empty($data['name']) || empty($data['code']) || empty($data['department']) || empty($data['semester'])) {
            throw new Exception('All subject fields are required.');
        }

        $stmt = $pdo->prepare("INSERT INTO subjects (name, code, department, semester) VALUES (?, ?, ?, ?)");
        $stmt->execute([trim($data['name']), strtoupper(trim($data['code'])), $data['department'], $data['semester']]);
        echo json_encode(['success' => true, 'message' => 'Subject added successfully.']);
        exit;
    }

    // --- 4. ASSIGN TEACHER ID ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'assign_id') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['code']) || empty($data['assigned_to']) || empty($data['department'])) {
            throw new Exception('All fields, including Department, are required.');
        }

        $stmt = $pdo->prepare("INSERT INTO teacher_ids (code, assigned_to, department) VALUES (?, ?, ?)");
        $stmt->execute([trim($data['code']), trim($data['assigned_to']), $data['department']]);
        echo json_encode(['success' => true, 'message' => 'Teacher ID assigned for ' . $data['department']]);
        exit;
    }

} catch (PDOException $e) {
    // Handle specific SQL errors
    if ($e->getCode() == 23000) { // Duplicate entry
        echo json_encode(['success' => false, 'message' => 'Duplicate entry found (Code or Name already exists).']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>