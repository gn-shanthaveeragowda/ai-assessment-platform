<?php
session_start(); 
require_once 'db.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$teacher_id = $_SESSION['user_id'] ?? null;
if (!$teacher_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User ID missing from session']);
    exit;
}

$action = $_GET['action'] ?? null;

try {
    // --- ACTION: LIST QUESTIONS ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $qid = $_GET['quiz_id'] ?? null;
        if (!$qid) throw new Exception("Quiz ID is required.");

        // FIX: Use $teacher_id instead of $uid
        $check = $pdo->prepare("SELECT id FROM quizzes WHERE id = ? AND created_by = ?");
        $check->execute([$qid, $teacher_id]);
        
        if (!$check->fetch()) {
            throw new Exception("Unauthorized: You do not own Quiz ID " . $qid);
        }

        $stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ?");
        $stmt->execute([$qid]);
        echo json_encode(['success' => true, 'questions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- ADD QUESTION ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) throw new Exception("Invalid JSON received");

        if (empty(trim($data['question_text']))) throw new Exception("Question text cannot be empty.");
        
        $type = $data['type'] ?? 'MCQ';
        $correct = $data['correct_option'] ?? '';

        if ($type === 'MSQ') {
            if (is_array($correct) && !empty($correct)) {
                sort($correct); 
                $correct = json_encode($correct);
            } else {
                throw new Exception("MSQ requires at least one correct option selected.");
            }
        } elseif ($type === 'DESCRIPTIVE') {
            if (empty(trim($correct))) throw new Exception("Descriptive answer cannot be empty.");
            $data['option_a'] = $data['option_b'] = $data['option_c'] = $data['option_d'] = '';
        }

        // FIX: Match variable name $checkStmt and use created_by
        $checkStmt = $pdo->prepare("SELECT id FROM quizzes WHERE id = ? AND created_by = ?");    
        $checkStmt->execute([$data['quiz_id'], $teacher_id]);
        if (!$checkStmt->fetch()) {
            throw new Exception("Unauthorized: You cannot add questions to a quiz you don't own.");
        }

        $stmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['quiz_id'], 
            trim($data['question_text']),
            $data['option_a'] ?? '', 
            $data['option_b'] ?? '', 
            $data['option_c'] ?? '', 
            $data['option_d'] ?? '', 
            $correct, 
            $data['marks'] ?? 1,
            $type
        ]);
        echo json_encode(['success' => true, 'message' => 'Question added!']);
        exit;
    }

    // --- DELETE QUESTION ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['question_id'])) throw new Exception("Question ID required.");

        // FIX: Use created_by instead of teacher_id
        $checkStmt = $pdo->prepare("
            SELECT qz.id 
            FROM questions q 
            JOIN quizzes qz ON q.quiz_id = qz.id 
            WHERE q.id = ? AND qz.created_by = ?
        ");
        $checkStmt->execute([$data['question_id'], $teacher_id]);
        if (!$checkStmt->fetch()) {
             throw new Exception("Unauthorized: You cannot delete a question from a quiz you don't own.");
        }

        $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$data['question_id']]);
        echo json_encode(['success' => true, 'message' => 'Question deleted successfully.']);
        exit;
    }

    throw new Exception("Invalid action or request method.");

} catch (Exception $e) { 
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}