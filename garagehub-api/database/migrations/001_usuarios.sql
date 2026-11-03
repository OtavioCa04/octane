-- Usuários, tokens de acesso e tentativas de login (para limitar força bruta).
-- Datas em UTC, gravadas pela aplicação. Escrito para MySQL 8; o migrador adapta
-- o AUTO_INCREMENT para o SQLite usado nos testes.

CREATE TABLE usuarios (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  username VARCHAR(30) NOT NULL,
  email VARCHAR(180) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  cidade VARCHAR(120) NULL,
  bio VARCHAR(500) NULL,
  perfil_privado TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL,
  atualizado_em DATETIME NOT NULL,
  CONSTRAINT uq_usuarios_username UNIQUE (username),
  CONSTRAINT uq_usuarios_email UNIQUE (email)
);

CREATE TABLE tokens (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  usuario_id INTEGER NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  ultimo_uso_em DATETIME NULL,
  criado_em DATETIME NOT NULL,
  CONSTRAINT uq_tokens_hash UNIQUE (token_hash),
  CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
);

CREATE INDEX idx_tokens_usuario ON tokens (usuario_id);

CREATE TABLE login_tentativas (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  chave CHAR(64) NOT NULL,
  criado_em DATETIME NOT NULL
);

CREATE INDEX idx_login_tentativas_chave ON login_tentativas (chave, criado_em);
