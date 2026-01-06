<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('student');
$qid = (int)($_GET['qid'] ?? 0);
$taskId = (int)($_GET['task_id'] ?? 0);
if(!$qid) exit("Missing qid");

$st = db()->prepare("
  SELECT q.*,
    EXISTS(
      SELECT 1 FROM favorites f
      WHERE f.user_id=? AND f.fav_type='question' AND f.ref_id=q.id
    ) AS is_fav
  FROM questions q
  WHERE q.id=? AND q.is_active=1
  LIMIT 1
");
$st->execute([$u['id'], $qid]);
$q = $st->fetch();
if(!$q) exit("Question not found");

$choices = $q['choices_json'] ? json_decode($q['choices_json'], true) : null;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Question</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <script>const BASE_URL = "<?=BASE_URL?>";</script>
</head>
<body data-theme="<?=htmlspecialchars($u['theme'])?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Question</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/student/dashboard.php">Dashboard</a>
      <a class="btn" href="<?=BASE_URL?>/student/lessons.php">Lessons</a>
      <a class="btn" href="<?=BASE_URL?>/student/practice.php">Practice</a>
      <a class="btn" href="<?=BASE_URL?>/student/progress.php">Progress</a>
      <a class="btn" href="<?=BASE_URL?>/student/favorites.php">Favorites</a>
      <a class="btn" href="<?=BASE_URL?>/student/notebook.php">Notebook</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <?php if($taskId > 0): ?>
    <div class="card" style="margin-top:18px">
      <div class="row" style="justify-content:space-between; align-items:center">
        <div>
          <div style="font-weight:900">Task mode</div>
          <div class="muted">You're solving a task assigned by AI. Finish all questions to complete the task.</div>
        </div>
        <a class="btn" href="<?=BASE_URL?>/student/dashboard.php">Back to tasks</a>
      </div>
    </div>
  <?php endif; ?>

  <div class="card" style="margin-top:18px">
    <div class="muted"><?=htmlspecialchars($q['skill'])?> · diff <?= (int)$q['difficulty'] ?></div>
    <div class="h1" style="font-size:20px; margin-top:10px"><?=htmlspecialchars($q['prompt'])?></div>

    <?php if($q['media_url']): ?>
      <?php if(preg_match('/\.(mp3|wav)$/i', $q['media_url'])): ?>
        <audio controls src="<?=htmlspecialchars($q['media_url'])?>" style="width:100%; margin:10px 0"></audio>
      <?php else: ?>
        <img src="<?=htmlspecialchars($q['media_url'])?>" style="max-width:100%; border-radius:14px; margin:10px 0">
      <?php endif; ?>
    <?php endif; ?>

    <?php if($choices): ?>
      <div style="margin-top:10px; display:grid; gap:10px">
        <?php foreach($choices as $i=>$c): ?>
          <button class="btn" onclick="submitAnswer('<?= (string)$i ?>')"><?=htmlspecialchars($c)?></button>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <textarea class="input" id="w" rows="4" placeholder="Type your answer..."></textarea>
      <div style="height:10px"></div>
      <button class="btn primary" onclick="submitAnswer(document.getElementById('w').value)">Check Answer</button>
    <?php endif; ?>

    <div class="hr"></div>
    <div class="row">
      <button class="btn" onclick="toggleHint()">Hint</button>
      <button class="btn" id="favBtn" onclick="toggleFav()"><?= ((int)$q['is_fav']===1) ? '★ Saved' : '☆ Save' ?></button>
      <button class="btn" onclick="toggleNote()">📒 Notebook</button>
    </div>

    <div id="hint" class="muted" style="margin-top:10px; display:none">
      <?=htmlspecialchars($q['hint'] ?: 'No hint available for this question.')?>
    </div>

    <div id="feedback" style="margin-top:12px"></div>
  </div>

  <div class="card" id="noteBox" style="margin-top:18px; display:none">
    <div class="h1" style="font-size:18px">Add to Notebook</div>
    <div class="muted">Save a word quickly to review later.</div>
    <div class="hr"></div>

    <input class="input" id="term" placeholder="Word / term"><br><br>
    <input class="input" id="meaning" placeholder="Meaning (optional)"><br><br>
    <input class="input" id="example" placeholder="Example sentence (optional)"><br><br>
    <textarea class="input" id="note" rows="3" placeholder="Note (optional)"></textarea><br><br>
    <button class="btn primary" onclick="saveNote()" style="width:100%">Save</button>
  </div>
</div>

<form id="csrfForm" style="display:none">
  <input type="hidden" name="csrf" value="<?=csrf_token()?>">
</form>

<script>
function toggleHint(){
  const el = document.getElementById('hint');
  el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}
function toggleNote(){
  const el = document.getElementById('noteBox');
  el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}

async function submitAnswer(val){
  const res = await fetch(`${BASE_URL}/student/api/practice_submit.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`question_id=<?= (int)$q['id'] ?>&user_answer=${encodeURIComponent(val)}&task_id=<?= (int)$taskId ?>`
  });
  const data = await res.json();

  document.getElementById('feedback').innerHTML = `
    <div class="toast">
      <b>${data.correct ? 'Correct!' : 'Incorrect.'}</b>
      ${data.points_added ? `<div class="muted" style="margin-top:6px">+${data.points_added} points</div>`:''}
      ${data.explanation ? `<div class="muted" style="margin-top:8px">${escapeHtml(data.explanation)}</div>`:''}
      ${data.example ? `<div class="muted" style="margin-top:8px">Example: ${escapeHtml(data.example)}</div>`:''}
      ${<?= (int)$taskId ?> > 0 ? `<div style="margin-top:12px"><a class="btn primary" href="${BASE_URL}/student/task_next.php?task_id=<?= (int)$taskId ?>">Next question →</a></div>` : ''}
    </div>
  `;

  document.querySelectorAll('button.btn').forEach(b=>{
    // navbar butonlarını kilitleme: sadece soru seçeneklerini kilitlemek istersen sınıf ekleyebilirsin
  });
}

async function toggleFav(){
  const csrf = document.querySelector('#csrfForm input[name="csrf"]').value;
  const res = await fetch(`${BASE_URL}/student/api/favorite_toggle_ajax.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`csrf=${encodeURIComponent(csrf)}&fav_type=question&ref_id=<?= (int)$q['id'] ?>`
  });
  const data = await res.json();
  if(!data.ok){ alert('Action failed'); return; }

  document.getElementById('favBtn').textContent =
    (data.state === 'added') ? '★ Saved' : '☆ Save';
}

async function saveNote(){
  const csrf = document.querySelector('#csrfForm input[name="csrf"]').value;
  const term = document.getElementById('term').value.trim();
  const meaning = document.getElementById('meaning').value.trim();
  const example = document.getElementById('example').value.trim();
  const note = document.getElementById('note').value.trim();
  if(!term){ alert('Word cannot be empty'); return; }

  const res = await fetch(`${BASE_URL}/student/api/notebook_add_ajax.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`csrf=${encodeURIComponent(csrf)}&term=${encodeURIComponent(term)}&meaning=${encodeURIComponent(meaning)}&example=${encodeURIComponent(example)}&note=${encodeURIComponent(note)}`
  });
  const data = await res.json();
  alert(data.ok ? 'Added to Notebook ✅' : 'Could not save');
}

function escapeHtml(str){
  return (str ?? '').toString()
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'","&#039;");
}
</script>
</body>
</html>
