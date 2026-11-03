-- Foto de perfil: caminho relativo dentro de UPLOADS_DIR (avatares/<id>/<nome>.jpg), ou NULL para as iniciais.

ALTER TABLE usuarios ADD COLUMN avatar VARCHAR(80) NULL;
