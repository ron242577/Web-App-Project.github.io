<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$user = current_user($pdo);
if (!$user) {
  http_response_code(401);
  echo json_encode(['success'=>false, 'error'=>'Not authenticated']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$post_id = isset($input['post_id']) ? intval($input['post_id']) : 0;
if (!$post_id) {
  echo json_encode(['success'=>false, 'error'=>'Invalid post_id']);
  exit;
}

try {
  // check if like exists
  $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = :uid AND post_id = :pid");
  $stmt->execute([':uid'=>$user['id'], ':pid'=>$post_id]);
  $exists = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($exists) {
    $del = $pdo->prepare("DELETE FROM likes WHERE id = :id");
    $del->execute([':id'=>$exists['id']]);
    $liked = false;
  } else {
    $ins = $pdo->prepare("INSERT INTO likes (user_id, post_id) VALUES (:uid, :pid)");
    $ins->execute([':uid'=>$user['id'], ':pid'=>$post_id]);
    $liked = true;
  }

  $cnt = $pdo->prepare("SELECT COUNT(*) AS c FROM likes WHERE post_id = :pid");
  $cnt->execute([':pid'=>$post_id]);
  $likes = (int)$cnt->fetch(PDO::FETCH_ASSOC)['c'];

  echo json_encode(['success'=>true, 'liked'=>$liked, 'likes'=>$likes]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false, 'error'=>'Server error']);
}
