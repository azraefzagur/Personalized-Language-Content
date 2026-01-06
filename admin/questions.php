<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('admin');
$err = $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    $skill = $_POST['skill'];
    $diff  = (int)$_POST['difficulty'];
    $isPl  = (int)($_POST['is_placement'] ?? 0);
    $prompt= trim($_POST['prompt'] ?? '');
    $choices = trim($_POST['choices'] ?? ''); // satır satır
    $correct = trim($_POST['correct_answer'] ?? '');
    $hint = trim($_POST['hint'] ?? '');
    $exp  = trim($_POST['explanation'] ?? '');
    $exs  = trim($_POST['example_sentence'] ?? '');
    $media= trim($_POST['media_url'] ?? '');

    $choicesJson = null;
    if ($choices !== '') {
      $arr = array_values(array_filter(array_map('trim', explode("\n",$choices))));
      $choicesJson = json_encode($arr, JSON_UNESCAPED_UNICODE);
    }

    $st = db()->prepare("INSERT INTO questions(skill,difficulty,is_placement,prompt,choices_json,correct_answer,media_url,hint,explanation,example_sentence)
                         VALUES(?,?,?,?,?,?,?,?,?,?)");
    $st->execute([$skill,$diff,$isPl,$prompt,$choicesJson,$correct,$media,$hint,$exp,$exs]);
    $ok = 'Soru eklendi.';
  }

  if ($action === 'delete') {
    $id = (int)$_POST['id'];
    db()->prepare("DELETE FROM questions WHERE id=?")->execute([$id]);
    $ok = 'Silindi.';
  }
}

$rows = db()->query("SELECT * FROM questions ORDER BY id DESC LIMIT 50")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Questions</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Admin</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <div class="h1">Add Question</div>
      <div class="muted">Manage placement and practice questions here.</div>
      <div class="hr"></div>

      <?php if($err): ?><div class="toast"><?=$err?></div><?php endif; ?>
      <?php if($ok): ?><div class="toast"><?=$ok?></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="add">

        <div class="row">
          <select class="input" name="skill" required>
            <option value="vocab">vocab</option>
            <option value="grammar">grammar</option>
            <option value="reading">reading</option>
            <option value="listening">listening</option>
            <option value="writing">writing</option>
          </select>
          <input class="input" type="number" name="difficulty" min="1" max="5" value="1" required>
        </div><br>

        <label class="muted"><input type="checkbox" name="is_placement" value="1"> Placement question</label><br><br>

        <textarea class="input" name="prompt" rows="3" placeholder="Prompt" required></textarea><br><br>
        <textarea class="input" name="choices" rows="4" placeholder="MCQ options (one per line). Leave empty for writing questions."></textarea><br><br>
        <input class="input" name="correct_answer" placeholder="Correct answer: for MCQ use option index (0,1,2,3); for writing use the exact text"><br><br>
        <input class="input" name="media_url" placeholder="Media URL (audio/image) optional"><br><br>
        <textarea class="input" name="hint" rows="2" placeholder="Hint (optional)"></textarea><br><br>
        <textarea class="input" name="explanation" rows="2" placeholder="Explanation (optional)"></textarea><br><br>
        <input class="input" name="example_sentence" placeholder="Example sentence (optional)"><br><br>

        <button class="btn primary" style="width:100%">Save</button>
      </form>
    </div>

    <div class="card">
      <div class="h1">Recent Questions</div>
      <div class="muted">Quick delete (MVP). You can extend the edit page with the same pattern.</div>
      <div class="hr"></div>

      <?php foreach($rows as $r): ?>
        <div class="card" style
        ="box-shadow:none; margin-bottom:10px">
          <div class="muted">#<?=$r['id']?> · <?=$r['skill']?> · diff <?=$r['difficulty']?> · placement <?=$r['is_placement']?></div>
          <div style="margin-top:6px"><b><?=htmlspecialchars(mb_strimwidth($r['prompt'],0,90,'...'))?></b></div>
          <form method="post" style="margin-top:10px" onsubmit="return confirm('Delete this question?')">
            <input type="hidden" name="csrf" value="<?=csrf_token()?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=$r['id']?>">
            <button class="btn">Delete</button> 
            <a class="btn" href="<?=BASE_URL?>/admin/edit_question.php?id=<?=$r['id']?>">Edit</a>

          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
