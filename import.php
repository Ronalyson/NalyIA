<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $f = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = fgetcsv($f); // Cabeçalhos do CSV
        $ix = array_flip($header);

        while (($row = fgetcsv($f)) !== false) {
            // Produtos
            $reference = trim($row[$ix['reference']] ?? '');
            $name = trim($row[$ix['name']] ?? '');
            $description = trim($row[$ix['description']] ?? '');
            $brand_name = trim($row[$ix['brand']] ?? '');
            $category_name = trim($row[$ix['category']] ?? '');
            $reference_oem = trim($row[$ix['reference_oem']] ?? '');
            $specs = trim($row[$ix['specs']] ?? '');

            // Veículo
            $vehicle_brand = trim($row[$ix['vehicle_brand']] ?? '');
            $vehicle_model = trim($row[$ix['vehicle_model']] ?? '');
            $year_from = trim($row[$ix['year_from']] ?? '');
            $year_to = trim($row[$ix['year_to']] ?? '');
            $engine = trim($row[$ix['engine']] ?? '');
            $fuel = trim($row[$ix['fuel']] ?? '');
            $body = trim($row[$ix['body']] ?? '');
            $notes = trim($row[$ix['notes']] ?? '');

            // Referência cruzada
            $cross_reference_code = trim($row[$ix['cross_reference_code']] ?? '');
            $cross_reference_brand = trim($row[$ix['cross_reference_brand']] ?? '');
            $cross_reference_source = trim($row[$ix['cross_reference_source']] ?? '');
            
            // Similar
            $similar_reference = trim($row[$ix['similar_reference']] ?? '');
            $similar_relation_type = trim($row[$ix['similar_relation_type']] ?? '');

            // Se o produto não tiver uma referência válida, ignora a linha
            if ($reference === '' || $name === '') continue;

            // Inserir Marca
            $brand_id = null;
            if ($brand_name !== '') {
                $st = $pdo->prepare("INSERT IGNORE INTO brand(name) VALUES (:n)");
                $st->execute([':n' => $brand_name]);
                $brand_id = (int)$pdo->lastInsertId();
                if (!$brand_id) {
                    $brand_id = (int)$pdo->query("SELECT id FROM brand WHERE name=" . $pdo->quote($brand_name))->fetchColumn();
                }
            }

            // Inserir Categoria
            $category_id = null;
            if ($category_name !== '') {
                $st = $pdo->prepare("INSERT IGNORE INTO category(name) VALUES (:n)");
                $st->execute([':n' => $category_name]);
                $category_id = (int)$pdo->lastInsertId();
                if (!$category_id) {
                    $category_id = (int)$pdo->query("SELECT id FROM category WHERE name=" . $pdo->quote($category_name))->fetchColumn();
                }
            }

            // Inserir Produto
            $st = $pdo->prepare("
                INSERT INTO product(reference,name,description,brand_id,category_id,reference_oem,specs)
                VALUES (:r, :n, :d, :b, :c, :o, :s)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    brand_id = VALUES(brand_id),
                    category_id = VALUES(category_id),
                    reference_oem = VALUES(reference_oem),
                    specs = VALUES(specs),
                    updated_at = NOW()
            ");
            $st->execute([
                ':r' => $reference, ':n' => $name, ':d' => $description ?: null,
                ':b' => $brand_id ?: null, ':c' => $category_id ?: null,
                ':o' => $reference_oem ?: null, ':s' => $specs ?: null
            ]);

            // Inserir Aplicações (Produto x Veículo)
            $product_id = (int)$pdo->lastInsertId();
            if ($vehicle_brand !== '' && $vehicle_model !== '') {
                // Inserir ou pegar ID do veículo
                $stv = $pdo->prepare("INSERT IGNORE INTO vehicle_model(brand_name, model_name) VALUES (:b, :m)");
                $stv->execute([':b' => $vehicle_brand, ':m' => $vehicle_model]);
                $vehicle_id = (int)$pdo->lastInsertId();
                if (!$vehicle_id) {
                    $stv2 = $pdo->prepare("SELECT id FROM vehicle_model WHERE brand_name = :b AND model_name = :m");
                    $stv2->execute([':b' => $vehicle_brand, ':m' => $vehicle_model]);
                    $vehicle_id = (int)$stv2->fetchColumn();
                }

                // Inserir aplicação
                $sta = $pdo->prepare("
                    INSERT INTO product_application (product_id, vehicle_id, year_from, year_to, engine, fuel, body, notes)
                    VALUES (:pid, :vid, :yf, :yt, :e, :f, :b, :n)
                ");
                $sta->execute([
                    ':pid' => $product_id, ':vid' => $vehicle_id,
                    ':yf' => ($year_from !== '' ? (int)$year_from : null),
                    ':yt' => ($year_to !== '' ? (int)$year_to : null),
                    ':e' => $engine ?: null, ':f' => $fuel ?: null, ':b' => $body ?: null, ':n' => $notes ?: null
                ]);
            }

            // Inserir Referência Cruzada
            if ($cross_reference_code !== '') {
                $st = $pdo->prepare("INSERT INTO cross_reference (product_id, code, brand_name, source, notes)
                                     VALUES (:pid, :c, :bn, :s, :n)");
                $st->execute([
                    ':pid' => $product_id, ':c' => $cross_reference_code, ':bn' => $cross_reference_brand ?: null,
                    ':s' => $cross_reference_source ?: 'AFTERMARKET', ':n' => ''
                ]);
            }

            // Inserir Similar
            if ($similar_reference !== '') {
                $st = $pdo->prepare("INSERT INTO similar_product (product_id, similar_id, relation_type)
                                     VALUES (:pid, :sim_pid, :relation_type)");
                $st->execute([
                    ':pid' => $product_id, ':sim_pid' => $similar_reference, ':relation_type' => $similar_relation_type
                ]);
            }
        }
        fclose($f);
        $pdo->commit();
        echo "Importação concluída!";
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo "Erro: " . $e->getMessage();
    }
    exit;
}
?>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="csv" accept=".csv" required>
    <button>Importar CSV</button>
</form>
