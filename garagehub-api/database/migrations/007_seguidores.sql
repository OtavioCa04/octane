-- Quem segue quem, e quem segue quais carros. O feed é montado a partir destas tabelas.

CREATE TABLE seguidores_usuarios (
  seguidor_id INTEGER NOT NULL,
  seguido_id INTEGER NOT NULL,
  criado_em DATETIME NOT NULL,
  PRIMARY KEY (seguidor_id, seguido_id),
  CONSTRAINT fk_seg_usuarios_seguidor FOREIGN KEY (seguidor_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_seg_usuarios_seguido FOREIGN KEY (seguido_id) REFERENCES usuarios (id) ON DELETE CASCADE
);

CREATE INDEX idx_seg_usuarios_seguido ON seguidores_usuarios (seguido_id);

CREATE TABLE seguidores_veiculos (
  usuario_id INTEGER NOT NULL,
  veiculo_id INTEGER NOT NULL,
  criado_em DATETIME NOT NULL,
  PRIMARY KEY (usuario_id, veiculo_id),
  CONSTRAINT fk_seg_veiculos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_seg_veiculos_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos (id) ON DELETE CASCADE
);

CREATE INDEX idx_seg_veiculos_veiculo ON seguidores_veiculos (veiculo_id);
