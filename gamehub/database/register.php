<?php
// register.php
require_once 'db.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$email || !$password) {
        $errors[] = "All fields are required.";
    }

    if (empty($errors)) {
        // check duplicates
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Username or email already exists.";
        } else {
            // ✅ HASH PASSWORD HERE
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword]);

            header("Location: login.php");
            exit;
        }
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register - GameHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-gray-800 p-6 rounded-lg">
      <h2 class="text-2xl font-bold mb-4 text-green-400">Create account</h2>
      <?php if ($errors): ?>
        <div class="bg-red-700 p-3 rounded mb-3">
          <?php foreach ($errors as $e) echo "<div>$e</div>"; ?>
        </div>
      <?php endif; ?>
      <form method="post">
        <input class="w-full mb-2 px-3 py-2 bg-gray-700 rounded" name="username" placeholder="Username" required>
        <input class="w-full mb-2 px-3 py-2 bg-gray-700 rounded" name="email" type="email" placeholder="Email" required>
        <input class="w-full mb-4 px-3 py-2 bg-gray-700 rounded" name="password" type="password" placeholder="Password" required>
        <button class="w-full py-2 bg-green-500 rounded hover:bg-green-600">Register</button>
      </form>
      <p class="mt-3 text-sm text-gray-400">Already have an account? <a href="login.php" class="text-green-400">Login</a></p>
    </div>
  </div>
</body>
</html>
