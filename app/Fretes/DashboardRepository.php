<?php
declare(strict_types=1);

namespace App\Fretes;

use App\Core\Database;

/**
 * Consultas do dashboard. Todas respeitam o Escopo do usuário.
 * Saldo é sempre o ACUMULADO (não depende do período);
 * os números "no período" usam a data do frete.
 */
final class DashboardRepository
{
    public function __construct(private Escopo $escopo, private Filtros $f)
    {
    }

    /** Cards de saldo (posição atual). */
    public function resumoSaldos(): array
    {
        [$w, $p] = $this->escopo->filtroVendedor('s.vendedor_id', $this->f->gestorId);
        return Database::um(
            "SELECT COALESCE(SUM(CASE WHEN s.saldo > 0 THEN s.saldo END), 0) AS credito,
                    COALESCE(SUM(CASE WHEN s.saldo < 0 THEN s.saldo END), 0) AS negativo,
                    COALESCE(SUM(s.saldo), 0)        AS liquido,
                    COALESCE(SUM(s.reservado), 0)    AS reservado,
                    COALESCE(SUM(s.disponivel > 0), 0) AS vendedores_com_credito,
                    COALESCE(SUM(s.saldo < 0), 0)    AS vendedores_negativos,
                    COALESCE(SUM(s.fretes_aguardando_fatura), 0) AS aguardando_fatura
               FROM vw_saldo_vendedor s WHERE $w", $p) ?? [];
    }

    /** Totais dos fretes dentro do período e filtros. */
    public function resumoPeriodo(): array
    {
        [$sql, $p] = $this->ondeFretes();
        return Database::um(
            "SELECT COUNT(*) AS fretes,
                    COALESCE(SUM(f.valor_cliente), 0) AS cobrado_cliente,
                    COALESCE(SUM(f.valor_transp), 0)  AS pago_transp,
                    COALESCE(SUM(f.diferenca), 0)     AS credito_gerado,
                    COALESCE(SUM(f.diferenca > 0), 0) AS geraram_credito,
                    COALESCE(SUM(f.diferenca < 0), 0) AS consumiram_credito
               FROM fretes f WHERE $sql", $p) ?? [];
    }

    /** Série diária (ou semanal, se o período passar de 45 dias). */
    public function serieTemporal(): array
    {
        [$sql, $p] = $this->ondeFretes();
        $semanal = $this->f->dias() > 45;
        $grupo = $semanal ? 'DATE_SUB(f.data, INTERVAL WEEKDAY(f.data) DAY)' : 'f.data';
        $linhas = Database::todos(
            "SELECT $grupo AS dia,
                    SUM(f.valor_cliente) AS cliente,
                    SUM(COALESCE(f.valor_transp, 0)) AS transp,
                    SUM(COALESCE(f.diferenca, 0)) AS diferenca
               FROM fretes f WHERE $sql GROUP BY dia ORDER BY dia", $p);
        return ['semanal' => $semanal, 'linhas' => $linhas];
    }

    /** Admin: saldo por gestor. Gestor: saldo por vendedor da equipe. */
    public function saldoPorGrupo(): array
    {
        [$w, $p] = $this->escopo->filtroVendedor('s.vendedor_id', $this->f->gestorId);
        $porVendedor = !$this->escopo->veTudo() || $this->f->gestorId;
        $campo = $porVendedor ? 's.vendedor' : 's.gestor';
        return [
            'titulo' => $porVendedor ? 'Saldo por vendedor' : 'Saldo por gestor',
            'linhas' => Database::todos(
                "SELECT $campo AS nome, SUM(s.saldo) AS saldo
                   FROM vw_saldo_vendedor s WHERE $w AND s.ativo = 1
                  GROUP BY $campo HAVING ABS(SUM(s.saldo)) >= 0.01
                  ORDER BY saldo DESC", $p),
        ];
    }

    /** Maiores créditos e saldos negativos. */
    public function rankingVendedores(int $limite = 8): array
    {
        [$w, $p] = $this->escopo->filtroVendedor('s.vendedor_id', $this->f->gestorId);
        $base = "SELECT s.vendedor_id, s.vendedor, s.gestor, s.saldo, s.reservado, s.disponivel
                   FROM vw_saldo_vendedor s WHERE $w";
        return [
            'credito'  => Database::todos("$base AND s.saldo > 0 ORDER BY s.saldo DESC LIMIT $limite", $p),
            'negativo' => Database::todos("$base AND s.saldo < 0 ORDER BY s.saldo ASC LIMIT $limite", $p),
        ];
    }

    /** Faturas (só admin/financeiro). */
    public function resumoFaturas(): array
    {
        $dias = (int) (Database::um(
            "SELECT valor FROM configuracoes WHERE chave = 'alerta_vencimento_dias'")['valor'] ?? 3);
        [$we, $pe] = $this->filtroEmpresa('fa.empresa_id');

        $aPagar = Database::um(
            "SELECT COUNT(*) qtd, COALESCE(SUM(total_a_pagar), 0) total,
                    COALESCE(SUM(vencida), 0) vencidas,
                    COALESCE(SUM(status <> 'paga' AND vencimento BETWEEN CURDATE()
                                 AND CURDATE() + INTERVAL ? DAY), 0) vencendo,
                    COALESCE(SUM(status = 'divergente'), 0) divergentes
               FROM vw_faturas fa WHERE status <> 'paga' AND $we", array_merge([$dias], $pe));

        $pagas = Database::um(
            "SELECT COUNT(*) qtd, COALESCE(SUM(total_a_pagar), 0) total
               FROM vw_faturas fa WHERE status = 'paga' AND pago_em BETWEEN ? AND ? AND $we",
            array_merge([$this->f->de, $this->f->ate], $pe));

        $proximas = Database::todos(
            "SELECT id, transportadora, empresa, numero_documento, vencimento, status,
                    total_a_pagar, vencida
               FROM vw_faturas fa WHERE status <> 'paga' AND $we
              ORDER BY vencida DESC, vencimento IS NULL, vencimento LIMIT 8", $pe);

        $porStatus = Database::todos(
            "SELECT status, COUNT(*) qtd, COALESCE(SUM(total_a_pagar), 0) total
               FROM vw_faturas fa WHERE $we GROUP BY status", $pe);

        return compact('aPagar', 'pagas', 'proximas', 'porStatus', 'dias');
    }

    /** Pendências que pedem ação. */
    public function pendencias(): array
    {
        [$w, $p] = $this->escopo->filtroVendedor('vendedor_id', $this->f->gestorId);
        $sol = Database::um(
            "SELECT COUNT(*) n, COALESCE(SUM(valor), 0) v FROM solicitacoes_credito
              WHERE status = 'pendente' AND $w", $p);
        $semVendedor = $this->escopo->veTudo()
            ? (int) (Database::um('SELECT COUNT(*) n FROM fretes WHERE vendedor_id IS NULL')['n'] ?? 0)
            : 0;
        return [
            'solicitacoes' => (int) $sol['n'],
            'solicitacoes_valor' => (float) $sol['v'],
            'fretes_sem_vendedor' => $semVendedor,
        ];
    }

    /** Listas para os selects de filtro. */
    public function opcoesFiltro(): array
    {
        return [
            'empresas' => Database::todos('SELECT id, nome FROM empresas WHERE ativo = 1 ORDER BY nome'),
            'gestores' => $this->escopo->veTudo()
                ? Database::todos('SELECT id, nome FROM gestores WHERE ativo = 1 ORDER BY nome') : [],
        ];
    }

    // ------------------------------------------------------------------

    private function ondeFretes(): array
    {
        [$w, $p] = $this->escopo->filtroVendedor('f.vendedor_id', $this->f->gestorId);
        $sql = "f.data BETWEEN ? AND ? AND $w";
        $params = array_merge([$this->f->de, $this->f->ate], $p);
        if ($this->f->empresaId) {
            $sql .= ' AND f.empresa_id = ?';
            $params[] = $this->f->empresaId;
        }
        return [$sql, $params];
    }

    private function filtroEmpresa(string $coluna): array
    {
        return $this->f->empresaId ? ["$coluna = ?", [$this->f->empresaId]] : ['1 = 1', []];
    }
}
