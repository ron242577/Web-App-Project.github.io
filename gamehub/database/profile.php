<?php
require_once __DIR__ . '/db.php';

// Allow lookup by username or user_id
$userParam = $_GET['username'] ?? null;
$idParam = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

$profileUser = null;
if ($userParam) {
    $stmt = $pdo->prepare('SELECT id, username, email, avatar FROM users WHERE username = ?');
    $stmt->execute([ $userParam ]);
    $profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($idParam) {
    $stmt = $pdo->prepare('SELECT id, username, email, avatar FROM users WHERE id = ?');
    $stmt->execute([ $idParam ]);
    $profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$profileUser) {
    http_response_code(404);
    echo "User not found.";
    exit;
}

$current = current_user($pdo);

// fetch counts and posts
$countsStmt = $pdo->prepare('SELECT
    (SELECT COUNT(*) FROM posts WHERE user_id = ?) AS posts_count,
    (SELECT COUNT(*) FROM likes l JOIN posts p ON l.post_id = p.id WHERE p.user_id = ?) AS likes_received
');
$countsStmt->execute([ $profileUser['id'], $profileUser['id'] ]);
$counts = $countsStmt->fetch(PDO::FETCH_ASSOC);

$postsStmt = $pdo->prepare('SELECT p.*, (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS likes_count, (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comments_count FROM posts p WHERE p.user_id = ? ORDER BY p.created_at DESC');
$postsStmt->execute([ $profileUser['id'] ]);
$posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Profile - <?=htmlspecialchars($profileUser['username'])?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-900 text-gray-100 font-sans">

  <div class="min-h-screen p-6">
    <a href="index.php" class="text-sm text-green-400">← Back to home</a>
    <div class="mt-4 bg-gray-800 p-6 rounded-xl shadow">
      <div class="flex items-center gap-4">
        <?php if (!empty($profileUser['avatar'])): ?>
          <img src="<?=htmlspecialchars($profileUser['avatar'])?>" alt="avatar" class="w-24 h-24 rounded-full object-cover">
        <?php else: ?>
          <div class="w-24 h-24 rounded-full bg-gray-700 flex items-center justify-center text-2xl text-gray-300"><?=strtoupper(substr($profileUser['username'],0,1))?></div>
        <?php endif; ?>
        <div>
          <h1 class="text-2xl font-bold text-green-400"><?=htmlspecialchars($profileUser['username'])?></h1>
          <?php if ($current && $current['id'] === $profileUser['id']): ?>
            <div class="text-sm text-gray-300">Email: <?=htmlspecialchars($profileUser['email'])?></div>
          <?php endif; ?>
          <div class="text-sm text-gray-400 mt-2">
            <strong><?=intval($counts['posts_count'] ?? 0)?></strong> posts • <strong><?=intval($counts['likes_received'] ?? 0)?></strong> likes received
          </div>
        </div>
      </div>

      
    </div>

    <div class="mt-6 space-y-4">
      <?php if (empty($posts)): ?>
        <div class="bg-gray-800 p-4 rounded-xl shadow text-gray-400">No posts yet.</div>
      <?php endif; ?>

      <?php foreach ($posts as $post): ?>
        <article class="bg-gray-800 p-4 rounded-xl shadow">
          <h3 class="text-lg font-bold"><?=htmlspecialchars($post['title'])?></h3>
          <p class="text-xs text-gray-400">Posted <?=htmlspecialchars($post['created_at'])?> • <span class="text-green-400"><?=htmlspecialchars($post['category'])?></span></p>
          <?php if (!empty($post['media'])): ?>
            <?php if (($post['media_type'] ?? '') === 'image'): ?>
              <img src="<?=htmlspecialchars($post['media'])?>" class="w-full h-64 object-cover mt-3 rounded-lg" alt="post image">
            <?php else: ?>
              <video src="<?=htmlspecialchars($post['media'])?>" controls class="w-full h-64 object-cover mt-3 rounded-lg"></video>
            <?php endif; ?>
          <?php endif; ?>
          <p class="mt-3 text-gray-200"><?=nl2br(htmlspecialchars($post['content']))?></p>
          <div class="flex items-center gap-4 mt-3 text-gray-400">
            <div>👍 <?=intval($post['likes_count'] ?? 0)?></div>
            <div>💬 <?=intval($post['comments_count'] ?? 0)?></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
