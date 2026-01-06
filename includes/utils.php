<?php
require_once __DIR__ . '/db.php';

function quote_of_day(): array {
  // Basit: rastgele
  $q = db()->query("SELECT quote_text, author FROM quotes ORDER BY RAND() LIMIT 1")->fetch();
  return $q ?: ['quote_text'=>'Welcome back.','author'=>null];
}

function level_from_scores(array $scores): string {
  // scores: 0..100
  $avg = array_sum($scores) / max(1,count($scores));
  if ($avg < 30) return 'A1';
  if ($avg < 50) return 'A2';
  if ($avg < 70) return 'B1';
  if ($avg < 85) return 'B2';
  return 'C1';
}

function award_badges_if_needed(int $userId): void {
  $u = db()->prepare("SELECT points FROM users WHERE id=?");
  $u->execute([$userId]);
  $points = (int)$u->fetchColumn();

  $badges = db()->query("SELECT id, points_required FROM badges")->fetchAll();
  foreach ($badges as $b) {
    if ($points >= (int)$b['points_required']) {
      $ins = db()->prepare("INSERT IGNORE INTO user_badges(user_id,badge_id) VALUES(?,?)");
      $ins->execute([$userId, $b['id']]);
    }
  }
}
