<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('admin');

$id = (int)($_GET['id'] ?? 0);
if(!$id){ exit("Missing id"); }

$st = db()->prepare("SELECT * FROM questions WHERE id=?");
$st->execute([$id]);
$q = $st->fetch();
if(!$q){ exit("Question not found"); }

$ok = $err = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();

  $skill = $_POST['skill'];
  $topic = trim($_POST['topic'] ?? '');
  $diff  = (int)$_POST['difficulty'];
  $isPl  = (int)($_POST['is_placement'] ?? 0);
  $prompt= trim($_POST['prompt'] ?? '');
  $choicesText = trim($_POST['choices'] ?? '');
  $correct = trim($_POST['correct_answer'] ?? '');
  $hint = trim($_POST['hint'] ?? '');
  $exp  = trim($_POST['explanation'] ?? '');
  $exs  = trim($_POST['example_sentence'] ?? '');
  $media= trim($_POST['media_url'] ?? '');
  $active = (int)($_POST['is_active'] ?? 1);

  $choicesJson = null;
  if ($choicesText !== '') {
    $arr = array_values(array_filter(array_map('trim', explode("\n",$choicesText))));
    $choicesJson = json_encode($arr, JSON_UNESCAPED_UNICODE);
  }

  $up = db()->prepare("
    UPDATE questions
    SET skill=?, topic=?, difficulty=?, is_placement=?, prompt=?, choices_json=?, correct_answer=?,
        media_url=?, hint=?, explanation=?, example_sentence=?, is_active=?
    WHERE id=?
  ");
  $up->execute([
    $skill,$topic?:null,$diff,$isPl,$prompt,$choicesJson,$correct,
    $media ?: null, $hint ?: null, $exp ?: null, $exs ?: null,
    $active, $id
  ]);

  $ok = "Updated.";

  // reload
  $st->execute([$id]);
  $q = $st->fetch();
}

$choicesForTextarea = '';
if($q['choices_json']){
  $arr = json_decode($q['choices_json'], true) ?: [];
  $choicesForTextarea = implode("\n", $arr);
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Edit Question</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <div class="nav">
    <div class="brand"><div class="logo"></div> Admin · Edit</div>
    <div class="nav-right">
      <a class="btn" href="<?=BASE_URL?>/admin/questions.php">Questions</a>
      <a class="btn" href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="h1">Question #<?=$id?></div>
    <div class="muted">For MCQ: correct_answer = index (0,1,2,3...). For writing: the exact text.</div>
    <div class="hr"></div>

    <?php if($ok): ?><div class="toast"><?=htmlspecialchars($ok)?></div><div class="hr"></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>">

      <div class="row">
        <select class="input" name="skill" required>
          <?php foreach(['vocab','grammar','reading','listening','writing'] as $s): ?>
            <option value="<?=$s?>" <?=$q['skill']===$s?'selected':''?>><?=$s?></option>
          <?php endforeach; ?>
        </select>
        <input class="input" type="number" name="difficulty" min="1" max="5" value="<?= (int)$q['difficulty'] ?>" required>
      </div><br>

      <input class="input" name="topic" value="<?=htmlspecialchars($q['topic'] ?? '')?>" placeholder="Topic (free text, e.g., Past Simple)"><br><br>
      <label class="muted"><input type="checkbox" name="is_placement" value="1" <?=$q['is_placement']?'checked':''?>> Placement question</label><br><br>
      <label class="muted"><input type="checkbox" name="is_active" value="1" <?=$q['is_active']?'checked':''?>> Active</label><br><br>

      <textarea class="input" name="prompt" rows="3" required><?=htmlspecialchars($q['prompt'])?></textarea><br><br>
      <textarea class="input" name="choices" rows="5" placeholder="MCQ options (one per line). Leave empty for writing questions."><?=htmlspecialchars($choicesForTextarea)?></textarea><br><br>
      <input class="input" name="correct_answer" value="<?=htmlspecialchars($q['correct_answer'] ?? '')?>" placeholder="correct_answer"><br><br>
      <input class="input" name="media_url" value="<?=htmlspecialchars($q['media_url'] ?? '')?>" placeholder="media_url"><br><br>
      <textarea class="input" name="hint" rows="2" placeholder="hint"><?=htmlspecialchars($q['hint'] ?? '')?></textarea><br><br>
      <textarea class="input" name="explanation" rows="2" placeholder="explanation"><?=htmlspecialchars($q['explanation'] ?? '')?></textarea><br><br>
      <input class="input" name="example_sentence" value="<?=htmlspecialchars($q['example_sentence'] ?? '')?>" placeholder="example_sentence"><br><br>

      <button class="btn primary" style="width:100%">Save</button>
    </form>
  </div>
</div>
</body>
</html>
