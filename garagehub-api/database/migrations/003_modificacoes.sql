-- Modificações de cada carro: formam a linha do tempo do projeto.

CREATE TABLE modificacoes (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  veiculo_id INTEGER NOT NULL,
  nome VARCHAR(120) NOT NULL,
  categoria VARCHAR(60) NOT NULL,
  descricao TEXT NULL,
  data_instalacao DATE NULL,
  quilometragem INTEGER NULL,
  valor DECIMAL(12, 2) NULL,
  marca_peca VARCHAR(80) NULL,
  oficina VARCHAR(120) NULL,
  criado_em DATETIME NOT NULL,
  atualizado_em DATETIME NOT NULL,
  CONSTRAINT fk_modificacoes_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos (id) ON DELETE CASCADE
);

CREATE INDEX idx_modificacoes_veiculo ON modificacoes (veiculo_id, data_instalacao);
