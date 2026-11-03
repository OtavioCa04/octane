-- Pedidos de "esqueci minha senha". Como nos tokens de acesso, o banco guarda só o hash
-- do código enviado por e-mail. Cada pedido vale por pouco tempo e só pode ser usado uma vez.

CREATE TABLE redefinicoes_senha (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  usuario_id INTEGER NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  usado_em DATETIME NULL,
  criado_em DATETIME NOT NULL,
  CONSTRAINT uq_redefinicoes_hash UNIQUE (token_hash),
  CONSTRAINT fk_redefinicoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
);

CREATE INDEX idx_redefinicoes_usuario ON redefinicoes_senha (usuario_id, criado_em);
