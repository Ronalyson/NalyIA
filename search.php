<?php
header('Content-Type: application/json; charset=utf-8');
$dsn  = "mysql:host=127.0.0.1;dbname=autoparts;charset=utf8mb4";
$user = "root";
$pass = "";
try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["error" => "DB connection failed"]);
  exit;
}

$q = $_GET['q'] ?? ''; // Palavra-chave de pesquisa

if (!$q) {
    echo json_encode(["error" => "Nenhuma pesquisa fornecida"]);
    exit;
}

// Busca por referência ou nome
$sql = "SELECT p.id, p.reference, p.name, p.description, b.name AS brand, c.name AS category
        FROM product p
        LEFT JOIN brand b ON b.id = p.brand_id
        LEFT JOIN category c ON c.id = p.category_id
        WHERE MATCH(p.name, p.description) AGAINST(:q IN NATURAL LANGUAGE MODE)
        OR p.reference LIKE :q";
$stmt = $pdo->prepare($sql);
$stmt->execute([':q' => "%$q%"]);
$results = $stmt->fetchAll();

echo json_encode($results);
