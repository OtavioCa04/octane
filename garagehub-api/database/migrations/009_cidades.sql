-- Municípios do IBGE, para a cidade do usuário ser estruturada (filtro "perto de mim").
-- Os dados entram com scripts/importar-cidades.php (database/seeds/cidades.json).
-- "busca" guarda o nome sem acento e em minúsculas, para a busca ignorar acentos.

CREATE TABLE cidades (
  id INTEGER PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  uf CHAR(2) NOT NULL,
  busca VARCHAR(120) NOT NULL
);

CREATE INDEX idx_cidades_uf ON cidades (uf);
CREATE INDEX idx_cidades_busca ON cidades (busca);

-- A cidade só aparece no nível do município: nunca bairro, rua ou coordenadas.
ALTER TABLE usuarios ADD COLUMN cidade_id INTEGER NULL;

CREATE INDEX idx_usuarios_cidade ON usuarios (cidade_id);
