<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['csv'])) {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $f = fopen($_FILES['csv']['tmp_name'], 'r');
    $header = fgetcsv($f); // cabeçalhos
    // Espera: reference,name,description,brand,category,reference_oem,specs
    $ix = array_flip($header);

    while (($row = fgetcsv($f)) !== false) {
      $reference    = trim($row[$ix['reference']] ?? '');
      $name         = trim($row[$ix['name']] ?? '');
      $description  = trim($row[$ix['description']] ?? '');
      $brand_name   = trim($row[$ix['brand']] ?? '');
      $category_name= trim($row[$ix['category']] ?? '');
      $reference_oem= trim($row[$ix['reference_oem']] ?? '');
      $specs        = trim($row[$ix['specs']] ?? '');

      if ($reference==='' || $name==='') continue;

      // Marca
      $brand_id = null;
      if ($brand_name!=='') {
        $st = $pdo->prepare("INSERT IGNORE INTO brand(name) VALUES (:n)");
        $st->execute([':n'=>$brand_name]);
        $brand_id = (int)$pdo->lastInsertId();
        if (!$brand_id) {
          $brand_id = (int)$pdo->query("SELECT id FROM brand WHERE name=".$pdo->quote($brand_name))->fetchColumn();
        }
      }

      // Categoria
      $category_id = null;
      if ($category_name!=='') {
        $st = $pdo->prepare("INSERT IGNORE INTO category(name) VALUES (:n)");
        $st->execute([':n'=>$category_name]);
        $category_id = (int)$pdo->lastInsertId();
        if (!$category_id) {
          $category_id = (int)$pdo->query("SELECT id FROM category WHERE name=".$pdo->quote($category_name))->fetchColumn();
        }
      }

      // Produto (upsert por reference)
      $st = $pdo->prepare("
        INSERT INTO product(reference,name,description,brand_id,category_id,reference_oem,specs)
        VALUES (:r,:n,:d,:b,:c,:o,:s)
        ON DUPLICATE KEY UPDATE
          name=VALUES(name),
          description=VALUES(description),
          brand_id=VALUES(brand_id),
          category_id=VALUES(category_id),
          reference_oem=VALUES(reference_oem),
          specs=VALUES(specs),
          updated_at=NOW()
      ");
      $st->execute([
        ':r'=>$reference, ':n'=>$name, ':d'=>$description ?: null,
        ':b'=>$brand_id?:null, ':c'=>$category_id?:null,
        ':o'=>$reference_oem?:null,
        ':s'=>$specs?:null
      ]);
    }
    fclose($f);
    $pdo->commit();
    echo "OK: produtos importados.";
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
<h2>Importar Produtos (CSV)</h2>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="csv" accept=".csv" required>
  <button>Importar</button>
</form>
<p>Esperado: reference,name,description,brand,category,reference_oem,specs</p>
</body></html>
