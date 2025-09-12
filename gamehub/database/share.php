<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$post_id = isset($input['post_id']) ? intval($input['post_id']) : 0;
if (!$post_id) {
  echo json_encode(['success'=>false, 'error'=>'Invalid post_id']);
  exit;
}

$user = current_user($pdo);
$uid = $user ? $user['id'] : null;

try {
  $stmt = $pdo->prepare("INSERT INTO shares (user_id, post_id) VALUES (:uid, :pid)");
  $stmt->execute([':uid'=>$uid, ':pid'=>$post_id]);

  $cnt = $pdo->prepare("SELECT COUNT(*) AS c FROM shares WHERE post_id = :pid");
  $cnt->execute([':pid'=>$post_id]);
  $shares = (int)$cnt->fetch(PDO::FETCH_ASSOC)['c'];

  // build a share link (simple deep link to page with anchor)
  $share_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/index.php?shared_post=' . $post_id;

  echo json_encode(['success'=>true, 'shares'=>$shares, 'share_link'=>$share_link]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false, 'error'=>'Server error']);
}
