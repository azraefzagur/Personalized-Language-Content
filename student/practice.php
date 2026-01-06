<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('student');
if ((int)$u['placement_completed'] === 0) {
  header("Location: ".BASE_URL."/student/placement.php");
  exit;
}

$topic = trim((string)($_GET['topic'] ?? ''));
$skill = trim((string)($_GET['skill'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'new');
$q = trim((string)($_GET['q'] ?? ''));

$order = ($sort === 'old') ? 'ASC' : 'DESC';

// Topics list
$topicSt = db()->query("SELECT DISTINCT topic FROM questions WHERE is_active=1 AND (topic IS NOT NULL AND topic<>'') ORDER BY topic ASC");
$topics = array_map(fn($r)=>$r['topic'], $topicSt->fetchAll() ?: []);

// Build query
$sql = "
  SELECT q.*,
    EXISTS(
      SELECT 1 FROM favorites f
      WHERE f.user_id=? AND f.fav_type='question' AND f.ref_id=q.id
    ) AS is_fav
  FROM questions q
  WHERE q.is_active=1 AND q.is_placement=0
";
$params = [$u['id']];

if ($topic !== '') {
  if ($topic === '__none__') {
    $sql .= " AND (q.topic IS NULL OR q.topic='')";
  } else {
    $sql .= " AND q.topic=?";
    $params[] = $topic;
  }
}
if ($skill !== '') {
  $sql .= " AND q.skill=?";
  $params[] = $skill;
}
if ($q !== '') {
  $sql .= " AND (q.prompt LIKE ? OR COALESCE(q.topic,'') LIKE ?)";
  $params[] = "%{$q}%";
  $params[] = "%{$q}%";
}

$sql .= " ORDER BY q.created_at {$order} LIMIT 200";
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function short_text(string $s, int $n=160): string {
  $s = trim(preg_replace('/\s+/', ' ', $s));
  return (mb_strlen($s) > $n) ? (mb_substr($s, 0, $n-1).'…') : $s;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Practice</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <script>const BASE_URL = "<?=BASE_URL?>";</script>
</head>
<body data-theme="<?=e($u['theme'])?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Practice</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/student/dashboard.php">Dashboard</a>
      <a class="btn" href="<?=BASE_URL?>/student/lessons.php">Lessons</a>
      <a class="btn" href="<?=BASE_URL?>/student/progress.php">Progress</a>
      <a class="btn" href="<?=BASE_URL?>/student/favorites.php">Favorites</a>
      <a class="btn" href="<?=BASE_URL?>/student/notebook.php">Notebook</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="h1" style="font-size:18px">Question Bank</div>
    <div class="muted">All practice questions added by admins. Filter by topic, skill, and date.</div>
    <div class="hr"></div>

    <form method="get" class="row" style="gap:10px; flex-wrap:wrap; align-items:end">
      <div style="min-width:220px">
        <div class="muted" style="margin-bottom:6px">Topic</div>
        <select class="input" name="topic">
          <option value="" <?= $topic===''?'selected':'' ?>>All topics</option>
          <option value="__none__" <?= $topic==='__none__'?'selected':'' ?>>(No topic)</option>
          <?php foreach($topics as $t): ?>
            <option value="<?=e($t)?>" <?= $topic===$t?'selected':'' ?>><?=e($t)?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="min-width:180px">
        <div class="muted" style="margin-bottom:6px">Skill</div>
        <select class="input" name="skill">
          <option value="" <?= $skill===''?'selected':'' ?>>All skills</option>
          <option value="vocab" <?= $skill==='vocab'?'selected':'' ?>>Vocabulary</option>
          <option value="grammar" <?= $skill==='grammar'?'selected':'' ?>>Grammar</option>
          <option value="reading" <?= $skill==='reading'?'selected':'' ?>>Reading</option>
          <option value="listening" <?= $skill==='listening'?'selected':'' ?>>Listening</option>
          <option value="writing" <?= $skill==='writing'?'selected':'' ?>>Writing</option>
        </select>
      </div>

      <div style="min-width:180px">
        <div class="muted" style="margin-bottom:6px">Sort</div>
        <select class="input" name="sort">
          <option value="new" <?= $sort==='new'?'selected':'' ?>>Newest first</option>
          <option value="old" <?= $sort==='old'?'selected':'' ?>>Oldest first</option>
        </select>
      </div>

      <div style="flex:1; min-width:240px">
        <div class="muted" style="margin-bottom:6px">Search</div>
        <input class="input" name="q" value="<?=e($q)?>" placeholder="Search prompt or topic" />
      </div>

      <button class="btn primary" type="submit">Apply</button>
      <a class="btn" href="<?=BASE_URL?>/student/practice.php">Reset</a>
    </form>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="row" style="justify-content:space-between; align-items:baseline">
      <div>
        <div class="h1" style="font-size:16px">Questions</div>
        <div class="muted">Click “Solve” to open a question.</div>
      </div>
      <div class="pill"><?=count($rows)?> shown</div>
    </div>
    <div class="hr"></div>

    <?php if(!$rows): ?>
      <div class="toast">No questions match your filters.</div>
    <?php else: ?>
      <div style="display:grid; gap:10px">
        <?php foreach($rows as $r): ?>
          <div class="card" style="margin:0; box-shadow:none">
            <div class="muted">
              <?=e($r['skill'])?> · diff <?= (int)$r['difficulty'] ?> · Topic: <b><?=e($r['topic'] ?: '—')?></b> · <?=e($r['created_at'])?>
            </div>
            <div style="margin-top:6px; font-weight:900"><?=e(short_text((string)$r['prompt'], 180))?></div>
            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap">
              <a class="btn primary" href="<?=BASE_URL?>/student/practice_view.php?qid=<?=(int)$r['id']?>">Solve</a>
              <button class="btn" onclick="toggleFav(<?= (int)$r['id'] ?>, this)">
                <?= ((int)$r['is_fav']===1) ? '★ Saved' : '☆ Save' ?>
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<form id="csrfForm" style="display:none">
  <input type="hidden" name="csrf" value="<?=csrf_token()?>">
</form>

<script>
async function toggleFav(qid, btn){
  const csrf = document.querySelector('#csrfForm input[name="csrf"]').value;
  const res = await fetch(`${BASE_URL}/student/api/favorite_toggle_ajax.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`csrf=${encodeURIComponent(csrf)}&fav_type=question&ref_id=${encodeURIComponent(qid)}`
  });
  const data = await res.json();
  if(!data.ok){ alert('Action failed'); return; }
  btn.textContent = (data.state === 'added') ? '★ Saved' : '☆ Save';
}
</script>
</body>
</html>
