<?php
require_once __DIR__ . '/../includes/rbac.php';

$u = require_role('student');

// Overall practice stats
$st = db()->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN is_correct=1 THEN 1 ELSE 0 END) AS correct
                    FROM question_attempts WHERE user_id=? AND attempt_type='practice'");
$st->execute([(int)$u['id']]);
$overall = $st->fetch() ?: ['total'=>0,'correct'=>0];
$total = (int)($overall['total'] ?? 0);
$correct = (int)($overall['correct'] ?? 0);
$wrong = max(0, $total - $correct);
$accuracy = ($total > 0) ? round(($correct / $total) * 100) : 0;

// Completed tasks
$st = db()->prepare("SELECT COUNT(*) FROM user_tasks WHERE user_id=? AND status='done'");
$st->execute([(int)$u['id']]);
$doneTasks = (int)$st->fetchColumn();

// Topic-based stats
$st = db()->prepare("
  SELECT COALESCE(NULLIF(q.topic,''),'(No topic)') AS topic,
         COUNT(*) AS attempts,
         SUM(CASE WHEN qa.is_correct=1 THEN 1 ELSE 0 END) AS correct
  FROM question_attempts qa
  JOIN questions q ON q.id = qa.question_id
  WHERE qa.user_id=? AND qa.attempt_type='practice'
  GROUP BY COALESCE(NULLIF(q.topic,''),'(No topic)')
  ORDER BY attempts DESC
  LIMIT 30
");
$st->execute([(int)$u['id']]);
$topicRows = $st->fetchAll();

// Recent attempts
$st = db()->prepare("
  SELECT qa.created_at, qa.is_correct, q.prompt, q.skill, q.difficulty, q.topic
  FROM question_attempts qa
  JOIN questions q ON q.id=qa.question_id
  WHERE qa.user_id=? AND qa.attempt_type='practice'
  ORDER BY qa.id DESC
  LIMIT 20
");
$st->execute([(int)$u['id']]);
$recent = $st->fetchAll();

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function short_text(string $s, int $n=120): string {
  $s = trim(preg_replace('/\s+/', ' ', $s));
  return (mb_strlen($s) > $n) ? (mb_substr($s, 0, $n-1).'…') : $s;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Progress</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=e($u['theme'])?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Progress</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/student/dashboard.php">Dashboard</a>
      <a class="btn" href="<?=BASE_URL?>/student/lessons.php">Lessons</a>
      <a class="btn" href="<?=BASE_URL?>/student/practice.php">Practice</a>
      <a class="btn" href="<?=BASE_URL?>/student/favorites.php">Favorites</a>
      <a class="btn" href="<?=BASE_URL?>/student/notebook.php">Notebook</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px; margin-top:18px">
    <div class="card" style="margin:0">
      <div class="muted">Practice attempts</div>
      <div class="h1" style="font-size:28px; margin-top:8px"><?= $total ?></div>
    </div>
    <div class="card" style="margin:0">
      <div class="muted">Accuracy</div>
      <div class="h1" style="font-size:28px; margin-top:8px"><?= $accuracy ?>%</div>
      <div style="height:10px"></div>
      <div class="progress"><div class="progress-bar" style="width:<?= (int)$accuracy ?>%"></div></div>
      <div class="muted" style="margin-top:8px"><?= $correct ?> correct · <?= $wrong ?> wrong</div>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="row" style="justify-content:space-between; align-items:baseline">
      <div>
        <div class="h1" style="font-size:18px">Topic Performance</div>
        <div class="muted">Success rates by topic based on your solved questions.</div>
      </div>
      <div class="pill">Completed tasks: <?= $doneTasks ?></div>
    </div>
    <div class="hr"></div>

    <?php if (!$topicRows): ?>
      <div class="toast">No practice data yet. Solve a few questions to see your progress.</div>
    <?php else: ?>
      <div style="display:grid; gap:12px">
        <?php foreach($topicRows as $r):
          $a = (int)$r['attempts'];
          $c = (int)$r['correct'];
          $pct = ($a>0) ? (int)round(100*$c/$a) : 0;
        ?>
          <div class="card" style="margin:0; box-shadow:none">
            <div class="row" style="justify-content:space-between; align-items:baseline; gap:12px">
              <div>
                <div style="font-weight:900"><?=e((string)$r['topic'])?></div>
                <div class="muted" style="margin-top:4px"><?= $c ?> / <?= $a ?> correct</div>
              </div>
              <div class="pill"><?= $pct ?>%</div>
            </div>
            <div style="height:10px"></div>
            <div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="h1" style="font-size:18px">Recent Practice</div>
    <div class="muted">Your latest answered questions.</div>
    <div class="hr"></div>

    <?php if(!$recent): ?>
      <div class="toast">No recent activity.</div>
    <?php else: ?>
      <div style="display:grid; gap:10px">
        <?php foreach($recent as $it): ?>
          <div class="card" style="margin:0; box-shadow:none">
            <div class="row" style="justify-content:space-between; align-items:baseline; gap:10px">
              <div class="muted"><?=e($it['created_at'])?></div>
              <div class="pill"><?= ((int)$it['is_correct']===1) ? 'Correct' : 'Wrong' ?></div>
            </div>
            <div class="muted" style="margin-top:6px"><?=e($it['skill'])?> · diff <?= (int)$it['difficulty'] ?> · Topic: <b><?=e($it['topic'] ?: '—')?></b></div>
            <div style="margin-top:6px; font-weight:900"><?=e(short_text((string)$it['prompt'], 160))?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
