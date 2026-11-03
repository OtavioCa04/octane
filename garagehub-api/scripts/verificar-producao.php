<?php

declare(strict_types=1);

/**
 * Confere a configuração antes de liberar o app: .env, banco, migrations, pastas e e-mail.
 *
 * Uso: php scripts/verificar-producao.php
 *      php scripts/verificar-producao.php voce@email.com   (também manda um e-mail de teste)
 */

use GarageHub\Core\Db;
use GarageHub\Core\Env;
use GarageHub\Correio\FabricaDeCorreio;
use GarageHub\Correio\Mensagem;

require dirname(__DIR__) . '/src/bootstrap.php';

$problemas = 0;
$item = function (bool $ok, string $texto, string $dica = '') use (&$problemas): void {
    echo ($ok ? '  ok   ' : '  ERRO ') . $texto . ($ok || $dica === '' ? '' : "\n         → {$dica}") . "\n";
    $problemas += $ok ? 0 : 1;
};

echo "Configuração\n";
$item(is_file(dirname(__DIR__) . '/.env'), '.env existe', 'Copie .env.example para .env e preencha.');
$item(Env::get('APP_ENV') === 'production', 'APP_ENV=production', 'Bloqueia o seed de demonstração.');
$item(!Env::bool('APP_DEBUG'), 'APP_DEBUG desligado', 'Com ele ligado, erros internos aparecem para os usuários.');
$appUrl = (string) Env::get('APP_URL');
$item(str_starts_with($appUrl, 'https://'), "APP_URL com https ({$appUrl})", 'É o endereço usado nos links dos e-mails.');
$origens = array_map('trim', explode(',', (string) Env::get('CORS_ORIGINS')));
$item(!array_filter($origens, fn ($o) => str_contains($o, 'localhost')), 'CORS_ORIGINS sem localhost', 'Deixe só o endereço do site.');
$item(version_compare(PHP_VERSION, '8.1', '>='), 'PHP ' . PHP_VERSION, 'Precisa de PHP 8.1 ou mais novo.');
foreach (['pdo_mysql', 'mbstring', 'openssl', 'curl', 'zlib'] as $ext) {
    $item(extension_loaded($ext), "extensão {$ext}", 'Ative no painel de PHP da hospedagem.');
}

echo "\nBanco\n";
try {
    $db = Db::doAmbiente();
    $db->valor('SELECT 1');
    $item(true, 'conexão com o ' . $db->driver);
    $feitas = array_column($db->todos('SELECT nome FROM migracoes'), 'nome');
    $arquivos = array_map('basename', glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: []);
    $faltando = array_diff($arquivos, $feitas);
    $item(!$faltando, 'migrations aplicadas', 'Rode: php scripts/migrar.php (faltam ' . implode(', ', $faltando) . ')');
    $item((int) $db->valor('SELECT COUNT(*) FROM cidades') > 5000, 'cidades do IBGE importadas', 'Rode: php scripts/importar-cidades.php');
    $item((int) $db->valor("SELECT COUNT(*) FROM usuarios WHERE email LIKE '%@garagehub.test'") === 0, 'sem contas de demonstração', 'Apague as contas @garagehub.test: a senha delas é pública.');
} catch (Throwable $e) {
    $item(false, 'conexão com o banco', $e->getMessage());
}

echo "\nPastas\n";
$uploads = rtrim((string) Env::get('UPLOADS_DIR', dirname(__DIR__) . '/public/uploads'), '/\\');
$item(is_dir($uploads) && is_writable($uploads), "uploads gravável ({$uploads})", 'Crie a pasta e dê permissão de escrita (755).');
$storage = dirname(__DIR__) . '/storage';
$item((is_dir($storage) || @mkdir($storage, 0750, true)) && is_writable($storage), 'storage gravável', 'Guarda cache da FIPE e backups.');

echo "\nE-mail\n";
$driver = Env::get('MAIL_DRIVER');
$item(in_array($driver, ['smtp', 'resend'], true), "MAIL_DRIVER=smtp ou resend ({$driver})", 'Sem isso, os e-mails de senha só são gravados em storage/mail.');
if ($driver === 'smtp') {
    $item((string) Env::get('MAIL_HOST') !== '' && (string) Env::get('MAIL_FROM_ADDRESS') !== '', 'MAIL_HOST e MAIL_FROM_ADDRESS preenchidos');
}
if ($driver === 'resend') {
    $item(str_starts_with((string) Env::get('RESEND_API_KEY'), 're_'), 'RESEND_API_KEY preenchida', 'A chave do Resend começa com re_.');
    $item((string) Env::get('MAIL_FROM_ADDRESS') !== '', 'MAIL_FROM_ADDRESS preenchido', 'Use um endereço do domínio confirmado no Resend.');
}
$para = $argv[1] ?? null;
if ($para) {
    try {
        FabricaDeCorreio::doAmbiente()->enviar(new Mensagem($para, 'Teste da Octane', 'Se você recebeu, o e-mail está funcionando.', '<p>Se você recebeu, o e-mail está funcionando.</p>'));
        $item(true, "e-mail de teste enviado para {$para} (confira a caixa de entrada e o spam)");
    } catch (Throwable $e) {
        $item(false, 'envio do e-mail de teste', $e->getMessage());
    }
}

echo $problemas ? "\n{$problemas} problema(s) para resolver.\n" : "\nTudo certo.\n";
exit($problemas ? 1 : 0);
