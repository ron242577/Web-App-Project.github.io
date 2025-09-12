<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$user = current_user($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
    if (!$post_id) {
        echo json_encode(['success'=>false, 'error'=>'Invalid post_id']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT c.*, u.username 
                               FROM comments c 
                               JOIN users u ON c.user_id = u.id 
                               WHERE c.post_id = :pid 
                               ORDER BY c.created_at DESC 
                               LIMIT 50");
        $stmt->execute([':pid'=>$post_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success'=>true, 'comments'=>$comments]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        echo json_encode(['success'=>false, 'error'=>'Not logged in']);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);
    $post_id = intval($data['post_id'] ?? 0);
    $content = trim($data['content'] ?? '');

    if (!$post_id || !$content) {
        echo json_encode(['success'=>false, 'error'=>'Post ID and content required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO comments (user_id, post_id, content) VALUES (:uid, :pid, :content)");
        $stmt->execute([
            ':uid' => $user['id'],
            ':pid' => $post_id,
            ':content' => $content
        ]);

        echo json_encode(['success'=>true, 'message'=>'Comment added']);
    } catch (Exception $e) {
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    }
}
