<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/tasks.php';

$u = require_role('student');

// If the user has no active tasks yet, try generating some.
$activeSt = db()->prepare("SELECT COUNT(*) FROM user_tasks WHERE user_id=? AND status IN ('open','in_progress')");
$activeSt->execute([$u['id']]);
$active = (int)$activeSt->fetchColumn();
if ($active === 0) {
  refresh_tasks_for_user((int)$u['id'], 3);
}

$st = db()->prepare("SELECT * FROM user_tasks WHERE user_id=? ORDER BY id DESC LIMIT 12");
$st->execute([$u['id']]);
$tasks = $st->fetchAll();

?><!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Dashboard</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'])?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Dashboard</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/student/lessons.php">Lessons</a>
      <a class="btn" href="<?=BASE_URL?>/student/practice.php">Practice</a>
      <a class="btn" href="<?=BASE_URL?>/student/progress.php">Progress</a>
      <a class="btn" href="<?=BASE_URL?>/student/favorites.php">Favorites</a>
      <a class="btn" href="<?=BASE_URL?>/student/notebook.php">Notebook</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="row" style="justify-content:space-between; align-items:flex-start">
      <div>
        <div class="h1" style="font-size:22px">Welcome, <?=htmlspecialchars($u['full_name'] ?? 'Student')?> 👋</div>
        <div class="muted" style="margin-top:6px">
          Level: <b><?=htmlspecialchars($u['level'] ?? '—')?></b> · Points: <b><?= (int)($u['points'] ?? 0) ?></b>
        </div>
      </div>
      <div>
        <a class="btn" href="<?=BASE_URL?>/student/refresh_tasks.php">Refresh tasks</a>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="row" style="justify-content:space-between; align-items:center">
      <div>
        <div class="h1" style="font-size:18px">My Tasks</div>
        <div class="muted">AI assigns tasks based on your weak topics. Click a task to start.</div>
      </div>
    </div>
    <div class="hr"></div>

    <?php if(!$tasks): ?>
      <div class="toast">No tasks yet. Try refreshing.</div>
    <?php else: ?>
      <div style="display:grid; gap:12px">
        <?php foreach($tasks as $t):
          $p = task_progress((int)$u['id'], (int)$t['id']);
          $done = (int)$p['done'];
          $total = max(1, (int)$p['total']);
          $pct = (int)round(100 * $done / $total);
          $status = (string)$t['status'];
        ?>
          <div class="card" style="margin:0">
            <div class="row" style="justify-content:space-between; align-items:flex-start">
              <div>
                <div style="font-weight:900; font-size:16px"><?=htmlspecialchars($t['title'])?></div>
                <div class="muted" style="margin-top:4px">Topic: <b><?=htmlspecialchars($t['topic'])?></b> · Status: <?=htmlspecialchars(ucfirst($status))?></div>
              </div>
              <div>
                <?php if($status !== 'done'): ?>
                  <a class="btn primary" href="<?=BASE_URL?>/student/task_start.php?task_id=<?= (int)$t['id'] ?>">Start</a>
                <?php else: ?>
                  <span class="pill">Completed</span>
                <?php endif; ?>
              </div>
            </div>

            <div style="height:10px"></div>
            <div class="progress" aria-label="progress"><div class="progress-bar" style="width:<?=$pct?>%"></div></div>
            <div class="muted" style="margin-top:8px"><?=$done?> / <?=$total?> questions</div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
