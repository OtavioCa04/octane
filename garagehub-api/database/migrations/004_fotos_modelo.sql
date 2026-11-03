-- Fotos padrão por modelo, usadas quando o dono ainda não enviou fotos do carro.
-- Preenchida pelo script scripts/buscar-fotos-modelos.php (database/seeds/fotos_modelo.sql).
-- Os campos de crédito são obrigatórios na exibição pública de fotos do Wikimedia.

CREATE TABLE fotos_modelo (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  marca VARCHAR(80) NOT NULL,
  modelo VARCHAR(120) NOT NULL,
  arquivo_url VARCHAR(500) NOT NULL,
  fonte VARCHAR(40) NOT NULL DEFAULT 'wikimedia',
  autor VARCHAR(255) NULL,
  licenca VARCHAR(60) NULL,
  licenca_url VARCHAR(255) NULL,
  pagina_origem VARCHAR(500) NULL,
  created_at DATETIME NULL,
  CONSTRAINT uq_fotos_modelo UNIQUE (marca, modelo)
);
