<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('admin');
$ok = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $id = (int)($_POST['id'] ?? 0);
  $status = $_POST['status'] ?? 'new';
  if($id && in_array($status, ['new','reviewing','resolved'], true)){
    db()->prepare("UPDATE reports SET status=? WHERE id=?")->execute([$status,$id]);
    $ok = "Updated.";
  }
}

$filter = $_GET['status'] ?? '';
$params = [];
$sql = "SELECT r.*, u.full_name, u.email
        FROM reports r
        JOIN users u ON u.id=r.reporter_id";
if(in_array($filter, ['new','reviewing','resolved'], true)){
  $sql .= " WHERE r.status=?";
  $params[] = $filter;
}
$sql .= " ORDER BY r.id DESC LIMIT 100";

$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Reports</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Admin · Reports</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a>
      <a class="btn" href="<?=BASE_URL?>/admin/students.php">Students</a>
      <a class="btn" href="<?=BASE_URL?>/admin/questions.php">Questions</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="h1">Hata/Rapor Kutusu</div>
    <div class="row" style="margin-top:10px">
      <a class="btn" href="?">All</a>
      <a class="btn" href="?status=new">New</a>
      <a class="btn" href="?status=reviewing">Reviewing</a>
      <a class="btn" href="?status=resolved">Resolved</a>
    </div>
    <div class="hr"></div>

    <?php if($ok): ?><div class="toast"><?=htmlspecialchars($ok)?></div><div class="hr"></div><?php endif; ?>

    <?php if(!$rows): ?>
      <div class="toast">No records.</div>
    <?php endif; ?>

    <?php foreach($rows as $r): ?>
      <div class="card" style="box-shadow:none; margin-bottom:10px">
        <div class="muted">
          #<?=$r['id']?> · <?=htmlspecialchars($r['created_at'])?> ·
          <b><?=htmlspecialchars($r['status'])?></b> · <?=htmlspecialchars($r['category'])?>
        </div>
        <div style="margin-top:6px"><b><?=htmlspecialchars($r['full_name'])?></b> <span class="muted">(<?=htmlspecialchars($r['email'])?>)</span></div>
        <?php if($r['page']): ?><div class="muted" style="margin-top:6px">Page: <?=htmlspecialchars($r['page'])?></div><?php endif; ?>
        <div style="margin-top:8px"><?=nl2br(htmlspecialchars($r['message']))?></div>

        <div class="hr"></div>
        <form method="post" class="row">
          <input type="hidden" name="csrf" value="<?=csrf_token()?>">
          <input type="hidden" name="id" value="<?=$r['id']?>">
          <select class="input" name="status" style="max-width:220px">
            <option value="new" <?=$r['status']==='new'?'selected':''?>>new</option>
            <option value="reviewing" <?=$r['status']==='reviewing'?'selected':''?>>reviewing</option>
            <option value="resolved" <?=$r['status']==='resolved'?'selected':''?>>resolved</option>
          </select>
          <button class="btn">Save</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
