<?php

declare(strict_types=1);

/**
 * Dados de exemplo para desenvolvimento: garagens de demonstração, atividade em datas
 * diferentes (para o feed ter conteúdo) e as fotos padrão dos modelos.
 * Pode rodar várias vezes: recria as contas de demonstração do zero.
 *
 * Uso: php scripts/seed-dev.php
 *
 * Contas de demonstração (só para desenvolvimento, nunca em produção), todas com a senha garagehub-demo:
 *   demo@garagehub.test        @otavio         (a conta principal; segue @lari_jdm)
 *   lari@garagehub.test        @lari_jdm
 *   joao@garagehub.test        @opala_do_joao
 */

use GarageHub\Core\Db;
use GarageHub\Core\Env;
use GarageHub\Garagem\FotoStorage;
use GarageHub\Local\CidadeRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

if (Env::get('APP_ENV', 'local') === 'production') {
    fwrite(STDERR, "Seed de desenvolvimento bloqueado em produção.\n");
    exit(1);
}

$db = Db::doAmbiente();
$agora = Db::agora();
$senha = password_hash('garagehub-demo', PASSWORD_DEFAULT);

// "Há N horas", para espalhar a atividade no tempo
$ha = fn (float $horas) => gmdate('Y-m-d H:i:s', (int) (time() - $horas * 3600));

// Fotos padrão dos modelos (o mesmo script que roda em produção)
ob_start();
require __DIR__ . '/importar-fotos-modelos.php';
ob_end_clean();
$fotos = $db->todos('SELECT id FROM fotos_modelo');

// Apaga as contas de demonstração antigas. O ON DELETE CASCADE leva carros, modificações, fotos,
// seguidores e tokens; os arquivos das fotos enviadas, apagamos aqui.
$storage = FotoStorage::doAmbiente();
$antigas = $db->todos(
    "SELECT f.arquivo FROM veiculo_fotos f JOIN veiculos v ON v.id = f.veiculo_id
     JOIN usuarios u ON u.id = v.usuario_id WHERE u.email LIKE '%@garagehub.test'",
);
foreach ($antigas as $f) {
    $storage->excluir($f['arquivo']);
}
foreach ($db->todos("SELECT avatar FROM usuarios WHERE email LIKE '%@garagehub.test' AND avatar IS NOT NULL") as $u) {
    $storage->excluir($u['avatar']);
}
$db->executar("DELETE FROM usuarios WHERE email LIKE '%@garagehub.test'");

$garagens = [
    [
        'usuario' => [
            'nome' => 'Otávio Corrêa', 'username' => 'otavio', 'email' => 'demo@garagehub.test',
            'cidade' => 'Campinas, SP', 'bio' => 'VW de rua, um projeto em andamento e um clássico na garagem.',
        ],
        'carros' => [
            [
                'dados' => [
                    'slug' => 'golf-gti', 'marca' => 'Volkswagen', 'modelo' => 'Golf GTI', 'versao' => '2.0 TSI · MK7',
                    'ano_fabricacao' => 2015, 'ano_modelo' => 2016, 'motor' => '2.0 TSI, 220 cv', 'cambio' => 'DSG, 6 marchas',
                    'combustivel' => 'Gasolina', 'cor' => 'Branco Puro', 'cor_hex' => '#E9E9E4', 'carroceria' => 'hatch',
                    'quilometragem' => 98400, 'status' => 'projeto', 'placa' => 'ABC1D23', 'preco_compra' => 95000,
                    'descricao' => 'Comprado original em 2022. O plano é um carro de rua rápido e confiável, sem perder o conforto do dia a dia.',
                    'valor_fipe' => 104300, 'data_consulta_fipe' => '2026-10-01',
                ],
                'criado' => 24 * 90,
                'mods' => [
                    ['Remap stage 2', 'Motor', '2026-03-20', 98000, 3200, null, 'Oficina exemplo', 24 * 30],
                    ['Downpipe 3"', 'Escape', '2026-03-10', 97800, 2800, 'Marca exemplo', null, 24 * 35],
                    ['Rodas 18" e pneus novos', 'Rodas e pneus', '2026-01-18', 95100, 7400, null, null, 24 * 60],
                    ['Coilover', 'Suspensão', '2025-11-05', 92400, 6500, null, null, 24 * 80],
                ],
            ],
            [
                'dados' => [
                    'slug' => 'civic-si', 'marca' => 'Honda', 'modelo' => 'Civic Si', 'versao' => '2.4 · Coupé',
                    'ano_fabricacao' => 2014, 'ano_modelo' => 2014, 'motor' => '2.4 i-VTEC, 206 cv', 'cambio' => 'Manual, 6 marchas',
                    'combustivel' => 'Gasolina', 'cor' => 'Vermelho', 'cor_hex' => '#B3262E', 'carroceria' => 'cupe',
                    'quilometragem' => 121000, 'status' => 'garagem', 'valor_fipe' => 78900, 'data_consulta_fipe' => '2026-10-01',
                ],
                'criado' => 24 * 85,
                'mods' => [
                    ['Intake de admissão', 'Admissão', '2025-08-12', 118500, 1200, null, null, 24 * 70],
                    ['Película 35%', 'Estética', '2025-06-02', 116000, 450, null, null, 24 * 75],
                ],
            ],
            [
                'dados' => [
                    'slug' => 'fusca', 'marca' => 'Volkswagen', 'modelo' => 'Fusca', 'versao' => '1300 · Original',
                    'ano_fabricacao' => 1974, 'ano_modelo' => 1974, 'motor' => '1.3 boxer a ar', 'cambio' => 'Manual, 4 marchas',
                    'combustivel' => 'Gasolina', 'cor' => 'Azul Atlântico', 'cor_hex' => '#2F5D8C', 'carroceria' => 'classico',
                    'quilometragem' => 64200, 'status' => 'garagem', 'valor_fipe' => 38500, 'data_consulta_fipe' => '2026-10-01',
                ],
                'criado' => 24 * 84,
                'mods' => [
                    ['Restauro de pintura', 'Funilaria', '2024-05-10', 63800, 9000, null, null, 24 * 83],
                ],
            ],
            [
                'dados' => [
                    'slug' => 'uno-mille', 'marca' => 'Fiat', 'modelo' => 'Uno Mille', 'versao' => '1.0 Fire · Way',
                    'ano_fabricacao' => 2010, 'ano_modelo' => 2011, 'motor' => '1.0 Fire, 66 cv', 'cambio' => 'Manual, 5 marchas',
                    'combustivel' => 'Flex', 'cor' => 'Prata', 'cor_hex' => '#9EA4AA', 'carroceria' => 'hatch',
                    'quilometragem' => 185000, 'status' => 'vendido',
                ],
                'criado' => 24 * 83,
                'mods' => [],
            ],
        ],
    ],
    [
        'usuario' => [
            'nome' => 'Larissa Moura', 'username' => 'lari_jdm', 'email' => 'lari@garagehub.test',
            'cidade' => 'São Paulo, SP', 'bio' => 'JDM desde sempre. Supra em construção, RX-7 para o fim de semana.',
        ],
        'carros' => [
            [
                'dados' => [
                    'slug' => 'supra-mk4', 'marca' => 'Toyota', 'modelo' => 'Supra MK4', 'versao' => '3.0 Turbo · A80',
                    'ano_fabricacao' => 1996, 'ano_modelo' => 1996, 'motor' => '2JZ-GTE', 'cambio' => 'Manual, 6 marchas',
                    'combustivel' => 'Gasolina', 'cor' => 'Laranja', 'cor_hex' => '#E8692A', 'carroceria' => 'cupe',
                    'quilometragem' => 143000, 'status' => 'projeto',
                    'descricao' => 'Importado em 2021. Projeto de rua com cara de pista, um passo de cada vez.',
                ],
                'criado' => 24 * 20,
                'mods' => [
                    ['Turbo single e intercooler', 'Motor', '2026-10-28', 142800, null, null, 'Oficina exemplo', 6],
                    ['Rodas 18" e pneus semi-slick', 'Rodas e pneus', '2026-10-12', 142100, null, null, null, 24 * 9],
                    ['Freios de 4 pistões', 'Freios', '2026-09-30', 141500, null, null, null, 24 * 15],
                ],
            ],
            [
                'dados' => [
                    'slug' => 'rx-7', 'marca' => 'Mazda', 'modelo' => 'RX-7', 'versao' => 'FD3S',
                    'ano_fabricacao' => 1993, 'ano_modelo' => 1993, 'motor' => '13B-REW', 'cambio' => 'Manual, 5 marchas',
                    'combustivel' => 'Gasolina', 'cor' => 'Prata', 'cor_hex' => '#D8D2C4', 'carroceria' => 'cupe',
                    'quilometragem' => 98000, 'status' => 'garagem',
                ],
                'criado' => 30,
                'mods' => [],
            ],
        ],
    ],
    [
        'usuario' => [
            'nome' => 'João Batista', 'username' => 'opala_do_joao', 'email' => 'joao@garagehub.test',
            'cidade' => 'Curitiba, PR', 'bio' => 'Opaleiro raiz. Restauro carro antigo nas horas vagas.',
        ],
        'carros' => [
            [
                'dados' => [
                    'slug' => 'opala', 'marca' => 'Chevrolet', 'modelo' => 'Opala', 'versao' => 'SS 4.1',
                    'ano_fabricacao' => 1979, 'ano_modelo' => 1979, 'motor' => '4.1, 6 cilindros', 'cambio' => 'Manual, 4 marchas',
                    'combustivel' => 'Gasolina', 'cor' => 'Vermelho', 'cor_hex' => '#B3262E', 'carroceria' => 'sedan',
                    'quilometragem' => 87000, 'status' => 'garagem',
                ],
                'criado' => 24 * 40,
                'mods' => [
                    ['Restauro do painel original', 'Interior', '2026-10-20', 86900, null, null, null, 24 * 4],
                ],
            ],
            [
                'dados' => [
                    'slug' => 'charger-1970', 'marca' => 'Dodge', 'modelo' => 'Charger 1970', 'versao' => 'R/T 440',
                    'ano_fabricacao' => 1970, 'ano_modelo' => 1970, 'motor' => '440 Magnum V8', 'cambio' => 'Automático, 3 marchas',
                    'combustivel' => 'Gasolina', 'cor' => 'Preto', 'cor_hex' => '#26282B', 'carroceria' => 'classico',
                    'quilometragem' => 52000, 'status' => 'projeto',
                ],
                'criado' => 24 * 2,
                'mods' => [],
            ],
        ],
    ],
];

$cidades = new CidadeRepository($db);
$ids = [];
foreach ($garagens as $g) {
    $u = $g['usuario'];
    // "Campinas, SP" → cidade estruturada do IBGE (se a lista de cidades já foi importada)
    [$nomeCidade, $uf] = array_map('trim', explode(',', $u['cidade']));
    $cidade = $cidades->porNomeUf($nomeCidade, $uf);
    $usuarioId = $db->inserir('usuarios', $u + [
        'cidade_id' => $cidade['id'] ?? null,
        'senha_hash' => $senha,
        'perfil_privado' => 0,
        'criado_em' => $ha(24 * 120),
        'atualizado_em' => $agora,
    ]);
    $ids[$u['username']] = $usuarioId;

    foreach ($g['carros'] as $ordem => $carro) {
        $veiculoId = $db->inserir('veiculos', $carro['dados'] + [
            'usuario_id' => $usuarioId,
            'ordem' => $ordem,
            'visivel' => 1,
            'criado_em' => $ha($carro['criado']),
            'atualizado_em' => $ha(min([$carro['criado'], ...array_map(fn ($m) => $m[7], $carro['mods'])])),
        ]);
        foreach ($carro['mods'] as [$nome, $categoria, $data, $km, $valor, $marcaPeca, $oficina, $horas]) {
            $db->inserir('modificacoes', [
                'veiculo_id' => $veiculoId,
                'nome' => $nome,
                'categoria' => $categoria,
                'data_instalacao' => $data,
                'quilometragem' => $km,
                'valor' => $valor,
                'marca_peca' => $marcaPeca,
                'oficina' => $oficina,
                'criado_em' => $ha($horas),
                'atualizado_em' => $ha($horas),
            ]);
        }
    }
}

// A conta principal já segue a Larissa: o feed "Seguindo" começa com conteúdo,
// e o João aparece nas sugestões e no "Explorar".
$db->executar(
    'INSERT INTO seguidores_usuarios (seguidor_id, seguido_id, criado_em) VALUES (?, ?, ?)',
    [$ids['otavio'], $ids['lari_jdm'], $agora],
);

echo count($fotos) . ' fotos padrão e ' . count($garagens) . " garagens de demonstração criadas (@otavio, @lari_jdm, @opala_do_joao).\n";
echo "Login principal: demo@garagehub.test (a senha está no topo deste arquivo).\n";
