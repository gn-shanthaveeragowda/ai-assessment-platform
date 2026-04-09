<?php
session_start();
require_once 'db.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

/* ---------------- AUTH ---------------- */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$uid    = (int) $_SESSION['user_id'];
$role   = strtolower($_SESSION['role'] ?? '');
$action = $_GET['action'] ?? '';

try {

    /* =========================================================
       1. LIST QUIZZES (RESTORED ORIGINAL LOGIC)
    ========================================================= */
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {

        $quizzes = [];

        /* ---- TEACHER ---- */
        if ($role === 'teacher') {
            $stmt = $pdo->prepare("
                SELECT q.*,
                       COALESCE(s.name, 'No Subject') AS subject_name,
                       (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS q_count
                FROM quizzes q
                LEFT JOIN subjects s ON q.subject_id = s.id
                WHERE q.created_by = ?
                ORDER BY q.created_at DESC
            ");
            $stmt->execute([$uid]);
            $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /* ---- STUDENT ---- */
        elseif ($role === 'student') {
            $stmt = $pdo->prepare("
                SELECT q.*, u.name AS author, s.name AS subject_name
                FROM quizzes q
                JOIN users u ON q.created_by = u.id
                LEFT JOIN subjects s ON q.subject_id = s.id
                WHERE q.is_published = 1
                ORDER BY q.created_at DESC
            ");
            $stmt->execute();
            $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /* ---- SUBJECTS (FOR DROPDOWN) ---- */
        $subjectsStmt = $pdo->query("
            SELECT id, name
            FROM subjects
            ORDER BY name
        ");
        $subjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'  => true,
            'quizzes'  => $quizzes,
            'subjects' => $subjects
        ]);
        exit;
    }

    /* =========================================================
       2. SAVE QUIZ (RESTORED CREATE / UPDATE)
    ========================================================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {

        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
            exit;
        }

        $title = trim($data['title'] ?? '');
        if ($title === '') {
            echo json_encode(['success' => false, 'message' => 'Quiz title required']);
            exit;
        }

        $desc  = $data['description'] ?? '';
        $dur   = (int) ($data['duration'] ?? 10);
        
        // FIX: Ensure empty dropdown value becomes NULL for DB
        $sub   = !empty($data['subject_id']) ? $data['subject_id'] : null;
        $qid   = $data['quiz_id'] ?? null;
        
        // ADDED: Logic for is_published (defaults to 1 if not sent)
        $is_pub = isset($data['is_published']) ? (int)$data['is_published'] : 1;

        $start = !empty($data['start_time']) ? str_replace('T',' ',$data['start_time']) : null;
        $end   = !empty($data['end_time'])   ? str_replace('T',' ',$data['end_time'])   : null;

        /* ---- UPDATE ---- */
        if ($qid) {
            $stmt = $pdo->prepare("
                UPDATE quizzes
                SET title=?, description=?, subject_id=?, duration_minutes=?, start_time=?, end_time=?, is_published=?
                WHERE id=? AND created_by=?
            ");
            $stmt->execute([$title,$desc,$sub,$dur,$start,$end,$is_pub,$qid,$uid]);

            echo json_encode(['success'=>true,'message'=>'Quiz updated','quiz_id'=>$qid]);
            exit;
        }

        /* ---- CREATE ---- */
        $stmt = $pdo->prepare("
            INSERT INTO quizzes
            (title, description, subject_id, duration_minutes, start_time, end_time, is_published, created_by)
            VALUES (?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([$title,$desc,$sub,$dur,$start,$end,$is_pub,$uid]);

        echo json_encode([
            'success' => true,
            'message' => 'Quiz created',
            'quiz_id' => $pdo->lastInsertId()
        ]);
        exit;
    }

    /* =========================================================
       3. DELETE QUIZ (RESTORED ORIGINAL)
    ========================================================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['quiz_id'])) {
            echo json_encode(['success'=>false,'message'=>'Quiz ID missing']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id=? AND created_by=?");
        $stmt->execute([$data['quiz_id'], $uid]);

        echo json_encode(['success'=>true,'message'=>'Quiz deleted']);
        exit;
    }

    /* =========================================================
       FALLBACK (RESTORED ORIGINAL DEBUG INFO)
    ========================================================= */
    echo json_encode([
        'success'=>false,
        'message'=>'Invalid request',
        'debug'=>[
            'method'=>$_SERVER['REQUEST_METHOD'],
            'action'=>$action,
            'role'=>$role
        ]
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'success'=>false,
        'message'=>'Server error',
        'error'=>$e->getMessage()
    ]);
    exit;
}