-- Curtidas e comentários em carros e modificações.
-- alvo_tipo = 'veiculo' ou 'modificacao'; alvo_id = id na tabela correspondente.
-- Ao excluir um carro ou uma modificação, a aplicação apaga as interações dele (InteracoesRepository::limpar).

CREATE TABLE curtidas (
  usuario_id INTEGER NOT NULL,
  alvo_tipo VARCHAR(20) NOT NULL,
  alvo_id INTEGER NOT NULL,
  criado_em DATETIME NOT NULL,
  PRIMARY KEY (usuario_id, alvo_tipo, alvo_id),
  CONSTRAINT fk_curtidas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
);

CREATE INDEX idx_curtidas_alvo ON curtidas (alvo_tipo, alvo_id);

CREATE TABLE comentarios (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  usuario_id INTEGER NOT NULL,
  alvo_tipo VARCHAR(20) NOT NULL,
  alvo_id INTEGER NOT NULL,
  texto TEXT NOT NULL,
  criado_em DATETIME NOT NULL,
  CONSTRAINT fk_comentarios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
);

CREATE INDEX idx_comentarios_alvo ON comentarios (alvo_tipo, alvo_id, criado_em);
CREATE INDEX idx_comentarios_usuario ON comentarios (usuario_id, criado_em);
