<?php
require_once __DIR__ . '/../../includes/rbac.php';

header('Content-Type: application/json; charset=utf-8');
$u = require_role('student');

function base_diff(string $level): int {
  if ($level === 'A1') return 1;
  if ($level === 'A2') return 2;
  if ($level === 'B1') return 3;
  if ($level === 'B2') return 4;
  return 5;
}

// zayıf skill bul
$st = db()->prepare("
  SELECT q.skill,
         AVG(CASE WHEN qa.is_correct=1 THEN 1 ELSE 0 END) AS acc
  FROM question_attempts qa
  JOIN questions q ON q.id=qa.question_id
  WHERE qa.user_id=? AND qa.created_at >= (NOW() - INTERVAL 30 DAY)
  GROUP BY q.skill
");
$st->execute([$u['id']]);
$rows = $st->fetchAll();

$skillAcc = ['vocab'=>1,'grammar'=>1,'reading'=>1,'listening'=>1,'writing'=>1];
foreach($rows as $r){ $skillAcc[$r['skill']] = (float)$r['acc']; }
asort($skillAcc);
$weak = array_slice(array_keys($skillAcc), 0, 2);

$bd = base_diff((string)$u['level']);

$pick = db()->prepare("
  SELECT id, title, skill, material_type, difficulty, level
  FROM lessons
  WHERE is_active=1
    AND skill IN (?,?)
    AND difficulty BETWEEN ? AND ?
    AND (level IS NULL OR level='' OR level=?)
  ORDER BY RAND()
  LIMIT 1
");
$pick->execute([$weak[0], $weak[1], max(1,$bd-1), min(5,$bd+1), $u['level']]);
$lesson = $pick->fetch();

if(!$lesson){
  // fallback
  $lesson = db()->query("
    SELECT id, title, skill, material_type, difficulty, level
    FROM lessons
    WHERE is_active=1
    ORDER BY RAND()
    LIMIT 1
  ")->fetch();
}

echo json_encode(['ok'=> (bool)$lesson, 'lesson'=>$lesson], JSON_UNESCAPED_UNICODE);
