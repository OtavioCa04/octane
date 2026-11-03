<?php

declare(strict_types=1);

/**
 * Backup do banco MySQL em SQL compactado (storage/backups/octane-AAAAMMDD-HHMM.sql.gz), sem precisar
 * do mysqldump. Apaga os backups mais velhos que BACKUP_DIAS (padrão 14).
 * Feito para rodar todo dia pelo agendador (cron) da hospedagem:
 *
 *   php /caminho/octane-api/scripts/backup.php
 *
 * Para restaurar: descompacte e importe o .sql pelo phpMyAdmin ou com "mysql banco < arquivo.sql".
 * As fotos enviadas (pasta uploads) não entram aqui; use o backup de arquivos da hospedagem.
 */

use GarageHub\Core\Db;
use GarageHub\Core\Env;

require dirname(__DIR__) . '/src/bootstrap.php';

$db = Db::doAmbiente();
if ($db->driver !== 'mysql') {
    fwrite(STDERR, "O backup é só para MySQL. No SQLite, basta copiar o arquivo do banco.\n");
    exit(1);
}

$pasta = dirname(__DIR__) . '/storage/backups';
if (!is_dir($pasta) && !mkdir($pasta, 0750, true)) {
    fwrite(STDERR, "Não consegui criar {$pasta}.\n");
    exit(1);
}

$arquivo = $pasta . '/octane-' . gmdate('Ymd-Hi') . '.sql.gz';
$gz = gzopen($arquivo . '.parcial', 'wb6');
$pdo = $db->pdo();
// A constante mudou de nome no PHP 8.4; as hospedagens ainda usam versões mais antigas também
$semBuffer = defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY') ? constant('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY') : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;

gzwrite($gz, "-- Backup da Octane, " . gmdate('Y-m-d H:i:s') . " UTC\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM) as [$tabela]) {
    $criar = $pdo->query("SHOW CREATE TABLE `{$tabela}`")->fetch(PDO::FETCH_NUM)[1];
    gzwrite($gz, "DROP TABLE IF EXISTS `{$tabela}`;\n{$criar};\n\n");

    // Lê sem carregar a tabela inteira na memória
    $pdo->setAttribute($semBuffer, false);
    $linhas = $pdo->query("SELECT * FROM `{$tabela}`");
    $lote = [];
    while ($linha = $linhas->fetch(PDO::FETCH_NUM)) {
        $lote[] = '(' . implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $linha)) . ')';
        if (count($lote) === 200) {
            gzwrite($gz, "INSERT INTO `{$tabela}` VALUES\n" . implode(",\n", $lote) . ";\n");
            $lote = [];
        }
    }
    if ($lote) {
        gzwrite($gz, "INSERT INTO `{$tabela}` VALUES\n" . implode(",\n", $lote) . ";\n");
    }
    $linhas->closeCursor();
    $pdo->setAttribute($semBuffer, true);
    gzwrite($gz, "\n");
}

gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
gzclose($gz);
rename($arquivo . '.parcial', $arquivo);

// Só apaga os antigos depois que o novo ficou pronto
$dias = max(1, (int) Env::get('BACKUP_DIAS', '14'));
foreach (glob($pasta . '/octane-*.sql.gz') ?: [] as $antigo) {
    if (filemtime($antigo) < time() - $dias * 86400) {
        unlink($antigo);
    }
}

printf("Backup salvo em %s (%.1f KB).\n", $arquivo, filesize($arquivo) / 1024);
