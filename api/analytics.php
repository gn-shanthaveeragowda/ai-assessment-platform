<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) send_json(['success' => false], 403);
$action = $_GET['action'] ?? null;


if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'leaderboard') {
    $qid = $_GET['quiz_id'];

    $stmt = $pdo->prepare("
        SELECT u.name, a.score, a.total_marks, a.completed_at 
        FROM attempts a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.quiz_id = ? 
        ORDER BY a.score DESC, a.completed_at ASC 
        LIMIT 10
    ");
    $stmt->execute([$qid]);
    send_json(['success' => true, 'leaderboard' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'quiz_stats') {
    if ($_SESSION['role'] !== 'teacher') send_json(['success' => false], 403);
    $qid = $_GET['quiz_id'];

    $basic = $pdo->prepare("SELECT COUNT(*) as attempts, AVG(score) as avg_score, MAX(score) as max_score, MIN(score) as min_score FROM attempts WHERE quiz_id = ?");
    $basic->execute([$qid]);
    $stats = $basic->fetch();

    $scoresStmt = $pdo->prepare("SELECT score, total_marks FROM attempts WHERE quiz_id = ?");
    $scoresStmt->execute([$qid]);
    $all_scores = $scoresStmt->fetchAll();

    send_json(['success' => true, 'stats' => $stats, 'scores' => $all_scores]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'student_results') {
    if ($_SESSION['role'] !== 'teacher') send_json(['success' => false], 403);
    $qid = $_GET['quiz_id'];
    
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, a.score, a.total_marks, a.completed_at 
        FROM attempts a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.quiz_id = ? 
        ORDER BY u.name ASC
    ");
    $stmt->execute([$qid]);
    send_json(['success' => true, 'results' => $stmt->fetchAll()]);
}
?>