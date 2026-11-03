<?php

declare(strict_types=1);

namespace GarageHub\Core;

/**
 * Aplica, em ordem, os arquivos .sql de database/migrations que ainda não rodaram.
 * O controle fica na tabela "migracoes".
 */
final class Migrador
{
    public function __construct(
        private readonly Db $db,
        private readonly string $pasta,
    ) {
    }

    /** @return list<string> nomes das migrations aplicadas agora */
    public function migrar(): array
    {
        $this->db->executar('CREATE TABLE IF NOT EXISTS migracoes (nome VARCHAR(190) PRIMARY KEY, aplicada_em DATETIME NOT NULL)');
        $feitas = array_column($this->db->todos('SELECT nome FROM migracoes'), 'nome');

        $arquivos = glob($this->pasta . '/*.sql') ?: [];
        sort($arquivos);

        $aplicadas = [];
        foreach ($arquivos as $arquivo) {
            $nome = basename($arquivo);
            if (in_array($nome, $feitas, true)) {
                continue;
            }
            foreach ($this->comandos((string) file_get_contents($arquivo)) as $sql) {
                $this->db->pdo()->exec($sql);
            }
            $this->db->executar('INSERT INTO migracoes (nome, aplicada_em) VALUES (?, ?)', [$nome, Db::agora()]);
            $aplicadas[] = $nome;
        }
        return $aplicadas;
    }

    /** Separa o arquivo em comandos e adapta o pouco que muda no SQLite. */
    private function comandos(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        if ($this->db->driver === 'sqlite') {
            $sql = str_replace('AUTO_INCREMENT', 'AUTOINCREMENT', $sql);
        }
        return array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql))));
    }
}
