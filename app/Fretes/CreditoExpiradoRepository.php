<?php
declare(strict_types=1);

namespace App\Fretes;

use App\Core\Database;
use PDOException;

/**
 * Créditos expirados na virada do ano (tabela `creditos_expirados`).
 * sp_expirar_creditos roda normalmente via cron (ver README); aqui também dá pra disparar
 * na mão (idempotente: o mesmo ano nunca expira duas vezes, graças à UNIQUE KEY da tabela).
 * sp_resgatar_credito devolve, total ou parcialmente, um crédito já expirado.
 */
final class CreditoExpiradoRepository
{
    public function __construct(private Escopo $escopo)
    {
    }

    public function listar(string $filtroStatus): array
    {
        [$w, $p] = $this->escopo->filtroVendedor('ce.vendedor_id');
        $sqlStatus = '';
        if ($filtroStatus === 'pendentes') {
            $sqlStatus = " AND ce.status IN ('expirado','resgatado_parcial')";
        } elseif (in_array($filtroStatus, ['expirado', 'resgatado_parcial', 'resgatado'], true)) {
            $sqlStatus = ' AND ce.status = ?';
            $p[] = $filtroStatus;
        }
        return Database::todos(
            "SELECT ce.*, v.nome vendedor, g.nome gestor, (ce.valor - ce.valor_resgatado) disponivel_resgate
               FROM creditos_expirados ce
               JOIN vendedores v ON v.id = ce.vendedor_id
               JOIN gestores g ON g.id = v.gestor_id
              WHERE $w $sqlStatus
              ORDER BY ce.ano_referencia DESC, ce.expirado_em DESC", $p);
    }

    public function buscar(int $id): ?array
    {
        return Database::um(
            "SELECT ce.*, v.nome vendedor, v.gestor_id, (ce.valor - ce.valor_resgatado) disponivel_resgate
               FROM creditos_expirados ce JOIN vendedores v ON v.id = ce.vendedor_id WHERE ce.id = ?", [$id]);
    }

    public function resumo(): array
    {
        [$w, $p] = $this->escopo->filtroVendedor('ce.vendedor_id');
        return Database::um(
            "SELECT COUNT(*) qtd, COALESCE(SUM(ce.valor), 0) total,
                    COALESCE(SUM(ce.valor - ce.valor_resgatado), 0) disponivel
               FROM creditos_expirados ce WHERE $w", $p) ?? [];
    }

    /** @return ?string mensagem de erro de negócio (ou null se deu certo) */
    public function resgatar(int $id, float $valor, int $usuarioId, ?string $motivo): ?string
    {
        try {
            $st = Database::get()->prepare('CALL sp_resgatar_credito(?, ?, ?, ?)');
            $st->execute([$id, $valor, $usuarioId, $motivo]);
            $st->closeCursor();
            return null;
        } catch (PDOException $e) {
            if (($e->errorInfo[0] ?? '') === '45000') {
                return (string) ($e->errorInfo[2] ?? 'Não foi possível concluir.');
            }
            throw $e;
        }
    }

    /** Dispara a expiração de um ano (normalmente roda sozinha via cron, ver README). */
    public function expirarAno(int $ano, int $usuarioId): array
    {
        $st = Database::get()->prepare('CALL sp_expirar_creditos(?, ?)');
        $st->execute([$ano, $usuarioId]);
        $resultado = $st->fetch() ?: ['vendedores_expirados' => 0, 'total_expirado' => 0];
        $st->closeCursor();
        return $resultado;
    }

    public function chaveGeralAtiva(): bool
    {
        $r = Database::um("SELECT valor FROM configuracoes WHERE chave = 'credito_expira_virada_ano'");
        return (bool) (int) ($r['valor'] ?? '0');
    }

    /** @return int[] anos que já têm pelo menos um registro expirado */
    public function anosJaProcessados(): array
    {
        return array_map('intval', array_column(Database::todos(
            'SELECT DISTINCT ano_referencia FROM creditos_expirados ORDER BY ano_referencia DESC'), 'ano_referencia'));
    }
}
