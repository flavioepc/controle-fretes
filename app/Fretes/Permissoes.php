<?php
declare(strict_types=1);

namespace App\Fretes;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

/**
 * O que cada perfil pode fazer no sistema de fretes (painel em fretes/permissoes, só admin).
 *
 * O padrão de cada perfil fica no CATALOGO; a tabela permissoes_perfil guarda só o que o admin mudou
 * (sem linha = vale o padrão). Rotas usam o middleware 'pode:chave' (ou 'pode:a|b' = qualquer uma);
 * menu, botões e o Escopo consultam Permissoes::pode().
 *
 * A permissão libera a TELA/AÇÃO; quais vendedores cada um enxerga continua no Escopo (gestor só a
 * equipe, vendedor só ele). Por isso as ações que valem para todos os vendedores ficam travadas para
 * gestor e vendedor ('global'), e o admin nunca perde o próprio acesso ao painel.
 */
final class Permissoes
{
    public const PERFIS = [
        'admin' => 'Administrador', 'financeiro' => 'Financeiro', 'gestor' => 'Gestor',
        'operador' => 'Operador', 'vendedor' => 'Vendedor',
    ];
    /** Perfis que só enxergam parte dos vendedores (equipe / ele mesmo). */
    private const PERFIS_RESTRITOS = ['gestor', 'vendedor'];

    /**
     * chave => [grupo, rótulo, explicação, perfis com acesso por padrão, opções]
     * opções: 'global' = vale para todos os vendedores (travado para gestor e vendedor);
     *         'nunca' = perfis que não podem receber; 'sempre' = perfis que não podem perder.
     */
    public const CATALOGO = [
        'fretes.lancar' => ['Fretes', 'Lançar, editar e listar fretes', 'Menu Fretes; também os atalhos de correção no dashboard e no extrato.',
            ['admin', 'financeiro', 'operador'], ['global' => true]],
        'fretes.excluir' => ['Fretes', 'Excluir fretes', 'Botão de lixeira na lista de fretes.',
            ['admin'], ['global' => true]],
        'faturas.imprimir' => ['Fretes', 'Imprimir faturas', 'Relatório da fatura e menu "Imprimir faturas" (várias de uma vez).',
            ['admin', 'financeiro', 'operador'], ['global' => true]],

        'faturas.ver' => ['Faturas', 'Ver faturas', 'Menu Faturas, detalhe da fatura e resumo de faturas no dashboard.',
            ['admin', 'financeiro'], ['global' => true]],
        'faturas.conferir' => ['Faturas', 'Conferir, enviar para pagamento e marcar como paga', 'Precisa também de "Ver faturas".',
            ['admin', 'financeiro'], ['global' => true]],
        'faturas.corrigir' => ['Faturas', 'Corrigir fatura e conhecimento', 'Alterar dados de fatura enviada/paga, trocar nº e retirar fretes do conhecimento. Precisa também de "Ver faturas".',
            ['admin', 'financeiro'], ['global' => true]],

        'credito.solicitar' => ['Uso de crédito', 'Pedir uso de crédito', 'Gestor: para a equipe; vendedor: para ele mesmo; os demais: para qualquer vendedor. Inclui cancelar os próprios pedidos.',
            ['admin', 'gestor', 'operador', 'vendedor'], []],
        'credito.decidir' => ['Uso de crédito', 'Aprovar e recusar pedidos de crédito', 'Gestor: só os da equipe; os demais: todos.',
            ['admin', 'gestor'], ['nunca' => ['vendedor']]],

        'movimentos.pagamento' => ['Pagamentos e transferências', 'Registrar pagamento de saldo negativo', 'Menu "Pagamentos e transf.".',
            ['admin', 'financeiro'], ['global' => true]],
        'movimentos.transferencia' => ['Pagamentos e transferências', 'Transferir crédito entre vendedores', 'Menu "Pagamentos e transf.".',
            ['admin', 'financeiro', 'operador'], ['global' => true]],
        'movimentos.estorno' => ['Pagamentos e transferências', 'Estornar pagamento ou transferência', 'Botão "Estornar" em "Últimos lançamentos".',
            ['admin', 'financeiro'], ['global' => true]],

        'expirados.ver' => ['Créditos expirados', 'Ver créditos expirados', 'Gestor: só a equipe; vendedor: só ele.',
            ['admin', 'financeiro', 'gestor'], []],
        'expirados.acoes' => ['Créditos expirados', 'Resgatar crédito e processar a expiração do ano', 'Precisa também de "Ver créditos expirados".',
            ['admin', 'financeiro'], ['global' => true]],

        'cadastros' => ['Administração', 'Cadastros', 'Empresas, transportadoras, gestores e vendedores.',
            ['admin'], ['global' => true]],
        'configuracoes' => ['Administração', 'Configurações', 'Expiração de crédito, valor mínimo do pedido isento etc.',
            ['admin'], ['global' => true]],
        'permissoes' => ['Administração', 'Permissões', 'Este painel.',
            ['admin'], ['global' => true, 'sempre' => ['admin']]],
    ];

    /** @var array<string, array<string, bool>>|null alterações gravadas, lidas uma vez por requisição */
    private static ?array $gravadas = null;

    /** O usuário logado pode? Sem perfil no sistema de fretes = não. */
    public static function pode(string $chave): bool
    {
        $perfil = Auth::perfil('fretes');
        return $perfil !== null && self::perfilPode($perfil, $chave);
    }

    /** Qualquer uma das chaves (ex.: a tela de movimentos abre com pagamento OU transferência OU estorno). */
    public static function podeAlguma(array $chaves): bool
    {
        foreach ($chaves as $c) {
            if (self::pode($c)) {
                return true;
            }
        }
        return false;
    }

    /** Middleware/controller: barra com 403, igual ao Auth::exigirSistema. 'a|b' = qualquer uma. */
    public static function exigir(string $chaves): void
    {
        Auth::exigirLogin();
        if (!self::podeAlguma(explode('|', $chaves))) {
            http_response_code(403);
            View::render('erros/mensagem', [
                'titulo' => 'Acesso não permitido',
                'mensagem' => 'Seu usuário não tem permissão para esta área. Fale com o administrador.',
            ]);
            exit;
        }
    }

    public static function perfilPode(string $perfil, string $chave): bool
    {
        $def = self::CATALOGO[$chave] ?? null;
        if (!$def || !isset(self::PERFIS[$perfil])) {
            return false;
        }
        $trava = self::trava($perfil, $chave);
        if ($trava !== null) {
            return $trava;
        }
        self::$gravadas ??= self::lerGravadas();
        return self::$gravadas[$perfil][$chave] ?? in_array($perfil, $def[3], true);
    }

    /** true/false = a célula é fixa (não muda no painel); null = o admin pode ligar e desligar. */
    public static function trava(string $perfil, string $chave): ?bool
    {
        $op = self::CATALOGO[$chave][4] ?? [];
        if (in_array($perfil, $op['sempre'] ?? [], true)) {
            return true;
        }
        if (in_array($perfil, $op['nunca'] ?? [], true)
            || (!empty($op['global']) && in_array($perfil, self::PERFIS_RESTRITOS, true))) {
            return false;
        }
        return null;
    }

    public static function padrao(string $perfil, string $chave): bool
    {
        return in_array($perfil, self::CATALOGO[$chave][3] ?? [], true);
    }

    /**
     * Grava o painel: $marcadas[perfil][chave] = true para cada caixa ligada. Só grava (e registra no
     * histórico) as células editáveis que mudaram. @return int quantas mudaram
     */
    public static function salvar(array $marcadas, int $usuarioId): int
    {
        $db = Database::get();
        $db->beginTransaction();
        try {
            self::$gravadas = self::lerGravadas(true);
            $mudou = 0;
            foreach (self::CATALOGO as $chave => $_) {
                foreach (self::PERFIS as $perfil => $__) {
                    if (self::trava($perfil, $chave) !== null) {
                        continue;
                    }
                    $novo = !empty($marcadas[$perfil][$chave]);
                    if ($novo === self::perfilPode($perfil, $chave)) {
                        continue;
                    }
                    $db->prepare('INSERT INTO permissoes_perfil (perfil, permissao, permitido, alterado_por) VALUES (?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE permitido = VALUES(permitido), alterado_por = VALUES(alterado_por)')
                       ->execute([$perfil, $chave, (int) $novo, $usuarioId]);
                    $db->prepare('INSERT INTO permissoes_historico (perfil, permissao, permitido, usuario_id) VALUES (?, ?, ?, ?)')
                       ->execute([$perfil, $chave, (int) $novo, $usuarioId]);
                    $mudou++;
                }
            }
            $db->commit();
            self::$gravadas = null;
            return $mudou;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Volta tudo ao padrão do sistema (apaga as alterações; o histórico fica). @return int células que voltaram */
    public static function restaurarPadrao(int $usuarioId): int
    {
        $db = Database::get();
        $db->beginTransaction();
        try {
            $mudou = 0;
            foreach (self::lerGravadas(true) as $perfil => $chaves) {
                foreach ($chaves as $chave => $valor) {
                    if (isset(self::CATALOGO[$chave]) && $valor !== self::padrao($perfil, $chave)
                        && self::trava($perfil, $chave) === null) {
                        $db->prepare('INSERT INTO permissoes_historico (perfil, permissao, permitido, usuario_id) VALUES (?, ?, ?, ?)')
                           ->execute([$perfil, $chave, (int) self::padrao($perfil, $chave), $usuarioId]);
                        $mudou++;
                    }
                }
            }
            $db->exec('DELETE FROM permissoes_perfil');
            $db->commit();
            self::$gravadas = null;
            return $mudou;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Últimas mudanças do painel, com o nome de quem mudou (portal). */
    public static function historico(int $limite = 50): array
    {
        $linhas = Database::todos(
            "SELECT perfil, permissao, permitido, usuario_id, alterado_em FROM permissoes_historico
              ORDER BY alterado_em DESC, id DESC LIMIT $limite");
        $ids = array_values(array_unique(array_filter(array_column($linhas, 'usuario_id'))));
        $nomes = [];
        if ($ids) {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $nomes = array_column(
                Database::todos("SELECT id, nome FROM usuarios WHERE id IN ($marcas)", $ids, 'portal'), 'nome', 'id');
        }
        foreach ($linhas as &$l) {
            $l['usuario'] = $nomes[$l['usuario_id']] ?? null;
        }
        return $linhas;
    }

    /** @return array<string, array<string, bool>> */
    private static function lerGravadas(bool $travar = false): array
    {
        $r = [];
        foreach (Database::todos('SELECT perfil, permissao, permitido FROM permissoes_perfil' . ($travar ? ' FOR UPDATE' : '')) as $l) {
            $r[$l['perfil']][$l['permissao']] = (bool) $l['permitido'];
        }
        return $r;
    }
}
