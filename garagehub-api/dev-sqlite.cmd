@echo off
rem Sobe a API em http://localhost:8000 com um banco SQLite local (dev.sqlite), sem precisar do MySQL.
rem Recria a garagem de demonstracao a cada execucao. So para desenvolvimento.
rem O -d extension=pdo_sqlite ativa o SQLite so nestes comandos, sem mudar o php.ini.
cd /d "%~dp0"
set DB_DRIVER=sqlite
set DB_DATABASE=%~dp0dev.sqlite
php -d extension=pdo_sqlite scripts\migrar.php || exit /b 1
php -d extension=pdo_sqlite scripts\importar-cidades.php || exit /b 1
php -d extension=pdo_sqlite scripts\seed-dev.php || exit /b 1
rem Limites de upload maiores que o padrao do PHP (2 MB), para as fotos
php -d extension=pdo_sqlite -d upload_max_filesize=10M -d post_max_size=12M -S localhost:8000 -t public public\index.php
