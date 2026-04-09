<?php
require_once 'db.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$uid = $_SESSION['user_id'];
$action = $_GET['action'] ?? null;

try {
    // --- SUBMIT FEEDBACK (Student) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['quiz_id']) || empty($data['rating'])) {
            throw new Exception("Rating is required.");
        }

        // Check if already submitted
        $check = $pdo->prepare("SELECT id FROM feedback WHERE quiz_id = ? AND user_id = ?");
        $check->execute([$data['quiz_id'], $uid]);
        if ($check->rowCount() > 0) {
            throw new Exception("You have already rated this quiz.");
        }

        $stmt = $pdo->prepare("INSERT INTO feedback (quiz_id, user_id, rating, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['quiz_id'], $uid, $data['rating'], $data['message'] ?? '']);
        
        echo json_encode(['success' => true, 'message' => 'Thank you for your feedback!']);
        exit;
    }

    // --- LIST FEEDBACK (Teacher) ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $quiz_id = $_GET['quiz_id'];
        
        // Verify ownership (optional security step)
        // $ownerCheck = $pdo->prepare("SELECT created_by FROM quizzes WHERE id = ?"); ...

        $stmt = $pdo->prepare("
            SELECT f.*, u.name as student_name 
            FROM feedback f 
            JOIN users u ON f.user_id = u.id 
            WHERE f.quiz_id = ? 
            ORDER BY f.rating DESC, f.created_at DESC
        ");
        $stmt->execute([$quiz_id]);
        
        echo json_encode(['success' => true, 'feedback' => $stmt->fetchAll()]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>