<?php
// Ajuste aqui:
define('DB_DSN',  'mysql:host=127.0.0.1;dbname=autoparts;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', '');

function db() {
  static $pdo;
  if (!$pdo) {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  return $pdo;
}

// Sanitiza referências (ABC-123 -> ABC123; abc.123 -> ABC123)
function clean_ref($s) {
  $s = strtoupper($s ?? '');
  return str_replace(['-',' ','.'], '', $s);
}
