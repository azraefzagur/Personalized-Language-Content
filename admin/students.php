<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('admin');
$ok = $err = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $action = $_POST['action'] ?? '';

  if($action==='create_student'){
    $name = trim($_POST['full_name'] ?? '');
    $email= trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $level= trim($_POST['level'] ?? '');
    $placementDone = (int)($_POST['placement_completed'] ?? 0);

    if($name==='' || $email==='' || strlen($pass)<6){
      $err = "Name and email are required. Password must be at least 6 characters.";
    } else {
      try{
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $st = db()->prepare("
          INSERT INTO users(role,email,password_hash,full_name,level,placement_completed,last_active_at)
          VALUES('student',?,?,?,?,?,NOW())
        ");
        $st->execute([$email,$hash,$name, $level?:null, $placementDone]);
        $ok = "Student account created.";
      }catch(Exception $e){
        $err = "This email may already be registered.";
      }
    }
  }

  if($action==='set_level'){
    $sid = (int)($_POST['student_id'] ?? 0);
    $level = trim($_POST['level'] ?? '');
    $placementDone = (int)($_POST['placement_completed'] ?? 0);

    if($sid){
      db()->prepare("UPDATE users SET level=?, placement_completed=? WHERE id=? AND role='student'")
        ->execute([$level?:null, $placementDone, $sid]);
      $ok = "Updated.";
    }
  }
}

$students = db()->query("
  SELECT id, full_name, email, level, placement_completed, points, last_active_at, created_at
  FROM users
  WHERE role='student'
  ORDER BY id DESC
  LIMIT 100
")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Students</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Admin · Students</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a>
      <a class="btn" href="<?=BASE_URL?>/admin/questions.php">Questions</a>
      <a class="btn" href="<?=BASE_URL?>/admin/reports.php">Reports</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <div class="h1">Create student account</div>
      <div class="muted">Admins can create accounts manually. You can keep placement required if you want.</div>
      <div class="hr"></div>

      <?php if($err): ?><div class="toast"><?=htmlspecialchars($err)?></div><div class="hr"></div><?php endif; ?>
      <?php if($ok): ?><div class="toast"><?=htmlspecialchars($ok)?></div><div class="hr"></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="create_student">

        <input class="input" name="full_name" placeholder="Full name" required><br><br>
        <input class="input" type="email" name="email" placeholder="Email" required><br><br>
        <input class="input" type="password" name="password" placeholder="Password (min 6)" required><br><br>

        <div class="row">
          <select class="input" name="level">
            <option value="">(no level)</option>
            <option value="A1">A1</option><option value="A2">A2</option>
            <option value="B1">B1</option><option value="B2">B2</option>
            <option value="C1">C1</option>
          </select>
          <select class="input" name="placement_completed">
            <option value="0">Placement required</option>
            <option value="1">Mark placement as completed</option>
          </select>
        </div><br>

        <button class="btn primary" style="width:100%">Create account</button>
      </form>
    </div>

    <div class="card">
      <div class="h1">Students</div>
      <div class="muted">Latest 100</div>
      <div class="hr"></div>

      <?php foreach($students as $s): ?>
        <div class="card" style="box-shadow:none; margin-bottom:10px">
          <div style="display:flex; justify-content:space-between; gap:10px">
            <div>
              <div><b><?=htmlspecialchars($s['full_name'])?></b></div>
              <div class="muted"><?=htmlspecialchars($s['email'])?></div>
              <div class="muted" style="margin-top:6px">
                Level: <b><?=htmlspecialchars($s['level'] ?? '-')?></b> ·
                Placement: <b><?= (int)$s['placement_completed'] ? 'yes':'no' ?></b> ·
                Points: <b><?= (int)$s['points'] ?></b>
              </div>
              <div class="muted" style="font-size:12px; margin-top:6px">
                Last active: <?=htmlspecialchars($s['last_active_at'] ?? '-')?>
              </div>
            </div>
          </div>

          <div class="hr"></div>
          <form method="post" class="row">
            <input type="hidden" name="csrf" value="<?=csrf_token()?>">
            <input type="hidden" name="action" value="set_level">
            <input type="hidden" name="student_id" value="<?=$s['id']?>">

            <select class="input" name="level">
              <option value="">(no level)</option>
              <?php foreach(['A1','A2','B1','B2','C1'] as $lv): ?>
                <option value="<?=$lv?>" <?=($s['level']===$lv?'selected':'')?>><?=$lv?></option>
              <?php endforeach; ?>
            </select>

            <select class="input" name="placement_completed">
              <option value="0" <?=((int)$s['placement_completed']===0?'selected':'')?>>Placement zorunlu</option>
              <option value="1" <?=((int)$s['placement_completed']===1?'selected':'')?>>Placement completed</option>
            </select>

            <button class="btn">Update</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
