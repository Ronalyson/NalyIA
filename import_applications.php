<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['csv'])) {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $f = fopen($_FILES['csv']['tmp_name'], 'r');
    $header = fgetcsv($f);
    // Espera: product_reference,vehicle_brand,vehicle_model,year_from,year_to,engine,fuel,body,notes
    $ix = array_flip($header);

    while (($row = fgetcsv($f)) !== false) {
      $product_reference = trim($row[$ix['product_reference']] ?? '');
      $vehicle_brand     = trim($row[$ix['vehicle_brand']] ?? '');
      $vehicle_model     = trim($row[$ix['vehicle_model']] ?? '');
      $year_from         = trim($row[$ix['year_from']] ?? '');
      $year_to           = trim($row[$ix['year_to']] ?? '');
      $engine            = trim($row[$ix['engine']] ?? '');
      $fuel              = trim($row[$ix['fuel']] ?? '');
      $body              = trim($row[$ix['body']] ?? '');
      $notes             = trim($row[$ix['notes']] ?? '');

      if ($product_reference==='' || $vehicle_brand==='' || $vehicle_model==='') continue;

      // Produto
      $stp = $pdo->prepare("SELECT id FROM product WHERE reference_clean = :rc OR reference = :ref LIMIT 1");
      $stp->execute([':rc'=>clean_ref($product_reference), ':ref'=>$product_reference]);
      $product_id = (int)$stp->fetchColumn();
      if (!$product_id) continue;

      // Veículo (upsert)
      $stv = $pdo->prepare("INSERT IGNORE INTO vehicle_model(brand_name, model_name) VALUES (:b,:m)");
      $stv->execute([':b'=>$vehicle_brand, ':m'=>$vehicle_model]);
      $vehicle_id = (int)$pdo->lastInsertId();
      if (!$vehicle_id) {
        $stv2 = $pdo->prepare("SELECT id FROM vehicle_model WHERE brand_name=:b AND model_name=:m");
        $stv2->execute([':b'=>$vehicle_brand, ':m'=>$vehicle_model]);
        $vehicle_id = (int)$stv2->fetchColumn();
      }

      // Inserir aplicação
      $sta = $pdo->prepare("
        INSERT INTO product_application (product_id, vehicle_id, year_from, year_to, engine, fuel, body, notes)
        VALUES (:pid,:vid,:yf,:yt,:e,:f,:b,:n)
      ");
      $sta->execute([
        ':pid'=>$product_id, ':vid'=>$vehicle_id,
        ':yf'=>($year_from!==''? (int)$year_from : null),
        ':yt'=>($year_to!==''? (int)$year_to : null),
        ':e'=>$engine?:null, ':f'=>$fuel?:null, ':b'=>$body?:null, ':n'=>$notes?:null
      ]);
    }
    fclose($f);
    $pdo->commit();
    echo "OK: aplicações importadas.";
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
<h2>Importar Aplicações (CSV)</h2>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="csv" accept=".csv" required>
  <button>Importar</button>
</form>
<p>Esperado: product_reference,vehicle_brand,vehicle_model,year_from,year_to,engine,fuel,body,notes</p>
</body></html>
