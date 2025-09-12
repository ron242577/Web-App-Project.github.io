<?php
// index.php

require_once __DIR__ . '/db.php';

// ensure support tables exist (likes, comments, shares)
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS likes (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id INTEGER NOT NULL,
    post_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_like (user_id, post_id)
  )");

  $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id INTEGER NOT NULL,
    post_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )");

  $pdo->exec("CREATE TABLE IF NOT EXISTS shares (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id INTEGER,
    post_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )");
} catch (Exception $e) {
  // ignore table creation errors for minimal change
}

// handle search query (searches titles and categories)
$q = null;
if (!empty($_GET['q'])) {
  $q = trim($_GET['q']);
}

if ($q) {
  $like = "%" . $q . "%";
  // include counts via subqueries
  $stmt = $pdo->prepare("SELECT p.*, u.username,
    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS likes_count,
    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comments_count,
    (SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id) AS shares_count
    FROM posts p JOIN users u ON p.user_id = u.id
    WHERE p.title LIKE :q OR p.category LIKE :q
    ORDER BY p.created_at DESC");
  $stmt->execute([':q' => $like]);
} else {
  // fetch posts (latest first) with username + counts
  $stmt = $pdo->query("SELECT p.*, u.username,
    (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS likes_count,
    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comments_count,
    (SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id) AS shares_count
    FROM posts p JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC");
}
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user = current_user($pdo);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>GameHub - Gaming Blog Platform</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-900 text-gray-100 font-sans h-screen flex flex-col">

  <!-- Navbar -->
  <header class="flex items-center justify-between p-4 bg-gray-800 shadow flex-none">
    <div class="flex items-center gap-4">
      <h1 class="text-2xl font-bold text-green-400">GameHub</h1>
      <nav class="hidden md:flex gap-3 text-sm text-gray-300">
        <a class="hover:text-green-400" href="index.php">Home</a>
        <a class="hover:text-green-400" href="#">News</a>
        <a class="hover:text-green-400" href="#">Guides</a>
      </nav>
    </div>

    <div class="flex items-center space-x-4">
      <form method="get" action="index.php" class="">
        <input name="q" type="text" placeholder="Search GameHub" value="<?= isset($q) ? htmlspecialchars($q) : '' ?>" class="px-3 py-1 rounded-lg bg-gray-700 text-gray-200">
      </form>
      
      <!-- Profile icon & dropdown -->
      <?php if ($user): ?>
        <div class="relative" x-data>
          <button id="profileBtn" class="p-2 bg-gray-700 rounded-full hover:bg-gray-600" onclick="toggleDropdown()">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 19.5a8.25 8.25 0 0115 0v.75H4.5v-.75z" />
            </svg>
          </button>
          <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-56 bg-gray-800 rounded shadow p-2 text-sm">
            <div class="px-2 py-1 text-gray-300">Signed in as <strong><?=htmlspecialchars($user['username'])?></strong></div>
            <a href="profile.php?username=<?=urlencode($user['username'])?>" class="block px-2 py-1 hover:bg-gray-700 rounded">View profile</a>
            <a href="logout.php" class="block px-2 py-1 hover:bg-gray-700 rounded">Logout</a>
            <div class="border-t border-gray-700 my-2"></div>
            <!-- Theme toggle -->
            <div class="px-2 py-1 text-gray-300">Theme</div>
            <div class="px-2 py-1 flex items-center justify-between">
              <div class="text-sm text-gray-300">Light</div>
              <button id="themeBtn" class="theme-toggle" aria-pressed="false" onclick="toggleThemeFromBtn()" title="Toggle light / dark"></button>
            </div>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="px-4 py-2 bg-green-500 rounded-lg text-sm hover:bg-green-600">Login</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Main: left sidebar (fixed), center feed (scrollable), right sidebar (fixed) -->
  <main class="flex-1 flex p-6 overflow-hidden">
    <!-- Sidebar (left) -->
    <aside class="w-2/12 bg-gray-800 p-4 rounded-xl shadow flex-none">
      <h2 class="font-bold mb-3 text-lg">Categories</h2>
      <ul class="space-y-2 text-gray-300">
      <?php
      // build a unique categories list from the currently fetched posts
      $categories = [];
      foreach ($posts as $p) {
        if (!empty($p['category'])) $categories[] = $p['category'];
      }
      $categories = array_values(array_unique($categories));
      if (empty($categories)) {
        echo '<li class="text-gray-500">No categories</li>';
      } else {
        foreach ($categories as $cat) {
          $url = 'index.php?q=' . urlencode($cat);
          echo '<li><a href="' . htmlspecialchars($url) . '" class="hover:text-green-400">' . htmlspecialchars($cat) . '</a></li>';
        }
      }
      ?>
      </ul>
    </aside>

    <!-- Main Feed (ONLY this column scrolls) -->
    <section class="flex-1 px-6 overflow-y-auto space-y-6">
      <?php if ($user): ?>
        <!-- Create post form -->
        <div class="bg-gray-800 p-4 rounded-xl shadow">
          <h3 class="text-lg font-bold">Create Post</h3>
          <form action="create_post.php" method="post" enctype="multipart/form-data" class="mt-3 space-y-2">
            <input name="title" class="w-full px-3 py-2 bg-gray-700 rounded" placeholder="Post title" required>
            <select name="category" class="w-full px-3 py-2 bg-gray-700 rounded" required>
              <option value="FPS">FPS</option>
              <option value="MMORPG">MMORPG</option>
              <option value="MOBA">MOBA</option>
              <option value="Battle Royale">Battle Royale</option>
              <option value="Indie">Indie</option>
              <option value="Strategy">Just Random</option>
            </select>
            <textarea name="content" rows="3" class="w-full px-3 py-2 bg-gray-700 rounded" placeholder="Write something..." required></textarea>

            <!-- new file input -->
            <div>
              <label class="text-sm text-gray-300">Attach image or video (optional)</label>
              <input type="file" name="media" accept="image/*,video/*" class="w-full mt-1 text-sm text-gray-300">
              <div class="text-xs text-gray-500 mt-1">Images ≤10MB, Videos ≤50MB. Allowed: jpg, png, gif, mp4, webm, ogg.</div>
            </div>

            <div class="flex gap-2">
              <button class="px-4 py-2 bg-green-500 rounded hover:bg-green-600">Post</button>
              <a href="logout.php" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600">Cancel</a>
            </div>
          </form>
        </div>
        <?php else: ?>
        <div class="bg-gray-800 p-4 rounded-xl shadow">
          <p class="text-gray-300">Log in to create posts.</p>
          <a href="login.php" class="inline-block mt-3 px-4 py-2 bg-green-500 rounded hover:bg-green-600">Login</a>
        </div>
        <?php endif; ?>

        <!-- Search status -->
        <?php if ($q): ?>
          <div class="text-sm text-gray-400">Showing results for "<strong class="text-green-400"><?=htmlspecialchars($q)?></strong>"</div>
        <?php endif; ?>

        <!-- Posts listing -->
        <?php if (empty($posts)): ?>
          <div class="bg-gray-800 p-4 rounded-xl shadow text-gray-400">No posts found.</div>
        <?php endif; ?>

        <?php foreach ($posts as $post): ?>
        <article class="bg-gray-800 p-4 rounded-xl shadow" data-post-id="<?=intval($post['id'])?>">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="text-lg font-bold"><?=htmlspecialchars($post['title'])?></h3>
              <p class="text-gray-400 text-sm">Posted <?=htmlspecialchars($post['created_at'])?> in <span class="text-green-400"><?=htmlspecialchars($post['category'])?></span> by <strong><a class="hover:text-green-400" href="profile.php?username=<?=urlencode($post['username'])?>"><?=htmlspecialchars($post['username'])?></a></strong></p>
            </div>
          </div>

          <!-- render media if present -->
          <?php
            $media = isset($post['media']) ? trim($post['media']) : '';
            $media_type = isset($post['media_type']) ? $post['media_type'] : '';
            if ($media) {
              $media_url = htmlspecialchars($media);
              if (!$media_type) {
                // infer from extension
                if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $media)) $media_type = 'image';
                else $media_type = 'video';
              }
              if ($media_type === 'image') {
                // increased height for images (was h-36)
                echo '<img src="'. $media_url .'" class="w-full h-64 md:h-96 object-cover mt-3 rounded-lg" alt="post image">';
              } else {
                // increased height for videos (was h-36)
                echo '<video src="'. $media_url .'" controls class="w-full h-64 md:h-96 object-cover mt-3 rounded-lg"></video>';
              }
            }
          ?>

          <p class="mt-3 text-gray-200"><?=nl2br(htmlspecialchars($post['content']))?></p>
          <div class="flex justify-between mt-3 text-gray-400 items-center">
            <div class="flex items-center gap-4">
              <button class="like-btn px-3 py-1 rounded bg-gray-700 hover:bg-gray-600 text-sm" data-post-id="<?=intval($post['id'])?>" aria-pressed="false">
                <span class="like-emoji">👍</span> <span class="like-count"><?=intval($post['likes_count'] ?? 0)?></span>
              </button>
              <button class="comment-toggle-btn px-3 py-1 rounded bg-gray-700 hover:bg-gray-600 text-sm" data-post-id="<?=intval($post['id'])?>">
                💬 <span class="comment-count"><?=intval($post['comments_count'] ?? 0)?></span>
              </button>
              <button class="share-btn px-3 py-1 rounded bg-gray-700 hover:bg-gray-600 text-sm" data-post-id="<?=intval($post['id'])?>">
                🔗 Share <span class="share-count"><?=intval($post['shares_count'] ?? 0)?></span>
              </button>
            </div>
          </div>

          <!-- Comments area (hidden by default) -->
          <div class="comments-area mt-3 hidden bg-gray-900 p-3 rounded">
            <div class="comments-list space-y-2 text-sm text-gray-300">
              <!-- comments will be loaded here via AJAX -->
              <div class="text-xs text-gray-500">Loading comments...</div>
            </div>

            <?php if ($user): ?>
            <form class="comment-form mt-3" data-post-id="<?=intval($post['id'])?>">
              <textarea name="content" rows="2" required class="w-full px-2 py-1 bg-gray-800 rounded text-gray-100" placeholder="Write a comment..."></textarea>
              <div class="mt-2 flex gap-2">
                <button class="px-3 py-1 bg-green-500 rounded text-sm submit-comment-btn">Post Comment</button>
                <button type="button" class="px-3 py-1 bg-gray-700 rounded text-sm cancel-comment-btn">Cancel</button>
              </div>
            </form>
            <?php else: ?>
              <div class="mt-2 text-sm text-gray-400">Log in to comment. <a href="login.php" class="text-green-400">Login</a></div>
            <?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
    </section>

    <!-- Right Sidebar -->
    <aside class="w-3/12 bg-gray-800 p-4 rounded-xl shadow flex-none">
      <h2 class="font-bold mb-3 text-lg">Popular Communities</h2>
      <ul class="space-y-3 text-gray-300">
        <li><a href="#" class="flex justify-between hover:text-green-400">GamingNews <span>12M</span></a></li>
        <li><a href="#" class="flex justify-between hover:text-green-400">LeagueOfLegends <span>8.3M</span></a></li>
        <li><a href="#" class="flex justify-between hover:text-green-400">CallOfDuty <span>6.1M</span></a></li>
        <li><a href="#" class="flex justify-between hover:text-green-400">Genshin_Impact <span>5.2M</span></a></li>
        <li><a href="#" class="flex justify-between hover:text-green-400">IndieGames <span>2.4M</span></a></li>
      </ul>
    </aside>
  </main>

  <script>
    function toggleDropdown(){
      const d = document.getElementById('profileDropdown');
      if (!d) return;
      d.classList.toggle('hidden');
    }
    document.addEventListener('click', (e) => {
      const btn = document.getElementById('profileBtn');
      const d = document.getElementById('profileDropdown');
      if (!btn || !d) return;
      if (!btn.contains(e.target) && !d.contains(e.target)) d.classList.add('hidden');
    });
    // Theme handling: persist in localStorage and toggle class on body
    const THEME_KEY = 'gamehub_theme'; // 'dark' | 'light'

    function applyTheme(theme) {
      const body = document.body;
      const btn = document.getElementById('themeBtn');
      if (theme === 'light') {
        body.classList.add('light-mode');
        if (btn) { btn.classList.add('on'); btn.setAttribute('aria-pressed','true'); }
      } else {
        body.classList.remove('light-mode');
        if (btn) { btn.classList.remove('on'); btn.setAttribute('aria-pressed','false'); }
      }
    }

    function toggleThemeFromBtn(){
      const current = localStorage.getItem(THEME_KEY) || (document.body.classList.contains('light-mode') ? 'light' : 'dark');
      const next = current === 'light' ? 'dark' : 'light';
      localStorage.setItem(THEME_KEY, next);
      applyTheme(next);
    }

    // initialize theme on page load
    (function(){
      try {
        const stored = localStorage.getItem(THEME_KEY);
        if (stored === 'light' || stored === 'dark') {
          applyTheme(stored);
        } else {
          // default: follow prefers-color-scheme or dark fallback
          const prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
          applyTheme(prefersLight ? 'light' : 'dark');
        }
      } catch (e) {
        // ignore localStorage errors
      }
    })();

    async function apiPost(path, data) {
      const resp = await fetch(path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      return resp.json();
    }

    async function apiGet(path) {
      const resp = await fetch(path);
      return resp.json();
    }

    document.addEventListener('click', async (e) => {
      // Like button
      const likeBtn = e.target.closest('.like-btn');
      if (likeBtn) {
        const postId = likeBtn.getAttribute('data-post-id');
        try {
          const res = await apiPost('like.php', { post_id: postId });
          if (res && res.success) {
            likeBtn.querySelector('.like-count').textContent = res.likes;
            likeBtn.setAttribute('aria-pressed', res.liked ? 'true' : 'false');
            likeBtn.classList.toggle('bg-green-600', res.liked);
          } else {
            alert(res && res.error ? res.error : 'Could not toggle like.');
          }
        } catch (err) {
          console.error(err);
        }
        return;
      }

      // Comment toggle
      const commentToggle = e.target.closest('.comment-toggle-btn');
      if (commentToggle) {
        const postId = commentToggle.getAttribute('data-post-id');
        const article = document.querySelector('article[data-post-id="'+postId+'"]');
        const commentsArea = article.querySelector('.comments-area');
        if (commentsArea.classList.contains('hidden')) {
          commentsArea.classList.remove('hidden');
          await loadComments(postId, article);
        } else {
          commentsArea.classList.add('hidden');
        }
        return;
      }

      // Share button
      const shareBtn = e.target.closest('.share-btn');
      if (shareBtn) {
        const postId = shareBtn.getAttribute('data-post-id');
        try {
          const res = await apiPost('share.php', { post_id: postId });
          if (res && res.success) {
            shareBtn.querySelector('.share-count').textContent = res.shares;
            // copy share link to clipboard
            const link = res.share_link || (window.location.origin + window.location.pathname + '?shared_post=' + postId);
            await navigator.clipboard.writeText(link);
            const old = shareBtn.textContent;
            shareBtn.textContent = 'Copied!';
            setTimeout(() => { shareBtn.innerHTML = '🔗 Share <span class="share-count">'+res.shares+'</span>'; }, 1200);
          } else {
            alert(res && res.error ? res.error : 'Could not share.');
          }
        } catch (err) {
          console.error(err);
        }
        return;
      }

      // Cancel comment
      const cancelBtn = e.target.closest('.cancel-comment-btn');
      if (cancelBtn) {
        const form = cancelBtn.closest('.comment-form');
        if (form) {
          form.querySelector('textarea').value = '';
          form.closest('.comments-area').classList.add('hidden');
        }
        return;
      }
    });

    // handle comment submit via delegation
    document.addEventListener('submit', async (e) => {
      const form = e.target.closest('.comment-form');
      if (!form) return;
      e.preventDefault();
      const postId = form.getAttribute('data-post-id');
      const textarea = form.querySelector('textarea[name="content"]');
      const content = textarea.value.trim();
      if (!content) return;
      try {
        const res = await apiPost('comment.php', { post_id: postId, content });
        if (res && res.success) {
          // append comment to list
          const article = document.querySelector('article[data-post-id="'+postId+'"]');
          await loadComments(postId, article); // reload comments
          // update comment count badge
          const toggleBtn = article.querySelector('.comment-toggle-btn .comment-count') || article.querySelector('.comment-toggle-btn .comment-count');
          const countSpan = article.querySelector('.comment-toggle-btn .comment-count');
          if (countSpan) countSpan.textContent = (parseInt(countSpan.textContent||'0') + 1);
          textarea.value = '';
        } else {
          alert(res && res.error ? res.error : 'Could not post comment.');
        }
      } catch (err) {
        console.error(err);
      }
    });

    async function loadComments(postId, articleEl) {
      const list = articleEl.querySelector('.comments-list');
      if (!list) return;
      list.innerHTML = '<div class="text-xs text-gray-500">Loading comments...</div>';
      try {
        const res = await apiGet('comment.php?post_id=' + encodeURIComponent(postId));
        if (res && res.success) {
          if (!res.comments || res.comments.length === 0) {
            list.innerHTML = '<div class="text-xs text-gray-500">No comments yet.</div>';
            return;
          }
          list.innerHTML = '';
          for (const c of res.comments) {
            const div = document.createElement('div');
            div.className = 'p-2 bg-gray-800 rounded';
            div.innerHTML = '<div class="text-xs text-gray-400">By <strong>'+escapeHtml(c.username)+'</strong> • '+escapeHtml(c.created_at)+'</div><div class="mt-1 text-gray-200">'+escapeHtml(c.content)+'</div>';
            list.appendChild(div);
          }
        } else {
          list.innerHTML = '<div class="text-xs text-red-400">Could not load comments.</div>';
        }
      } catch (err) {
        list.innerHTML = '<div class="text-xs text-red-400">Error loading comments.</div>';
      }
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/[&<>"'`=\/]/g, function(s) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[s];
      });
    }
  </script>
</body>
</html>
</html>
</body>
</html>
</html>
