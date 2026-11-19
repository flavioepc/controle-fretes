<?php
declare(strict_types=1);

namespace App\Fretes;

use App\Core\Database;

/**
 * Extrato: fretes e movimentos de crédito numa linha do tempo, de um vendedor por vez.
 * O saldo mostrado em cada linha é o ACUMULADO até aquela data, considerando todo o
 * histórico — por isso o "saldo anterior" ao período é calculado à parte.
 */
final class ExtratoRepository
{
    public function __construct(private Escopo $escopo)
    {
    }

    /** Vendedores que o usuário pode consultar (para o seletor da tela). */
    public function vendedoresVisiveis(): array
    {
        [$w, $p] = $this->escopo->filtroVendedor('v.id');
        return Database::todos(
            "SELECT v.id, v.nome, g.nome gestor FROM vendedores v JOIN gestores g ON g.id = v.gestor_id
              WHERE $w AND v.ativo = 1 ORDER BY g.nome, v.nome", $p);
    }

    public function podeVer(int $vendedorId): bool
    {
        [$w, $p] = $this->escopo->filtroVendedor('id');
        return (bool) Database::um("SELECT 1 FROM vendedores WHERE id = ? AND $w",
            array_merge([$vendedorId], $p));
    }

    public function saldo(int $vendedorId): ?array
    {
        return Database::um('SELECT * FROM vw_saldo_vendedor WHERE vendedor_id = ?', [$vendedorId]);
    }

    /** @return array{linhas: array, saldo_anterior: float} */
    public function extrato(int $vendedorId, string $de, string $ate): array
    {
        $anterior = Database::um(
            'SELECT COALESCE(SUM(valor), 0) v FROM vw_extrato WHERE vendedor_id = ? AND data_ref < ?',
            [$vendedorId, $de]);
        $saldoAnterior = (float) $anterior['v'];

        // saldo_apos soma do saldo anterior ao período + acumulado até a linha (ordem cronológica);
        // a listagem em si vem do mais recente para o mais antigo (ORDER BY externo)
        $linhas = Database::todos(
            "SELECT data_ref, origem, origem_id, descricao, valor,
                    ? + SUM(valor) OVER (ORDER BY data_ref, ord, origem_id ROWS UNBOUNDED PRECEDING) AS saldo_apos
               FROM (
                 SELECT data_ref, origem, origem_id, descricao, valor,
                        CASE WHEN origem = 'frete' THEN 0 ELSE 1 END AS ord
                   FROM vw_extrato WHERE vendedor_id = ? AND data_ref BETWEEN ? AND ?
               ) e
              ORDER BY data_ref DESC, ord DESC, origem_id DESC",
            [$saldoAnterior, $vendedorId, $de, $ate]);

        return ['linhas' => $linhas, 'saldo_anterior' => $saldoAnterior];
    }
}
