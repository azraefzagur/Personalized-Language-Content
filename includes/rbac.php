<?php
require_once __DIR__ . '/auth.php';

function require_role(string $role): array {
  $u = require_login();
  if ($u['role'] !== $role) {
    http_response_code(403);
    exit('Forbidden');
  }
  return $u;
}
