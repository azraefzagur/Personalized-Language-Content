<?php
require_once __DIR__ . '/db.php';

function start_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
  }
}

function current_user(): ?array {
  start_session();
  if (empty($_SESSION['uid'])) return null;

  $st = db()->prepare("SELECT * FROM users WHERE id=?");
  $st->execute([$_SESSION['uid']]);
  $u = $st->fetch();
  return $u ?: null;
}

function require_login(): array {
  $u = current_user();
  if (!$u) {
    header("Location: ".BASE_URL."/public/index.php");
    exit;
  }
  // touch last active
  $st = db()->prepare("UPDATE users SET last_active_at=NOW() WHERE id=?");
  $st->execute([$u['id']]);
  return $u;
}

function login(string $email, string $password): ?array {
  $st = db()->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch();
  if ($u && password_verify($password, $u['password_hash'])) {
    start_session();
    $_SESSION['uid'] = $u['id'];
    return $u;
  }
  return null;
}

function register_user(string $name, string $email, string $password): array {
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $st = db()->prepare("INSERT INTO users(role,email,password_hash,full_name,last_active_at) VALUES('student',?,?,?,NOW())");
  $st->execute([$email, $hash, $name]);
  $id = (int)db()->lastInsertId();
  start_session();
  $_SESSION['uid'] = $id;
  $st2 = db()->prepare("SELECT * FROM users WHERE id=?");
  $st2->execute([$id]);
  return $st2->fetch();
}

function logout(): void {
  start_session();
  $_SESSION = [];
  session_destroy();
}
