<?php
// login.php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $errors[] = 'Please fill both fields.';
    } else {
        // find user by username or email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // ✅ correct password
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: index.php");
            exit;
        } else {
            $errors[] = 'Invalid login credentials.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Login - GameHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-gray-800 p-6 rounded-lg">
      <h2 class="text-2xl font-bold mb-4 text-green-400">Login</h2>
      <?php if (!empty($errors)): ?>
        <div class="bg-red-700 p-3 rounded mb-3">
          <?= implode('', array_map(function($e){ return '<div>'.htmlspecialchars($e, ENT_QUOTES).'</div>'; }, $errors)); ?>
        </div>
      <?php endif; ?>
      <form method="post">
        <input class="w-full mb-2 px-3 py-2 bg-gray-700 rounded" name="identifier" placeholder="Username or Email" required>
        <input class="w-full mb-4 px-3 py-2 bg-gray-700 rounded" name="password" type="password" placeholder="Password" required>
        <button class="w-full py-2 bg-green-500 rounded hover:bg-green-600">Login</button>
      </form>
      <p class="mt-3 text-sm text-gray-400">Don't have an account? <a href="register.php" class="text-green-400">Register</a></p>
    </div>
  </div>
</body>
</html>
