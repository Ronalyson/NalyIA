-- Banco + collation
CREATE DATABASE IF NOT EXISTS autoparts
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE autoparts;

-- Marcas e categorias de produto
CREATE TABLE IF NOT EXISTS brand (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  UNIQUE KEY uk_brand_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS category (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  UNIQUE KEY uk_category_name (name)
) ENGINE=InnoDB;

-- Produtos (peças)
CREATE TABLE IF NOT EXISTS product (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(120) NOT NULL,             -- código do fabricante
  reference_oem VARCHAR(120) NULL,             -- opcional: código OEM
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  brand_id INT NULL,
  category_id INT NULL,
  specs JSON NULL,                              -- ex.: {"rosca":"M18","altura":45}
  reference_clean VARCHAR(120) AS (
    UPPER(REPLACE(REPLACE(REPLACE(reference,'-',''),' ',''),'.',''))
  ) STORED,
  reference_oem_clean VARCHAR(120) AS (
    UPPER(REPLACE(REPLACE(REPLACE(IFNULL(reference_oem,''),'-',''),' ',''),'.',''))
  ) STORED,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_p_brand  FOREIGN KEY (brand_id)    REFERENCES brand(id),
  CONSTRAINT fk_p_cat    FOREIGN KEY (category_id) REFERENCES category(id),
  UNIQUE KEY uk_product_reference (reference),
  KEY ix_reference_clean (reference_clean),
  KEY ix_reference_oem_clean (reference_oem_clean),
  FULLTEXT KEY ft_product_name_desc (name, description)
) ENGINE=InnoDB;

-- Veículos (marca/modelo, com range de anos)
CREATE TABLE IF NOT EXISTS vehicle_model (
  id INT AUTO_INCREMENT PRIMARY KEY,
  brand_name VARCHAR(120) NOT NULL,    -- ex.: Toyota
  model_name VARCHAR(120) NOT NULL,    -- ex.: Corolla
  UNIQUE KEY uk_vehicle (brand_name, model_name),
  KEY ix_vehicle_brand (brand_name),
  KEY ix_vehicle_model (model_name)
) ENGINE=InnoDB;

-- Aplicações: relação peça x veículo + faixa de anos e notas
CREATE TABLE IF NOT EXISTS product_application (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT NOT NULL,
  vehicle_id INT NOT NULL,
  year_from SMALLINT NULL,
  year_to   SMALLINT NULL,
  engine VARCHAR(120) NULL,            -- opcional: 1.8 16V
  fuel   VARCHAR(40)  NULL,            -- gasolina/flex/diesel
  body   VARCHAR(60)  NULL,            -- sedan/hatch etc.
  notes  VARCHAR(255) NULL,
  CONSTRAINT fk_app_product FOREIGN KEY (product_id) REFERENCES product(id),
  CONSTRAINT fk_app_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicle_model(id),
  KEY ix_app_vehicle (vehicle_id),
  KEY ix_app_year_range (year_from, year_to)
) ENGINE=InnoDB;

-- Equivalências (OEM/concorrentes/códigos internos)
CREATE TABLE IF NOT EXISTS cross_reference (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT NOT NULL,
  code VARCHAR(120) NOT NULL,
  brand_name VARCHAR(120) NULL,            -- marca do código equivalente (GM, Wega etc.)
  code_clean VARCHAR(120) AS (UPPER(REPLACE(REPLACE(REPLACE(code,'-',''),' ',''),'.',''))) STORED,
  source ENUM('OEM','AFTERMARKET','INTERNO') DEFAULT 'AFTERMARKET',
  notes VARCHAR(255) NULL,
  CONSTRAINT fk_cr_product FOREIGN KEY (product_id) REFERENCES product(id),
  KEY ix_cr_code_clean (code_clean)
) ENGINE=InnoDB;

-- Similares N:N
CREATE TABLE IF NOT EXISTS similar_product (
  product_id BIGINT NOT NULL,
  similar_id BIGINT NOT NULL,
  relation_type ENUM('EQUIVALENTE','ALTERNATIVA','REPARO','UPGRADE') DEFAULT 'EQUIVALENTE',
  PRIMARY KEY (product_id, similar_id),
  CONSTRAINT fk_sim_p1 FOREIGN KEY (product_id) REFERENCES product(id),
  CONSTRAINT fk_sim_p2 FOREIGN KEY (similar_id) REFERENCES product(id)
) ENGINE=InnoDB;

-- VIEW útil: agrega uma linha por produto com uma amostra de veículos
DROP VIEW IF EXISTS v_product_quicklook;
CREATE VIEW v_product_quicklook AS
SELECT
  p.id,
  p.reference,
  p.name,
  p.description,
  (SELECT GROUP_CONCAT(DISTINCT CONCAT(vm.brand_name,' ',vm.model_name) ORDER BY vm.brand_name, vm.model_name SEPARATOR ' | ')
   FROM product_application pa2
   JOIN vehicle_model vm ON vm.id = pa2.vehicle_id
   WHERE pa2.product_id = p.id
   LIMIT 1) AS vehicles_sample
FROM product p;
