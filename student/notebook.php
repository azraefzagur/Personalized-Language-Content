<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('student');

$err = '';

// Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  csrf_check();
  $title = trim((string)($_POST['title'] ?? ''));
  $content = trim((string)($_POST['content'] ?? ''));
  if ($title === '') {
    $err = 'Title is required.';
  } else {
    db()->prepare("INSERT INTO notebook_entries(user_id,term,note) VALUES(?,?,?)")
      ->execute([(int)$u['id'], $title, ($content === '' ? null : $content)]);
    header('Location: '.BASE_URL.'/student/notebook.php?saved=1');
    exit;
  }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  csrf_check();
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    db()->prepare("DELETE FROM notebook_entries WHERE id=? AND user_id=?")
      ->execute([$id, (int)$u['id']]);
  }
  header('Location: '.BASE_URL.'/student/notebook.php');
  exit;
}

$viewId = (int)($_GET['view'] ?? 0);

$st = db()->prepare("SELECT id, term, note, created_at FROM notebook_entries WHERE user_id=? ORDER BY id DESC LIMIT 200");
$st->execute([(int)$u['id']]);
$notes = $st->fetchAll();

$active = null;
if ($viewId > 0) {
  $st2 = db()->prepare("SELECT id, term, note, created_at FROM notebook_entries WHERE id=? AND user_id=? LIMIT 1");
  $st2->execute([$viewId, (int)$u['id']]);
  $active = $st2->fetch();
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function short_text(string $s, int $n=64): string {
  $s = trim(preg_replace('/\s+/', ' ', $s));
  return (mb_strlen($s) > $n) ? (mb_substr($s, 0, $n-1).'…') : $s;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Notebook</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=e($u['theme'])?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Notebook</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/student/dashboard.php">Dashboard</a>
      <a class="btn" href="<?=BASE_URL?>/student/lessons.php">Lessons</a>
      <a class="btn" href="<?=BASE_URL?>/student/practice.php">Practice</a>
      <a class="btn" href="<?=BASE_URL?>/student/progress.php">Progress</a>
      <a class="btn" href="<?=BASE_URL?>/student/favorites.php">Favorites</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="h1" style="font-size:18px">My Notebook</div>
    <div class="muted">Add notes with a required title. Your saved notes appear on the right.</div>
  </div>

  <div class="grid" style="grid-template-columns: 1fr 360px; gap:16px; align-items:start; margin-top:16px">
    <div class="card" style="margin:0">
      <div class="h1" style="font-size:16px">Add a note</div>
      <div class="muted">Title is required. Content is optional.</div>
      <div class="hr"></div>

      <?php if ($err): ?>
        <div class="toast" style="margin-bottom:12px"><?=e($err)?></div>
      <?php elseif (isset($_GET['saved'])): ?>
        <div class="toast" style="margin-bottom:12px">Saved ✅</div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="add">

        <div class="muted" style="margin-bottom:6px">Title *</div>
        <input class="input" name="title" required placeholder="e.g., Past Simple rules" value="<?=e($_POST['title'] ?? '')?>">

        <div style="height:10px"></div>
        <div class="muted" style="margin-bottom:6px">Content (optional)</div>
        <textarea class="input" name="content" rows="6" placeholder="Write your note... (examples, reminders, etc.)"><?=e($_POST['content'] ?? '')?></textarea>

        <div style="height:12px"></div>
        <button class="btn primary" style="width:100%" type="submit">Save note</button>
      </form>

      <?php if ($active): ?>
        <div class="hr"></div>
        <div class="h1" style="font-size:16px">Selected note</div>
        <div class="muted" style="margin-top:6px"><b><?=e($active['term'])?></b> · <?=e($active['created_at'])?></div>
        <div style="height:10px"></div>
        <div class="toast" style="white-space:pre-wrap"><?=e($active['note'] ?: '—')?></div>
        <div style="height:12px"></div>
        <form method="post" onsubmit="return confirm('Delete this note?')">
          <input type="hidden" name="csrf" value="<?=csrf_token()?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
          <button class="btn" type="submit">Delete</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="card" style="margin:0">
      <div class="row" style="justify-content:space-between; align-items:baseline">
        <div>
          <div class="h1" style="font-size:16px">Saved notes</div>
          <div class="muted">Click a title to view.</div>
        </div>
        <div class="pill"><?=count($notes)?> items</div>
      </div>
      <div class="hr"></div>

      <?php if(!$notes): ?>
        <div class="toast">No notes yet.</div>
      <?php else: ?>
        <div style="display:grid; gap:10px; max-height:60vh; overflow:auto; padding-right:6px">
          <?php foreach($notes as $n):
            $isActive = ($active && (int)$active['id'] === (int)$n['id']);
          ?>
            <a class="card" href="<?=BASE_URL?>/student/notebook.php?view=<?=(int)$n['id']?>" style="margin:0; box-shadow:none; text-decoration:none; border:1px solid rgba(141,169,196,.20); <?= $isActive ? 'background: rgba(19,64,116,.18);' : '' ?>">
              <div style="font-weight:900; color:var(--text)"><?=e(short_text((string)$n['term'], 80))?></div>
              <div class="muted" style="margin-top:4px"><?=e($n['created_at'])?></div>
              <?php if (!empty($n['note'])): ?>
                <div class="muted" style="margin-top:6px"><?=e(short_text((string)$n['note'], 90))?></div>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>
