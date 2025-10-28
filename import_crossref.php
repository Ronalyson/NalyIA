<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['csv'])) {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $f = fopen($_FILES['csv']['tmp_name'], 'r');
    $header = fgetcsv($f);
    // Espera: product_reference,code,brand_name,source,notes
    $ix = array_flip($header);

    while (($row = fgetcsv($f)) !== false) {
      $product_reference = trim($row[$ix['product_reference']] ?? '');
      $code              = trim($row[$ix['code']] ?? '');
      $brand_name        = trim($row[$ix['brand_name']] ?? '');
      $source            = trim($row[$ix['source']] ?? 'AFTERMARKET');
      $notes             = trim($row[$ix['notes']] ?? '');

      if ($product_reference==='' || $code==='') continue;

      $stp = $pdo->prepare("SELECT id FROM product WHERE reference_clean = :rc OR reference = :ref LIMIT 1");
      $stp->execute([':rc'=>clean_ref($product_reference), ':ref'=>$product_reference]);
      $product_id = (int)$stp->fetchColumn();
      if (!$product_id) continue;

      $st = $pdo->prepare("INSERT INTO cross_reference (product_id, code, brand_name, source, notes)
                           VALUES (:pid,:c,:bn,:s,:n)");
      $st->execute([
        ':pid'=>$product_id, ':c'=>$code, ':bn'=>$brand_name?:null, ':s'=>$source, ':n'=>$notes?:null
      ]);
    }
    fclose($f);
    $pdo->commit();
    echo "OK: cross references importadas.";
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
<h2>Importar Cross-Reference (CSV)</h2>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="csv" accept=".csv" required>
  <button>Importar</button>
</form>
<p>Esperado: product_reference,code,brand_name,source,notes</p>
</body></html>
