-- Fotos dos carros. O arquivo fica em disco (UPLOADS_DIR); o banco guarda só o nome e os metadados.
-- A foto com capa = 1 aparece no card e no topo da página do carro.

CREATE TABLE veiculo_fotos (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  veiculo_id INTEGER NOT NULL,
  arquivo VARCHAR(255) NOT NULL,
  largura INTEGER NOT NULL,
  altura INTEGER NOT NULL,
  tamanho INTEGER NOT NULL,
  capa TINYINT(1) NOT NULL DEFAULT 0,
  ordem INTEGER NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL,
  CONSTRAINT fk_veiculo_fotos_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos (id) ON DELETE CASCADE
);

CREATE INDEX idx_veiculo_fotos_veiculo ON veiculo_fotos (veiculo_id, capa, ordem);
