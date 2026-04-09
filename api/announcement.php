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

$role = $_SESSION['role'];
$uid = $_SESSION['user_id'];
$action = $_GET['action'] ?? null;

try {

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
   
        $query = "SELECT a.*, u.name as author, u.department as author_dept, (a.created_by = ?) as is_mine 
                  FROM announcements a 
                  JOIN users u ON a.created_by = u.id";
        $params = [$uid];

        if ($role === 'student') {
            $query .= " WHERE a.target_dept = ? AND a.target_sem = ?";
            $params[] = $_SESSION['dept'];
            $params[] = $_SESSION['sem'];
        }
        
        $query .= " ORDER BY a.created_at DESC LIMIT 20";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'announcements' => $stmt->fetchAll()]);
        exit;
    }

   
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create' && $role === 'teacher') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['title']) || empty($data['message'])) throw new Exception("Title and Message required.");

        $expiry = !empty($data['expires_at']) ? str_replace('T', ' ', $data['expires_at']) : null;

        $stmt = $pdo->prepare("INSERT INTO announcements (title, message, target_dept, target_sem, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$data['title'], $data['message'], $data['target_dept'], $data['target_sem'], $expiry, $uid]);
        
        echo json_encode(['success' => true, 'message' => 'Posted!']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && $role === 'teacher') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['id'])) throw new Exception("ID required.");

        $expiry = !empty($data['expires_at']) ? str_replace('T', ' ', $data['expires_at']) : null;

        $stmt = $pdo->prepare("UPDATE announcements SET title=?, message=?, target_dept=?, target_sem=?, expires_at=? WHERE id=? AND created_by=?");
        $stmt->execute([$data['title'], $data['message'], $data['target_dept'], $data['target_sem'], $expiry, $data['id'], $uid]);
        
        echo json_encode(['success' => true, 'message' => 'Updated!']);
        exit;
    }

   
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $role === 'teacher') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ? AND created_by = ?");
        $stmt->execute([$data['id'], $uid]);
        echo json_encode(['success' => true, 'message' => 'Deleted.']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>