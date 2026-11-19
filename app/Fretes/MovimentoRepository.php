<?php
declare(strict_types=1);

namespace App\Fretes;

use App\Core\Database;
use PDOException;

/**
 * Pagamentos de saldo negativo e transferências de crédito entre vendedores.
 * A gravação fica nas procedures sp_registrar_pagamento, sp_transferir_credito e sp_estornar_movimento,
 * que travam o(s) vendedor(es) e conferem o saldo dentro da transação.
 */
final class MovimentoRepository
{
    /** Vendedores com saldo; inclui inativos com saldo negativo (quem saiu ainda pode pagar). */
    public function vendedores(): array
    {
        return Database::todos(
            'SELECT vendedor_id id, vendedor nome, gestor, ativo, saldo, disponivel
               FROM vw_saldo_vendedor WHERE ativo = 1 OR saldo < 0 ORDER BY gestor, vendedor');
    }

    /**
     * Últimos pagamentos e transferências (a transferência aparece uma vez, pelo lado de quem enviou),
     * com o estorno, se houver (estorno_id, estornado_em, estornado_por_nome, estorno_obs).
     */
    public function recentes(int $limite = 100): array
    {
        $linhas = Database::todos(
            "SELECT m.id, m.tipo, m.data_ref, m.valor, m.obs, m.criado_por, m.criado_em,
                    v.nome vendedor, vp.nome contraparte, pv.referencia_externa,
                    e.id estorno_id, e.criado_em estornado_em, e.criado_por estornado_por, e.obs estorno_obs
               FROM movimentos_credito m
               JOIN vendedores v ON v.id = m.vendedor_id
               LEFT JOIN movimentos_credito mp ON mp.id = m.transferencia_par_id
               LEFT JOIN vendedores vp ON vp.id = mp.vendedor_id
               LEFT JOIN pagamentos_vendedor pv ON pv.id = m.pagamento_id
               LEFT JOIN movimentos_credito e ON e.estorno_de_id = m.id
              WHERE m.tipo = 'pagamento_negativo' OR (m.tipo = 'transferencia' AND m.valor < 0)
              ORDER BY m.data_ref DESC, m.id DESC
              LIMIT $limite");

        $ids = array_values(array_unique(array_filter(array_merge(
            array_column($linhas, 'criado_por'), array_column($linhas, 'estornado_por')))));
        $nomePorId = [];
        if ($ids) {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $nomePorId = array_column(
                Database::todos("SELECT id, nome FROM usuarios WHERE id IN ($marcas)", $ids, 'portal'), 'nome', 'id');
        }
        foreach ($linhas as &$l) {
            $l['criado_por_nome'] = $nomePorId[$l['criado_por']] ?? null;
            $l['estornado_por_nome'] = $nomePorId[$l['estornado_por']] ?? null;
        }
        return $linhas;
    }

    /** Pagamento ou transferência que ainda pode ser estornado (null se não existe, é de outro tipo ou já foi). */
    public function paraEstornar(int $movimentoId): ?array
    {
        return Database::um(
            "SELECT m.id, m.tipo, m.valor, v.nome vendedor
               FROM movimentos_credito m
               JOIN vendedores v ON v.id = m.vendedor_id
              WHERE m.id = ? AND m.tipo IN ('pagamento_negativo', 'transferencia')
                AND NOT EXISTS (SELECT 1 FROM movimentos_credito e
                                 WHERE e.estorno_de_id IN (m.id, COALESCE(m.transferencia_par_id, 0)))",
            [$movimentoId]);
    }

    /** Devolve null se deu certo ou a mensagem da regra violada. */
    public function estornar(int $movimentoId, int $usuarioId, string $motivo): ?string
    {
        return $this->chamar('CALL sp_estornar_movimento(?, ?, ?)', [$movimentoId, $usuarioId, $motivo]);
    }

    public function saldo(int $vendedorId): ?array
    {
        return Database::um('SELECT * FROM vw_saldo_vendedor WHERE vendedor_id = ?', [$vendedorId]);
    }

    /** Devolve null se deu certo ou a mensagem da regra violada. */
    public function registrarPagamento(int $vendedorId, float $valor, string $data, ?string $referencia,
                                       int $usuarioId, ?string $obs): ?string
    {
        return $this->chamar('CALL sp_registrar_pagamento(?, ?, ?, ?, ?, ?)',
            [$vendedorId, $valor, $data, $referencia, $usuarioId, $obs]);
    }

    /** Devolve null se deu certo ou a mensagem da regra violada. */
    public function transferir(int $origem, int $destino, float $valor, string $data, int $usuarioId, ?string $obs): ?string
    {
        return $this->chamar('CALL sp_transferir_credito(?, ?, ?, ?, ?, ?)',
            [$origem, $destino, $valor, $data, $usuarioId, $obs]);
    }

    private function chamar(string $sql, array $params): ?string
    {
        try {
            $st = Database::get()->prepare($sql);
            $st->execute($params);
            $st->closeCursor();
            return null;
        } catch (PDOException $e) {
            // SIGNAL '45000' da procedure = regra de negócio (ex.: saldo insuficiente)
            if (($e->errorInfo[0] ?? '') === '45000') {
                return (string) ($e->errorInfo[2] ?? 'Não foi possível concluir.');
            }
            throw $e;
        }
    }
}
