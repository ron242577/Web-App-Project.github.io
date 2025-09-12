<?php
// create_post.php
require_once __DIR__ . '/db.php';

// ensure user is logged in
$user = current_user($pdo);
if (!$user) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$content = trim($_POST['content'] ?? '');

$errors = [];
if (!$title) $errors[] = 'Title is required.';
if (!$category) $errors[] = 'Category is required.';
if (!$content) $errors[] = 'Content is required.';

$mediaPath = null;
$mediaType = null;

if (!empty($_FILES['media']) && $_FILES['media']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['media'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error.';
    } else {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $imageExts = ['jpg','jpeg','png','gif'];
        $videoExts = ['mp4','webm','ogg'];
        if (in_array($ext, $imageExts)) {
            $mediaType = 'image';
            $max = 10 * 1024 * 1024; // 10MB
        } elseif (in_array($ext, $videoExts)) {
            $mediaType = 'video';
            $max = 50 * 1024 * 1024; // 50MB
        } else {
            $errors[] = 'Unsupported media type.';
        }

        if (empty($errors)) {
            if ($f['size'] > $max) {
                $errors[] = 'File too large.';
            } else {
                $uploadsDir = __DIR__ . '/uploads';
                if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
                $basename = bin2hex(random_bytes(8)) . '.' . $ext;
                $dst = $uploadsDir . '/' . $basename;
                if (!move_uploaded_file($f['tmp_name'], $dst)) {
                    $errors[] = 'Could not save uploaded file.';
                } else {
                    // store path relative to project root
                    $mediaPath = 'uploads/' . $basename;
                }
            }
        }
    }
}

if (!empty($errors)) {
    // For simplicity redirect back with first error as query param
    $msg = urlencode($errors[0]);
    header('Location: index.php?error=' . $msg);
    exit;
}

try {
    // try to ensure posts table exists (no-op if already present)
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        user_id INTEGER NOT NULL,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(100) DEFAULT NULL,
        content TEXT NOT NULL,
        media VARCHAR(500) DEFAULT NULL,
        media_type VARCHAR(16) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $stm = $pdo->prepare("INSERT INTO posts (user_id, title, category, content, media, media_type, created_at) VALUES (:uid, :title, :cat, :content, :media, :mtype, NOW())");
    $stm->execute([
        ':uid' => $user['id'],
        ':title' => $title,
        ':cat' => $category,
        ':content' => $content,
        ':media' => $mediaPath,
        ':mtype' => $mediaType
    ]);

    header('Location: index.php');
    exit;
} catch (Exception $e) {
    // log error server-side if available, then redirect with generic error
    header('Location: index.php?error=' . urlencode('Could not create post'));
    exit;
}
