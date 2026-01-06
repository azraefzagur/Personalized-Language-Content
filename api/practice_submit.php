<?php
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/utils.php';

header('Content-Type: application/json; charset=utf-8');
$u = require_role('student');

$qid = (int)($_POST['question_id'] ?? 0);
$ans = trim($_POST['user_answer'] ?? '');
$taskId = (int)($_POST['task_id'] ?? 0);
if ($taskId <= 0) $taskId = null;

if (!$qid) { echo json_encode(['ok'=>false], JSON_UNESCAPED_UNICODE); exit; }

$q = db()->prepare("SELECT id, correct_answer, explanation, example_sentence, choices_json FROM questions WHERE id=? AND is_active=1");
$q->execute([$qid]);
$row = $q->fetch();
if(!$row){ echo json_encode(['ok'=>false], JSON_UNESCAPED_UNICODE); exit; }

$isCorrect = false;
if ($row['choices_json']) {
  $isCorrect = ((string)$ans === (string)$row['correct_answer']);
} else {
  $isCorrect = (mb_strtolower($ans) === mb_strtolower((string)$row['correct_answer']));
}

db()->prepare("INSERT INTO question_attempts(user_id,question_id,task_id,is_correct,user_answer,attempt_type)
               VALUES(?,?,?,?,?, 'practice')")
  ->execute([$u['id'],$qid, $taskId, $isCorrect?1:0, $ans]);

$pts = $isCorrect ? 6 : 2;
db()->prepare("UPDATE users SET points = points + ? WHERE id=?")->execute([$pts, $u['id']]);
award_badges_if_needed((int)$u['id']);

echo json_encode([
  'ok'=>true,
  'correct'=>$isCorrect,
  'explanation'=>$row['explanation'],
  'example'=>$row['example_sentence'],
  'points_added'=>$pts
], JSON_UNESCAPED_UNICODE);
