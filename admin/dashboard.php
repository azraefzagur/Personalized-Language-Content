<?php
require_once __DIR__ . '/../includes/rbac.php';

$u = require_role('admin');

// Toplam öğrenciler
$st = db()->query("SELECT COUNT(*) FROM users WHERE role='student'");
$totalStudents = (int)$st->fetchColumn();

// Son 7 gün aktif öğrenciler
$st = db()->query("SELECT COUNT(*) FROM users WHERE role='student' AND last_active_at >= (NOW() - INTERVAL 7 DAY)");
$active7 = (int)$st->fetchColumn();

// Rapor sayıları
$repNew = (int)db()->query("SELECT COUNT(*) FROM reports WHERE status='new'")->fetchColumn();
$repRev = (int)db()->query("SELECT COUNT(*) FROM reports WHERE status='reviewing'")->fetchColumn();
$repRes = (int)db()->query("SELECT COUNT(*) FROM reports WHERE status='resolved'")->fetchColumn();

// Seviye dağılımı
$levels = db()->query("
  SELECT COALESCE(level,'-') AS level_label, COUNT(*) AS cnt
  FROM users
  WHERE role='student'
  GROUP BY COALESCE(level,'-')
  ORDER BY level_label
")->fetchAll();

// Son 10 rapor
$recentReports = db()->query("
  SELECT r.id, r.created_at, r.category, r.status, r.page, u.full_name, u.email
  FROM reports r
  JOIN users u ON u.id=r.reporter_id
  ORDER BY r.id DESC
  LIMIT 10
")->fetchAll();

// Skill performans özeti (son 30 gün, öğrenciler)
$skillStats = db()->query("
  SELECT q.skill,
         COUNT(*) AS total,
         SUM(CASE WHEN qa.is_correct=1 THEN 1 ELSE 0 END) AS correct
  FROM question_attempts qa
  JOIN questions q ON q.id=qa.question_id
  JOIN users u ON u.id=qa.user_id
  WHERE u.role='student'
    AND qa.created_at >= (NOW() - INTERVAL 30 DAY)
  GROUP BY q.skill
  ORDER BY q.skill
")->fetchAll();

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Dashboard</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Admin</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/admin/questions.php">Questions</a>
      <a class="btn" href="<?=BASE_URL?>/admin/lessons.php">Lessons</a>
      <a class="btn" href="<?=BASE_URL?>/admin/students.php">Students</a>
      <a class="btn" href="<?=BASE_URL?>/admin/reports.php">Reports</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="grid" style="margin-top:18px">
    <div class="card">
      <div class="h1">Overview</div>
      <div class="muted">Quick system summary</div>
      <div class="hr"></div>

      <div class="row">
        <div class="badge">👩‍🎓 Students: <b><?=$totalStudents?></b></div>
        <div class="badge">🟢 Active (7d): <b><?=$active7?></b></div>
        <div class="badge">⚠️ New Reports: <b><?=$repNew?></b></div>
      </div>

      <div class="hr"></div>
      <div class="h1" style="font-size:18px">Level Distribution</div>

      <?php if(!$levels): ?>
        <div class="toast">No students yet.</div>
      <?php else: ?>
        <?php foreach($levels as $lv): ?>
          <div class="card" style="box-shadow:none; margin-top:10px">
            <div style="display:flex; justify-content:space-between; gap:10px">
              <div><b><?=htmlspecialchars($lv['level_label'])?></b></div>
              <div class="muted"><?= (int)$lv['cnt'] ?> students</div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="hr"></div>
      <div class="h1" style="font-size:18px">Skill accuracy (last 30 days)</div>
      <?php if(!$skillStats): ?>
        <div class="toast">No attempt data yet.</div>
      <?php else: ?>
        <?php foreach($skillStats as $s):
          $t=(int)$s['total']; $c=(int)$s['correct']; $p = $t? round(100*$c/$t):0;
        ?>
          <div class="card" style="box-shadow:none; margin-top:10px">
            <div style="display:flex; justify-content:space-between; gap:10px">
              <div><b><?=htmlspecialchars($s['skill'])?></b></div>
              <div class="muted"><?=$p?>% · <?=$c?>/<?=$t?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="h1">Reports</div>
      <div class="muted">Latest reports + status counters</div>
      <div class="hr"></div>

      <div class="row">
        <div class="badge">🆕 New: <b><?=$repNew?></b></div>
        <div class="badge">🔎 Reviewing: <b><?=$repRev?></b></div>
        <div class="badge">✅ Resolved: <b><?=$repRes?></b></div>
      </div>

      <div class="hr"></div>
      <div class="h1" style="font-size:18px">Latest 10 reports</div>

      <?php if(!$recentReports): ?>
        <div class="toast">No reports yet.</div>
      <?php else: ?>
        <?php foreach($recentReports as $r): ?>
          <div class="card" style="box-shadow:none; margin-bottom:10px">
            <div class="muted">
              #<?=$r['id']?> · <?=htmlspecialchars($r['created_at'])?> ·
              <b><?=htmlspecialchars($r['status'])?></b> · <?=htmlspecialchars($r['category'])?>
            </div>
            <div style="margin-top:6px">
              <b><?=htmlspecialchars($r['full_name'])?></b>
              <span class="muted">(<?=htmlspecialchars($r['email'])?>)</span>
            </div>
            <?php if($r['page']): ?>
              <div class="muted" style="margin-top:6px">Page: <?=htmlspecialchars($r['page'])?></div>
            <?php endif; ?>
            <div style="margin-top:10px">
              <a class="btn" href="<?=BASE_URL?>/admin/reports.php">Go to reports inbox</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
