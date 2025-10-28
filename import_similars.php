<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['csv'])) {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $f = fopen($_FILES['csv']['tmp_name'], 'r');
    $header = fgetcsv($f);
    // Espera: product_reference,similar_reference,relation_type
    $ix = array_flip($header);

    while (($row = fgetcsv($f)) !== false) {
      $r1 = trim($row[$ix['product_reference']] ?? '');
      $r2 = trim($row[$ix['similar_reference']] ?? '');
      $rt = trim($row[$ix['relation_type']] ?? 'EQUIVALENTE');
      if ($r1==='' || $r2==='') continue;

      $q = "SELECT id FROM product WHERE reference_clean=:rc OR reference=:ref LIMIT 1";

      $st1 = db()->prepare($q); $st1->execute([':rc'=>clean_ref($r1), ':ref'=>$r1]); $p1 = (int)$st1->fetchColumn();
      $st2 = db()->prepare($q); $st2->execute([':rc'=>clean_ref($r2), ':ref'=>$r2]); $p2 = (int)$st2->fetchColumn();
      if (!$p1 || !$p2) continue;

      $st = db()->prepare("REPLACE INTO similar_product (product_id, similar_id, relation_type) VALUES (:a,:b,:t)");
      $st->execute([':a'=>$p1, ':b'=>$p2, ':t'=>$rt]);
    }
    fclose($f);
    $pdo->commit();
    echo "OK: similares importados.";
  } catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "ERRO: ".$e->getMessage();
  }
  exit;
}
?>
<!doctype html>
<html lang="pt-br"><body>
<h2>Importar Similares (CSV)</h2>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="csv" accept=".csv" required>
  <button>Importar</button>
</form>
<p>Esperado: product_reference,similar_reference,relation_type</p>
</body></html>
