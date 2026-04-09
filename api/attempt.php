<?php
require_once 'db.php';

ini_set('display_errors', 0); 
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) throw new Exception('Session expired. Please log in again.');

    $user_id = $_SESSION['user_id'];
    $action = $_GET['action'] ?? null;

    // ==========================================
    // 1. START QUIZ
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'start') {
        $qid = $_GET['quiz_id'] ?? null;
        if (!$qid) throw new Exception('Missing Quiz ID.');

        // Verify the quiz exists
        $qStmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
        $qStmt->execute([$qid]);
        $quiz = $qStmt->fetch(PDO::FETCH_ASSOC);

        if (!$quiz) throw new Exception('Quiz not found in database.');

        // CHECK: Has the user already taken this quiz?
        $attemptCheck = $pdo->prepare("SELECT id FROM attempts WHERE quiz_id = ? AND user_id = ?");
        $attemptCheck->execute([$qid, $user_id]);
        if ($attemptCheck->fetch()) {
            throw new Exception('You have already completed this quiz. Multiple attempts are not allowed.');
        }

        // Check quiz timing
        $now = new DateTime();
        if (!empty($quiz['start_time'])) {
            $start = new DateTime($quiz['start_time']);
            if ($now < $start) throw new Exception('⏳ Quiz starts on ' . $start->format('d M Y, h:i A'));
        }

        if (!empty($quiz['end_time'])) {
            $end = new DateTime($quiz['end_time']);
            if ($now > $end) throw new Exception('⛔ Quiz expired on ' . $end->format('d M Y, h:i A'));
        }

        // Fetch questions
        $qtStmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ?");
        $qtStmt->execute([$qid]);
        $questions = $qtStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($questions)) throw new Exception('No questions have been added to this quiz yet. Please check back later.');

        shuffle($questions);

        echo json_encode(['success' => true, 'quiz' => $quiz, 'questions' => $questions]);
        exit;
    }

    // ==========================================
    // 2. SUBMIT & EVALUATE QUIZ
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $qid = $data['quiz_id'] ?? null;
        $answers = $data['answers'] ?? [];

        if (!$qid) throw new Exception('Missing Quiz ID.');

        // CHECK: Prevent submitting multiple times (e.g., if they opened two tabs)
        $attemptCheck = $pdo->prepare("SELECT id FROM attempts WHERE quiz_id = ? AND user_id = ?");
        $attemptCheck->execute([$qid, $user_id]);
        if ($attemptCheck->fetch()) {
            throw new Exception('You have already submitted this quiz.');
        }

        $stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ?");
        $stmt->execute([$qid]);
        $questions_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_score = 0;
        $max_marks = 0;

        foreach ($questions_db as $q) {
            $q_id = $q['id'] ?? $q['question_id'];
            $q_marks = $q['marks'] ?? 1;
            $q_correct = trim($q['correct_option'] ?? '');
            $q_type = strtoupper(trim($q['type'] ?? $q['question_type'] ?? ''));

            $max_marks += $q_marks;
            $user_ans = $answers[$q_id] ?? '';

            // --- 1. MCQ GRADING ---
            if ($q_type === 'MCQ') {
                if (strtolower(trim($user_ans)) === strtolower($q_correct)) {
                    $total_score += $q_marks;
                }
            } 
            
            // --- 2. MSQ GRADING ---
            elseif ($q_type === 'MSQ') {
                $correct_arr = json_decode($q_correct, true) ?? [];
                if (!is_array($correct_arr) && is_string($q_correct)) {
                    $correct_arr = array_map('trim', explode(',', $q_correct)); 
                }
                
                $user_arr = is_array($user_ans) ? array_map('trim', $user_ans) : [];
                
                $correct_arr = array_map('strtolower', $correct_arr);
                $user_arr = array_map('strtolower', $user_arr);

                sort($correct_arr); 
                sort($user_arr);
                
                if ($correct_arr == $user_arr) {
                    $total_score += $q_marks;
                }
            }
 
            // --- 3. DESCRIPTIVE GRADING (Smart Two-Step Match) ---
            else { 
                if (!empty($user_ans)) {
                    $student_text = strtolower(trim($user_ans));
                    $ai_keywords = strtolower(trim($q_correct));
                    
                    // STEP 1: Phrase Matching
                    if ($student_text === $ai_keywords || 
    (strpos($ai_keywords, $student_text) !== false && strlen($student_text) >= 20) ||
    (strpos($student_text, $ai_keywords) !== false && strlen($ai_keywords) >= 20)) {
    
    // This awards full marks if a significant phrase (20+ chars) matches exactly
    $total_score += $q_marks;
}
                    // STEP 2: Keyword Matching
                    else {
                        $ai_clean = str_replace([',', '.', ';', ':', "\n", "\r"], ' ', $ai_keywords);
                        $raw_words = explode(' ', $ai_clean);
                        
                        $stop_words = ['the', 'and', 'are', 'you', 'for', 'that', 'this', 'with', 'from', 'can', 'not', 'have', 'but', 'will', 'what', 'how', 'why', 'when'];
                        
                        $valid_keywords = [];
                        foreach ($raw_words as $w) {
                            $w = trim($w);
                            if (strlen($w) > 2 && !in_array($w, $stop_words)) {
                                $valid_keywords[] = $w;
                            }
                        }
                        
                        if (count($valid_keywords) > 0) {
                            $match_count = 0;
                            foreach ($valid_keywords as $word) {
                                if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $student_text)) {
                                    $match_count++;
                                }
                            }

                            // Require 40% of technical keywords to match
                            $required_matches = max(1, ceil(count($valid_keywords) * 0.4));
                            if ($match_count >= $required_matches) {
                                $total_score += $q_marks;
                            }
                        }
                    }
                }
            }
        } 

        // Save attempt
        $saveStmt = $pdo->prepare("INSERT INTO attempts (quiz_id, user_id, score, total_marks, answers_json) VALUES (?, ?, ?, ?, ?)");
        $saveStmt->execute([$qid, $user_id, $total_score, $max_marks, json_encode($answers)]);

        echo json_encode(['success' => true, 'score' => $total_score, 'total' => $max_marks]);
        exit;
    }

    // Catch missing actions
    throw new Exception("Invalid action requested.");

} catch (Throwable $e) { 
    http_response_code(200); 
    // Removed "System Error:" so messages look cleaner to the user
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>