<?php
require_once __DIR__ . '/../includes/rbac.php';

$u = require_role('student');
$taskId = (int)($_GET['task_id'] ?? 0);
if ($taskId <= 0) { header('Location: '.BASE_URL.'/student/dashboard.php'); exit; }

// Ensure task belongs to user
$st = db()->prepare("SELECT id FROM user_tasks WHERE id=? AND user_id=? LIMIT 1");
$st->execute([$taskId, $u['id']]);
if (!$st->fetch()) { header('Location: '.BASE_URL.'/student/dashboard.php'); exit; }

// Next unanswered
$qst = db()->prepare(
  "SELECT uti.question_id
   FROM user_task_items uti
   LEFT JOIN question_attempts qa
     ON qa.user_id=? AND qa.task_id=? AND qa.question_id=uti.question_id
   WHERE uti.task_id=? AND qa.id IS NULL
   ORDER BY uti.position ASC
   LIMIT 1"
);
$qst->execute([$u['id'], $taskId, $taskId]);
$qid = (int)($qst->fetchColumn() ?: 0);

if ($qid <= 0) {
  db()->prepare("UPDATE user_tasks SET status='done', completed_at=NOW() WHERE id=? AND user_id=?")
    ->execute([$taskId, $u['id']]);
  header('Location: '.BASE_URL.'/student/dashboard.php?task_done=1');
  exit;
}

header('Location: '.BASE_URL.'/student/practice_view.php?qid='.$qid.'&task_id='.$taskId);
exit;
