<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$action = $_GET['action'] ?? 'by_text';

function respond($data, $code=200){
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {

  if ($action === 'by_ref') {
    $q = trim($_GET['q'] ?? '');
    if ($q==='') respond(['error'=>'param q obrigatório'], 400);

    $sql = "SELECT p.id, p.reference, p.name, p.description,
                   IFNULL(b.name,'') AS brand, IFNULL(c.name,'') AS category
            FROM product p
            LEFT JOIN brand b ON b.id = p.brand_id
            LEFT JOIN category c ON c.id = p.category_id
            WHERE p.reference_clean = :rc OR p.reference = :raw
            LIMIT 30";
    $st = $pdo->prepare($sql);
    $st->execute([':rc'=>clean_ref($q), ':raw'=>$q]);
    respond($st->fetchAll());
  }

  if ($action === 'by_cross') {
    $q = trim($_GET['q'] ?? '');
    if ($q==='') respond(['error'=>'param q obrigatório'], 400);

    $sql = "SELECT DISTINCT p.id, p.reference, p.name, IFNULL(b.name,'') AS brand
            FROM product p
            LEFT JOIN brand b ON b.id = p.brand_id
            LEFT JOIN cross_reference cr ON cr.product_id = p.id
            WHERE p.reference_oem_clean = :rc OR cr.code_clean = :rc
            LIMIT 30";
    $st = $pdo->prepare($sql);
    $st->execute([':rc'=>clean_ref($q)]);
    respond($st->fetchAll());
  }

  if ($action === 'by_text') {
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 2) respond(['error'=>'mínimo 2 caracteres'], 400);

    // FULLTEXT em name/description + fallback em LIKE
    $sql = "SELECT p.id, p.reference, p.name, MATCH(p.name, p.description) AGAINST(:t IN NATURAL LANGUAGE MODE) AS score
            FROM product p
            WHERE MATCH(p.name, p.description) AGAINST(:t IN NATURAL LANGUAGE MODE)
            ORDER BY score DESC
            LIMIT 50";
    $st = $pdo->prepare($sql);
    try {
      $st->execute([':t'=>$q]);
      $rows = $st->fetchAll();
      if ($rows) respond($rows);
    } catch (Throwable $e) {
      // fallback (se FULLTEXT indisponível)
    }

    $sql2 = "SELECT p.id, p.reference, p.name
             FROM product p
             WHERE p.name LIKE :q OR p.description LIKE :q
             ORDER BY p.name
             LIMIT 50";
    $st2 = $pdo->prepare($sql2);
    $st2->execute([':q'=>"%$q%"]);
    respond($st2->fetchAll());
  }

  if ($action === 'by_application') {
    $brand = trim($_GET['brand'] ?? '');
    $model = trim($_GET['model'] ?? '');
    $year  = isset($_GET['year']) ? (int)$_GET['year'] : null;
    $piece = trim($_GET['piece'] ?? ''); // texto da peça (ex.: barra, filtro)

    if ($brand==='' || $model==='') respond(['error'=>'brand e model obrigatórios'], 400);

    $params = [':b'=>$brand, ':m'=>$model];
    $yearClause = '';
    if ($year) {
      $yearClause = "AND (:y BETWEEN IFNULL(pa.year_from,:y) AND IFNULL(pa.year_to,:y))";
      $params[':y'] = $year;
    }

    $pieceClause = '';
    if ($piece !== '') {
      // procura por texto no nome/descrição
      $pieceClause = "AND (p.name LIKE :piece OR p.description LIKE :piece)";
      $params[':piece'] = "%$piece%";
    }

    $sql = "SELECT DISTINCT
              p.id, p.reference, p.name, IFNULL(p.description,'') AS description,
              IFNULL(b.name,'') AS brand, IFNULL(c.name,'') AS category,
              vm.brand_name AS vehicle_brand, vm.model_name AS vehicle_model,
              pa.year_from, pa.year_to, pa.engine, pa.fuel, pa.body
            FROM product_application pa
            JOIN product p      ON p.id = pa.product_id
            JOIN vehicle_model vm ON vm.id = pa.vehicle_id
            LEFT JOIN brand b   ON b.id = p.brand_id
            LEFT JOIN category c ON c.id = p.category_id
            WHERE vm.brand_name = :b AND vm.model_name = :m
              $yearClause
              $pieceClause
            ORDER BY p.name
            LIMIT 100";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    respond($st->fetchAll());
  }

  if ($action === 'similars') {
    $pid = (int)($_GET['product_id'] ?? 0);
    if (!$pid) respond(['error'=>'product_id obrigatório'], 400);

    $sql = "SELECT ps.relation_type, p2.id, p2.reference, p2.name
            FROM similar_product ps
            JOIN product p2 ON p2.id = ps.similar_id
            WHERE ps.product_id = :pid";
    $st = $pdo->prepare($sql);
    $st->execute([':pid'=>$pid]);
    respond($st->fetchAll());
  }

  if ($action === 'list_brands') {
    $st = $pdo->query("SELECT DISTINCT brand_name FROM vehicle_model ORDER BY brand_name");
    respond($st->fetchAll());
  }

  if ($action === 'list_models') {
    $brand = trim($_GET['brand'] ?? '');
    if ($brand==='') respond(['error'=>'brand obrigatório'], 400);
    $st = $pdo->prepare("SELECT model_name FROM vehicle_model WHERE brand_name=:b ORDER BY model_name");
    $st->execute([':b'=>$brand]);
    respond($st->fetchAll());
  }

  respond(['error'=>'ação inválida'], 400);

} catch (Throwable $e) {
  respond(['error'=>'server', 'detail'=>$e->getMessage()], 500);
}
