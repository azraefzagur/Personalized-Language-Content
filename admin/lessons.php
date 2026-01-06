<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('admin');

$ok = $err = '';
$editId = (int)($_GET['edit'] ?? 0);

function fetch_lesson(int $id): ?array {
  $st = db()->prepare("SELECT * FROM lessons WHERE id=?");
  $st->execute([$id]);
  $r = $st->fetch();
  return $r ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'add' || $action === 'update') {
    $title = trim($_POST['title'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $skill = $_POST['skill'] ?? 'grammar';
    $type  = $_POST['material_type'] ?? 'reading';
    $diff  = (int)($_POST['difficulty'] ?? 1);
    $body  = trim($_POST['body_html'] ?? '');
    $active = (int)($_POST['is_active'] ?? 1);

    if ($title === '' || $body === '') {
      $err = "Title and content (body) are required.";
    } else {
      if ($action === 'add') {
        $st = db()->prepare("
          INSERT INTO lessons(title, level, skill, material_type, body_html, difficulty, is_active)
          VALUES(?,?,?,?,?,?,?)
        ");
        $st->execute([$title, $level ?: null, $skill, $type, $body, $diff, $active]);
        $ok = "Lesson eklendi.";
      } else {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) $err = "Missing id";
        else {
          $st = db()->prepare("
            UPDATE lessons
            SET title=?, level=?, skill=?, material_type=?, body_html=?, difficulty=?, is_active=?
            WHERE id=?
          ");
          $st->execute([$title, $level ?: null, $skill, $type, $body, $diff, $active, $id]);
          $ok = "Lesson updated.";
          $editId = $id;
        }
      }
    }
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
      db()->prepare("DELETE FROM lessons WHERE id=?")->execute([$id]);
      $ok = "Deleted.";
      if ($editId === $id) $editId = 0;
    }
  }

  if ($action === 'toggle_active') {
    $id = (int)($_POST['id'] ?? 0);
    $to = (int)($_POST['to'] ?? 1);
    if ($id) {
      db()->prepare("UPDATE lessons SET is_active=? WHERE id=?")->execute([$to, $id]);
      $ok = "Status updated.";
    }
  }
}

$editing = $editId ? fetch_lesson($editId) : null;

$list = db()->query("
  SELECT id, title, level, skill, material_type, difficulty, is_active, created_at
  FROM lessons
  ORDER BY id DESC
  LIMIT 80
")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Lessons</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Admin · Lessons</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a>
      <a class="btn" href="<?=BASE_URL?>/admin/questions.php">Questions</a>
      <a class="btn" href="<?=BASE_URL?>/admin/students.php">Students</a>
      <a class="btn" href="<?=BASE_URL?>/admin/reports.php">Reports</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="grid" style="margin-top:18px">
    <div class="card">
      <div class="h1"><?= $editing ? "Edit Lesson (#".$editing['id'].")" : "Add New Lesson" ?></div>
      <div class="muted">material_type: reading / visual. You can put simple HTML into body_html.</div>
      <div class="hr"></div>

      <?php if($err): ?><div class="toast"><?=htmlspecialchars($err)?></div><div class="hr"></div><?php endif; ?>
      <?php if($ok): ?><div class="toast"><?=htmlspecialchars($ok)?></div><div class="hr"></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <?php if($editing): ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?=$editing['id']?>">
        <?php else: ?>
          <input type="hidden" name="action" value="add">
        <?php endif; ?>

        <input class="input" name="title" placeholder="Title"
               value="<?=htmlspecialchars($editing['title'] ?? '')?>" required><br><br>

        <div class="row">
          <select class="input" name="level">
            <?php $curLv = $editing['level'] ?? ''; ?>
            <option value="" <?=$curLv===''?'selected':''?>>(no level)</option>
            <?php foreach(['A1','A2','B1','B2','C1'] as $lv): ?>
              <option value="<?=$lv?>" <?=$curLv===$lv?'selected':''?>><?=$lv?></option>
            <?php endforeach; ?>
          </select>

          <select class="input" name="skill" required>
            <?php $curSk = $editing['skill'] ?? 'grammar'; ?>
            <?php foreach(['vocab','grammar','reading','listening','writing'] as $sk): ?>
              <option value="<?=$sk?>" <?=$curSk===$sk?'selected':''?>><?=$sk?></option>
            <?php endforeach; ?>
          </select>
        </div><br>

        <div class="row">
          <select class="input" name="material_type" required>
            <?php $curT = $editing['material_type'] ?? 'reading'; ?>
            <option value="reading" <?=$curT==='reading'?'selected':''?>>reading</option>
            <option value="visual"  <?=$curT==='visual'?'selected':''?>>visual</option>
          </select>

          <input class="input" type="number" name="difficulty" min="1" max="5"
                 value="<?= (int)($editing['difficulty'] ?? 1) ?>" required>
        </div><br>

        <label class="muted">
          <input type="checkbox" name="is_active" value="1"
            <?= ((int)($editing['is_active'] ?? 1)===1) ? 'checked' : '' ?>>
          Active
        </label><br><br>

        <textarea class="input" name="body_html" rows="10" placeholder="Lesson content (HTML allowed)"
                  required><?=htmlspecialchars($editing['body_html'] ?? '')?></textarea><br><br>

        <button class="btn primary" style="width:100%"><?= $editing ? "Update" : "Save" ?></button>

        <?php if($editing): ?>
          <div style="margin-top:10px">
            <a class="btn" href="<?=BASE_URL?>/admin/lessons.php">Back to add new</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <div class="h1">Lesson List</div>
      <div class="muted">Latest 80 records</div>
      <div class="hr"></div>

      <?php if(!$list): ?>
        <div class="toast">No lessons yet.</div>
      <?php endif; ?>

      <?php foreach($list as $l): ?>
        <div class="card" style="box-shadow:none; margin-bottom:10px">
          <div class="muted">
            #<?=$l['id']?> · <?=htmlspecialchars($l['skill'])?> · <?=htmlspecialchars($l['material_type'])?>
            · diff <?= (int)$l['difficulty'] ?> · level <?=htmlspecialchars($l['level'] ?? '-')?>
          </div>
          <div style="margin-top:6px"><b><?=htmlspecialchars($l['title'])?></b></div>
          <div class="muted" style="margin-top:6px"><?=htmlspecialchars($l['created_at'])?></div>

          <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap">
            <a class="btn" href="<?=BASE_URL?>/admin/lessons.php?edit=<?=$l['id']?>">Edit</a>

            <form method="post" style="display:inline" onsubmit="return confirm('Delete this lesson?')">
              <input type="hidden" name="csrf" value="<?=csrf_token()?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?=$l['id']?>">
              <button class="btn">Delete</button>
            </form>

            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?=csrf_token()?>">
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="id" value="<?=$l['id']?>">
              <input type="hidden" name="to" value="<?= ((int)$l['is_active']===1) ? 0 : 1 ?>">
              <button class="btn"><?= ((int)$l['is_active']===1) ? 'Deactivate' : 'Activate' ?></button>
            </form>

            <span class="badge"><?= ((int)$l['is_active']===1) ? 'active' : 'inactive' ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
