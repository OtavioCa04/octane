-- Identificadores do catálogo FIPE, para consultar o valor de novo no futuro (histórico de valores).

ALTER TABLE veiculos ADD COLUMN fipe_marca_id VARCHAR(10) NULL;
ALTER TABLE veiculos ADD COLUMN fipe_modelo_id VARCHAR(10) NULL;
ALTER TABLE veiculos ADD COLUMN fipe_ano_id VARCHAR(12) NULL;
