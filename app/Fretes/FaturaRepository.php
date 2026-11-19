<?php
declare(strict_types=1);

namespace App\Fretes;

use App\Core\Database;

/**
 * Faturas (boletos) das transportadoras.
 * Fluxo: aberta -> conferida | divergente -> enviada_pagto -> paga.
 * Sem procedure: são updates de uma linha só, sem disputa de concorrência como no crédito.
 */
final class FaturaRepository
{
    public const POR_PAGINA = 30;
    public const STATUS = ['aberta', 'conferida', 'divergente', 'enviada_pagto', 'paga'];

    /** @return array{linhas: array, total: int, somas: array} */
    public function listar(array $f, int $pagina): array
    {
        [$where, $p] = $this->where($f);
        $somas = Database::um(
            "SELECT COUNT(*) qtd, COALESCE(SUM(total_a_pagar), 0) total, COALESCE(SUM(vencida), 0) vencidas
               FROM vw_faturas fa WHERE $where", $p);

        $offset = max(0, ($pagina - 1) * self::POR_PAGINA);
        $linhas = Database::todos(
            "SELECT fa.* FROM vw_faturas fa WHERE $where
              ORDER BY fa.vencida DESC, fa.vencimento IS NULL, fa.vencimento, fa.id DESC
              LIMIT " . self::POR_PAGINA . " OFFSET $offset", $p);

        return ['linhas' => $linhas, 'total' => (int) $somas['qtd'], 'somas' => $somas];
    }

    private function where(array $f): array
    {
        $w = [];
        $p = [];
        $status = $f['status'] ?? 'vencidas';
        if ($status === 'vencidas') {
            $w[] = 'fa.vencida = 1';                       // não paga e vencimento antes de hoje
        } elseif ($status === 'aberta') {
            // a vencer: não paga, vence hoje ou depois (ou ainda sem vencimento), em qualquer etapa
            $w[] = "fa.status <> 'paga' AND (fa.vencimento IS NULL OR fa.vencimento >= CURDATE())";
        } elseif ($status !== 'todas' && in_array($status, self::STATUS, true)) {
            $w[] = 'fa.status = ?';
            $p[] = $status;
        }
        if (!empty($f['transportadora'])) {
            $w[] = 'fa.transportadora_id = ?';
            $p[] = (int) $f['transportadora'];
        }
        if (!empty($f['empresa'])) {
            $w[] = 'fa.empresa_id = ?';
            $p[] = (int) $f['empresa'];
        }
        if (!empty($f['busca'])) {
            $w[] = 'fa.numero_documento LIKE ?';
            $p[] = '%' . $f['busca'] . '%';
        }
        return [$w ? implode(' AND ', $w) : '1 = 1', $p];
    }

    /**
     * Faturas com fretes DIGITADOS no período (fretes.criado_em), para imprimir em lote no fim do dia.
     * $f: de, ate (Y-m-d), usuario (só fretes lançados por ele, ou null), transportadora,
     *     situacao: pendentes (nunca impressa, ou ganhou frete depois da última impressão) | impressas | todas.
     */
    public function paraImpressao(array $f): array
    {
        $w = ['f.fatura_id IS NOT NULL', 'f.criado_em >= ?', 'f.criado_em < DATE_ADD(?, INTERVAL 1 DAY)'];
        $p = [$f['de'], $f['ate']];
        if (!empty($f['usuario'])) {
            $w[] = 'f.criado_por = ?';
            $p[] = (int) $f['usuario'];
        }
        $where = implode(' AND ', $w);
        $fora = [];
        if (!empty($f['transportadora'])) {
            $fora[] = 'fa.transportadora_id = ?';
            $p[] = (int) $f['transportadora'];
        }
        // pendente = nunca impressa, ou algum frete da fatura foi digitado depois da última impressão
        $pendente = '(i.ultima_impressao IS NULL OR i.ultima_impressao < u.ultimo_frete)';
        match ($f['situacao'] ?? 'pendentes') {
            'pendentes' => $fora[] = $pendente,
            'impressas' => $fora[] = "NOT $pendente",
            default     => null,
        };
        $filtro = $fora ? 'WHERE ' . implode(' AND ', $fora) : '';
        $linhas = Database::todos(
            "SELECT fa.*, x.qtd_periodo, x.ultimo_lancamento, i.qtd_impressoes, i.ultima_impressao,
                    $pendente pendente
               FROM vw_faturas fa
               JOIN (SELECT f.fatura_id, COUNT(*) qtd_periodo, MAX(f.criado_em) ultimo_lancamento
                       FROM fretes f WHERE $where GROUP BY f.fatura_id) x ON x.fatura_id = fa.id
               JOIN (SELECT fatura_id, MAX(criado_em) ultimo_frete FROM fretes
                      WHERE fatura_id IS NOT NULL GROUP BY fatura_id) u ON u.fatura_id = fa.id
               LEFT JOIN (SELECT fatura_id, COUNT(*) qtd_impressoes, MAX(impressa_em) ultima_impressao
                            FROM faturas_impressoes GROUP BY fatura_id) i ON i.fatura_id = fa.id
               $filtro
              ORDER BY fa.transportadora, fa.numero_documento
              LIMIT 300", $p);
        return $this->comUltimaImpressao($linhas);
    }

    /** Grava que estas faturas foram impressas (só ids que existem). */
    public function registrarImpressao(array $ids, ?int $usuarioId, string $modelo): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return 0;
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $st = Database::get()->prepare(
            "INSERT INTO faturas_impressoes (fatura_id, usuario_id, modelo)
             SELECT id, ?, ? FROM faturas WHERE id IN ($marcas)");
        $st->execute(array_merge([$usuarioId, $modelo], $ids));
        return $st->rowCount();
    }

    /**
     * Acrescenta qtd_impressoes, ultima_impressao e ultima_impressao_por (nome) em cada fatura da lista.
     * @param array $faturas linhas com 'id'
     */
    public function comUltimaImpressao(array $faturas): array
    {
        $ids = array_values(array_filter(array_map(fn ($f) => (int) $f['id'], $faturas)));
        if (!$ids) {
            return $faturas;
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        // a última linha de cada fatura (maior id = mais recente)
        $ultimas = Database::todos(
            "SELECT fi.fatura_id, fi.usuario_id, fi.impressa_em, c.qtd
               FROM faturas_impressoes fi
               JOIN (SELECT fatura_id, MAX(id) ultimo, COUNT(*) qtd FROM faturas_impressoes
                      WHERE fatura_id IN ($marcas) GROUP BY fatura_id) c ON c.ultimo = fi.id", $ids);
        $porFatura = array_column($ultimas, null, 'fatura_id');

        $usuarios = array_values(array_unique(array_filter(array_column($ultimas, 'usuario_id'))));
        $nomes = [];
        if ($usuarios) {
            $m = implode(',', array_fill(0, count($usuarios), '?'));
            $nomes = array_column(Database::todos("SELECT id, nome FROM usuarios WHERE id IN ($m)", $usuarios, 'portal'), 'nome', 'id');
        }
        foreach ($faturas as &$fa) {
            $u = $porFatura[$fa['id']] ?? null;
            $fa['qtd_impressoes'] = $u ? (int) $u['qtd'] : 0;
            $fa['ultima_impressao'] = $u['impressa_em'] ?? null;
            $fa['ultima_impressao_por'] = $u ? ($nomes[$u['usuario_id']] ?? null) : null;
        }
        return $faturas;
    }

    public function buscar(int $id): ?array
    {
        return Database::um('SELECT * FROM vw_faturas fa WHERE fa.id = ?', [$id]);
    }

    /** Fretes vinculados a esta fatura, para conferir contra o boleto (e para o relatório impresso). */
    public function fretesVinculados(int $faturaId): array
    {
        return Database::todos(
            'SELECT f.id, f.data, f.nota_fiscal, f.uf, f.valor_cliente, f.valor_transp, f.diferenca,
                    f.numero_pedido, v.nome vendedor, mi.nome motivo_isencao
               FROM fretes f LEFT JOIN vendedores v ON v.id = f.vendedor_id
               LEFT JOIN motivos_isencao mi ON mi.id = f.isencao_id
              WHERE f.fatura_id = ? ORDER BY f.data, f.id', [$faturaId]);
    }

    public function opcoesFiltro(): array
    {
        return [
            'transportadoras' => Database::todos('SELECT id, nome FROM transportadoras WHERE ativo = 1 ORDER BY nome'),
            'empresas' => Database::todos('SELECT id, nome FROM empresas WHERE ativo = 1 ORDER BY nome'),
        ];
    }

    /** Confere (ou re-confere) uma fatura: dados do boleto + decisão conferida/divergente. */
    public function conferir(int $id, array $d): void
    {
        Database::get()->prepare(
            'UPDATE faturas SET vencimento = ?, desconto = ?, valor_boleto = ?, responsavel = ?,
                    status = ?, obs = ? WHERE id = ?')
            ->execute([$d['vencimento'], $d['desconto'], $d['valor_boleto'], $d['responsavel'],
                       $d['status'], $d['obs'], $id]);
    }

    /** @return bool false se a fatura não estava conferida (nada foi alterado) */
    public function enviarPagamento(int $id): bool
    {
        $st = Database::get()->prepare(
            "UPDATE faturas SET status = 'enviada_pagto', enviado_pagto_em = CURDATE()
              WHERE id = ? AND status = 'conferida'");
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    /** @return bool false se a fatura não estava enviada para pagamento */
    public function marcarPaga(int $id, string $pagoEm, ?string $recebidoPor, ?string $recebidoEm): bool
    {
        $st = Database::get()->prepare(
            "UPDATE faturas SET status = 'paga', pago_em = ?, recebido_por = ?, recebido_em = ?
              WHERE id = ? AND status = 'enviada_pagto'");
        $st->execute([$pagoEm, $recebidoPor, $recebidoEm, $id]);
        return $st->rowCount() > 0;
    }

    // ------------------------------------------------------------ alteração de fatura enviada/paga

    /** Campos que podem ser corrigidos depois do envio para pagamento => [rótulo, tipo]. */
    public const ALTERAVEIS = [
        'vencimento' => ['Vencimento', 'data'],
        'valor_boleto' => ['Valor do boleto', 'moeda'],
        'desconto' => ['Desconto', 'moeda'],
        'responsavel' => ['Conferido por', 'texto'],
        'obs' => ['Observação', 'texto'],
        'enviado_pagto_em' => ['Enviada p/ pagto em', 'data'],
        'pago_em' => ['Pago em', 'data'],
        'recebido_em' => ['Recebido (RCTO) em', 'data'],
        'recebido_por' => ['Recebido por', 'texto'],
    ];
    /** Só existem depois de paga. */
    public const CAMPOS_PAGAMENTO = ['pago_em', 'recebido_em', 'recebido_por'];

    /**
     * Corrige os dados de uma fatura já enviada para pagamento ou paga, sem mudar o status nem os fretes,
     * e grava no histórico (faturas_alteracoes) o motivo e cada campo que mudou (antes → depois).
     * @param array $d dados já validados (ver FaturaController::alterar), só chaves de ALTERAVEIS
     * @return string|null mensagem se não alterou (status mudou no meio do caminho, nada diferente)
     */
    public function alterarDados(int $id, array $d, string $motivo, ?int $usuarioId): ?string
    {
        $db = Database::get();
        $db->beginTransaction();
        try {
            $atual = Database::um('SELECT * FROM faturas WHERE id = ? FOR UPDATE', [$id]);
            if (!$atual || !in_array($atual['status'], ['enviada_pagto', 'paga'], true)) {
                $db->rollBack();
                return 'a fatura não está enviada para pagamento nem paga';
            }
            if ($atual['status'] !== 'paga') {
                $d = array_diff_key($d, array_flip(self::CAMPOS_PAGAMENTO));
            }

            $mudou = [];
            $linhas = [];
            foreach (array_intersect_key($d, self::ALTERAVEIS) as $campo => $novo) {
                [$rotulo, $tipo] = self::ALTERAVEIS[$campo];
                $antes = $this->exibir($atual[$campo], $tipo);
                $depois = $this->exibir($novo, $tipo);
                if ($antes !== $depois) {
                    $mudou[$campo] = $novo;
                    $linhas[] = "$rotulo: $antes → $depois";
                }
            }
            if (!$mudou) {
                $db->rollBack();
                return 'nenhum dado diferente do que já estava gravado';
            }

            $sets = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($mudou)));
            $db->prepare("UPDATE faturas SET $sets WHERE id = ?")->execute([...array_values($mudou), $id]);
            $db->prepare('INSERT INTO faturas_alteracoes (fatura_id, usuario_id, motivo, detalhe) VALUES (?, ?, ?, ?)')
               ->execute([$id, $usuarioId, $motivo, implode("\n", $linhas)]);
            $db->commit();
            return null;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------ correção de conhecimento

    /**
     * A transportadora mandou o conhecimento com o nº errado: troca o nº da fatura inteira (os fretes vão junto).
     * Não vale para fatura paga. Se já houver outro conhecimento em andamento com o nº novo, recusa — nesse caso
     * os fretes devem ser retirados para ele (retirarFretes), para não ficar com duas faturas iguais abertas.
     * @return string|null mensagem se não trocou
     */
    public function trocarNumero(int $id, string $novo, string $motivo, int $usuarioId): ?string
    {
        $novo = mb_strtoupper(trim($novo));
        $db = Database::get();
        $db->beginTransaction();
        try {
            $fa = $this->travarParaCorrecao($id);
            if (is_string($fa)) {
                $db->rollBack();
                return $fa;
            }
            if ($novo === mb_strtoupper(trim($fa['numero_documento']))) {
                $db->rollBack();
                return 'o nº novo é igual ao atual';
            }
            $outra = Database::um(
                "SELECT id FROM faturas WHERE transportadora_id = ? AND numero_documento = ? AND id <> ?
                    AND status IN ('aberta', 'conferida', 'divergente') LIMIT 1",
                [$fa['transportadora_id'], $novo, $id]);
            if ($outra) {
                $db->rollBack();
                return "já existe o conhecimento $novo em andamento desta transportadora; marque os fretes e use "
                    . '"Retirar selecionados" informando esse nº';
            }
            $db->prepare('UPDATE faturas SET numero_documento = ? WHERE id = ?')->execute([$novo, $id]);
            $this->historico($id, $usuarioId, $motivo, ["Nº do conhecimento: {$fa['numero_documento']} → $novo"]);
            $db->commit();
            return null;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Tira fretes de um conhecimento: vão para outro nº da mesma transportadora (achado ou criado pela mesma
     * regra do lançamento, FreteRepository::faturaPara) ou ficam sem conhecimento (aguardando fatura).
     * Não mexe em valor nem em saldo, só em fretes.fatura_id. Se o total de uma fatura já conferida ou enviada
     * para pagamento mudar, ela volta a "divergente" para ser conferida de novo. Grava o histórico nas duas.
     * @param int[] $freteIds
     * @return array{0: string|null, 1: int|null} [mensagem se não retirou, id da fatura de destino]
     */
    public function retirarFretes(int $id, array $freteIds, string $destino, string $motivo, int $usuarioId): array
    {
        $destino = mb_strtoupper(trim($destino));
        $db = Database::get();
        $db->beginTransaction();
        try {
            $fa = $this->travarParaCorrecao($id);
            if (is_string($fa)) {
                $db->rollBack();
                return [$fa, null];
            }
            if ($destino !== '' && $destino === mb_strtoupper(trim($fa['numero_documento']))) {
                $db->rollBack();
                return ['o destino é o próprio conhecimento; informe outro nº ou deixe em branco', null];
            }
            $freteIds = array_values(array_unique(array_filter(array_map('intval', $freteIds))));
            $fretes = $freteIds ? Database::todos(
                'SELECT id, nota_fiscal, valor_transp FROM fretes
                  WHERE fatura_id = ? AND id IN (' . implode(',', array_fill(0, count($freteIds), '?')) . ')
                  ORDER BY data, id FOR UPDATE',
                [$id, ...$freteIds]) : [];
            if (!$fretes) {
                $db->rollBack();
                return ['nenhum dos fretes marcados está neste conhecimento', null];
            }

            $destinoId = null;
            $destinoAntes = null;
            if ($destino !== '') {
                $destinoId = (new FreteRepository())->faturaPara(
                    (int) $fa['transportadora_id'], $destino, $fa['empresa_id'] ? (int) $fa['empresa_id'] : null,
                    date('Y-m-d'), $usuarioId);
                $destinoAntes = Database::um('SELECT status FROM faturas WHERE id = ? FOR UPDATE', [$destinoId])['status'];
            }

            $ids = array_column($fretes, 'id');
            $db->prepare('UPDATE fretes SET fatura_id = ? WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')')
               ->execute([$destinoId, ...$ids]);

            $total = array_sum(array_map(fn ($f) => (float) $f['valor_transp'], $fretes));
            $lista = implode(', ', array_map(
                fn ($f) => 'NF ' . $f['nota_fiscal'] . ' (' . moeda($f['valor_transp']) . ')', $fretes));
            $para = $destino !== '' ? "para o conhecimento $destino" : 'deixado(s) sem conhecimento (aguardando fatura)';

            $linhas = [count($fretes) . ' frete(s) retirado(s), ' . moeda($total) . ", $para: $lista"];
            $this->voltarParaDivergente($id, $fa['status'], $linhas);
            $this->historico($id, $usuarioId, $motivo, $linhas);

            if ($destinoId) {
                $linhas = [count($fretes) . ' frete(s) recebido(s), ' . moeda($total)
                    . ", do conhecimento {$fa['numero_documento']}: $lista"];
                $this->voltarParaDivergente($destinoId, (string) $destinoAntes, $linhas);
                $this->historico($destinoId, $usuarioId, $motivo, $linhas);
            }
            $db->commit();
            return [null, $destinoId];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Trava a transportadora (mesma ordem de FreteRepository::faturaPara) e a fatura.
     * @return array|string a fatura, ou a mensagem se não pode ser corrigida
     */
    private function travarParaCorrecao(int $id): array|string
    {
        $t = Database::um('SELECT transportadora_id FROM faturas WHERE id = ?', [$id]);
        if (!$t) {
            return 'fatura não encontrada';
        }
        Database::um('SELECT id FROM transportadoras WHERE id = ? FOR UPDATE', [$t['transportadora_id']]);
        $fa = Database::um('SELECT * FROM faturas WHERE id = ? FOR UPDATE', [$id]);
        if ($fa['status'] === 'paga') {
            return 'fatura já paga não pode ter o conhecimento corrigido';
        }
        return $fa;
    }

    /** O total mudou: fatura conferida ou enviada para pagamento precisa ser conferida de novo. */
    private function voltarParaDivergente(int $faturaId, string $statusAntes, array &$linhas): void
    {
        if (!in_array($statusAntes, ['conferida', 'enviada_pagto'], true)) {
            return;
        }
        Database::get()->prepare("UPDATE faturas SET status = 'divergente' WHERE id = ?")->execute([$faturaId]);
        $rotulo = $statusAntes === 'conferida' ? 'Conferida' : 'Enviada p/ pagto';
        $linhas[] = "Status: $rotulo → Divergente (o total mudou; confira de novo)";
    }

    private function historico(int $faturaId, ?int $usuarioId, string $motivo, array $linhas): void
    {
        Database::get()->prepare('INSERT INTO faturas_alteracoes (fatura_id, usuario_id, motivo, detalhe) VALUES (?, ?, ?, ?)')
            ->execute([$faturaId, $usuarioId, $motivo, implode("\n", $linhas)]);
    }

    /** Histórico de alterações da fatura, mais recente primeiro, com o nome de quem alterou (portal). */
    public function alteracoes(int $faturaId): array
    {
        $linhas = Database::todos(
            'SELECT id, usuario_id, motivo, detalhe, alterada_em FROM faturas_alteracoes
              WHERE fatura_id = ? ORDER BY alterada_em DESC, id DESC', [$faturaId]);
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

    /** Valor como aparece no histórico (também serve para comparar: 10 e "10.00" são iguais). */
    private function exibir(mixed $v, string $tipo): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        return match ($tipo) {
            'data' => data_br((string) $v),
            'moeda' => moeda($v),
            default => (string) $v,
        };
    }
}
