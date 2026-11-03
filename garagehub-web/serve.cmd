@echo off
rem Sobe o servidor de desenvolvimento com o Node 22, sem trocar o Node padrão da máquina.
cd /d "%~dp0"
"%LOCALAPPDATA%\Author Software\nvm\installs\v22.22.3\node.exe" node_modules\@angular\cli\bin\ng.js serve --port 4200
