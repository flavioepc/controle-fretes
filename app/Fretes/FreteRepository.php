<?php
declare(strict_types=1);

namespace App\Fretes;

use App\Core\Database;
use PDO;

/**
 * Leitura e gravação de fretes.
 * Regras ao salvar:
 *   - o gestor gravado é o gestor ATUAL do vendedor (fica como histórico)
 *   - nº do documento -> encontra a fatura (transportadora + nº) ou cria uma "aberta"
 *   - frete grátis aprovado vinculado -> a solicitação vira "utilizada"
 *     (ao trocar/remover o vínculo numa edição, a anterior volta a "aprovada")
 */
final class FreteRepository
{
    public const POR_PAGINA = 50;

    // ------------------------------------------------------------ listagem

    /** @return array{linhas: array, total: int, somas: array} */
    public function listar(array $f, int $pagina): array
    {
        [$where, $p] = $this->where($f);
        $somas = Database::um(
            "SELECT COUNT(*) qtd, COALESCE(SUM(f.valor_cliente),0) cliente,
                    COALESCE(SUM(f.valor_transp),0) transp, COALESCE(SUM(f.diferenca),0) dif,
                    COALESCE(SUM(IF(f.isencao_id IS NULL, 0, f.valor_transp)),0) isento
               FROM fretes f WHERE $where", $p);

        $offset = max(0, ($pagina - 1) * self::POR_PAGINA);
        $linhas = Database::todos(
            "SELECT f.*, v.nome vendedor, g.nome gestor, t.nome transportadora, e.nome empresa,
                    fa.numero_documento, fa.status status_fatura, mi.nome motivo_isencao
               FROM fretes f
               JOIN transportadoras t ON t.id = f.transportadora_id
               LEFT JOIN motivos_isencao mi ON mi.id = f.isencao_id
               LEFT JOIN vendedores v ON v.id = f.vendedor_id
               LEFT JOIN gestores g   ON g.id = f.gestor_id
               LEFT JOIN empresas e   ON e.id = f.empresa_id
               LEFT JOIN faturas fa   ON fa.id = f.fatura_id
              WHERE $where
              ORDER BY f.data DESC, f.id DESC
              LIMIT " . self::POR_PAGINA . " OFFSET $offset", $p);

        return ['linhas' => $linhas, 'total' => (int) $somas['qtd'], 'somas' => $somas];
    }

    private function where(array $f): array
    {
        $w = ['f.data BETWEEN ? AND ?'];
        $p = [$f['de'], $f['ate']];
        foreach (['vendedor' => 'f.vendedor_id', 'transportadora' => 'f.transportadora_id',
                  'empresa' => 'f.empresa_id'] as $chave => $coluna) {
            if (!empty($f[$chave])) {
                $w[] = "$coluna = ?";
                $p[] = (int) $f[$chave];
            }
        }
        if (!empty($f['busca'])) {
            $w[] = '(f.nota_fiscal LIKE ? OR f.obs LIKE ? OR f.numero_pedido LIKE ? OR f.fatura_id IN
                     (SELECT id FROM faturas WHERE numero_documento LIKE ?))';
            $like = '%' . $f['busca'] . '%';
            array_push($p, $like, $like, $like, $like);
        }
        $situacao = (string) ($f['situacao'] ?? '');
        if (preg_match('/^isento_(\d+)$/', $situacao, $m)) {      // isentos de um motivo
            $w[] = 'f.isencao_id = ?';
            $p[] = (int) $m[1];
        }
        match ($situacao) {
            'aguardando'   => $w[] = 'f.valor_transp IS NULL',
            'sem_vendedor' => $w[] = 'f.vendedor_id IS NULL',
            'sem_fatura'   => $w[] = 'f.fatura_id IS NULL',
            'credito'      => $w[] = 'f.diferenca > 0',
            'debito'       => $w[] = 'f.diferenca < 0',
            'isento'       => $w[] = 'f.isencao_id IS NOT NULL',
            'gratis_vendedor' => $w[] = 'f.isencao_id IS NULL AND f.valor_cliente = 0',
            default        => null,
        };
        return [implode(' AND ', $w), $p];
    }

    // ------------------------------------------------------------ um frete

    public function buscar(int $id): ?array
    {
        return Database::um(
            'SELECT f.*, fa.numero_documento FROM fretes f
               LEFT JOIN faturas fa ON fa.id = f.fatura_id WHERE f.id = ?', [$id]);
    }

    /** Outros fretes com a mesma NF (para o aviso de duplicidade). */
    public function mesmaNota(string $nf, ?int $ignorarId): array
    {
        return Database::todos(
            'SELECT f.id, f.data, f.valor_cliente, v.nome vendedor
               FROM fretes f LEFT JOIN vendedores v ON v.id = f.vendedor_id
              WHERE f.nota_fiscal = ? AND f.id <> ? ORDER BY f.data DESC LIMIT 5',
            [$nf, $ignorarId ?? 0]);
    }

    // ------------------------------------------------------------ gravação

    /**
     * @param array $d dados já validados (ver FreteController::validar)
     * @return int id do frete
     */
    public function salvar(array $d, ?int $id, int $usuarioId): int
    {
        $db = Database::get();
        $db->beginTransaction();
        try {
            $anterior = $id ? $this->buscar($id) : null;

            $gestorId = $d['vendedor_id']
                ? (int) Database::um('SELECT gestor_id FROM vendedores WHERE id = ?', [$d['vendedor_id']])['gestor_id']
                : null;
            // numa edição, se o vendedor não mudou, mantém o gestor histórico
            if ($anterior && (int) $anterior['vendedor_id'] === (int) $d['vendedor_id'] && $anterior['gestor_id']) {
                $gestorId = (int) $anterior['gestor_id'];
            }

            if ($d['documento'] === '') {
                $faturaId = null;
            } elseif ($anterior && $anterior['fatura_id']
                && (int) $anterior['transportadora_id'] === (int) $d['transportadora_id']
                && mb_strtoupper(trim((string) $anterior['numero_documento'])) === mb_strtoupper(trim($d['documento']))) {
                // numa edição sem trocar transportadora/nº, o frete fica na fatura dele (mesmo se já paga)
                $faturaId = (int) $anterior['fatura_id'];
            } else {
                $faturaId = $this->faturaPara($d['transportadora_id'], $d['documento'], $d['empresa_id'], $d['data'], $usuarioId);
            }

            $campos = [
                'data' => $d['data'], 'nota_fiscal' => $d['nota_fiscal'], 'empresa_id' => $d['empresa_id'],
                'transportadora_id' => $d['transportadora_id'], 'vendedor_id' => $d['vendedor_id'],
                'gestor_id' => $gestorId, 'uf' => $d['uf'], 'valor_cliente' => $d['valor_cliente'],
                'valor_transp' => $d['valor_transp'], 'isencao_id' => $d['isencao_id'],
                'valor_pedido' => $d['valor_pedido'], 'numero_pedido' => $d['numero_pedido'], 'fatura_id' => $faturaId,
                'solicitacao_id' => $d['solicitacao_id'], 'obs' => $d['obs'],
            ];

            if ($id) {
                $sets = implode(', ', array_map(fn ($c) => "$c = :$c", array_keys($campos)));
                $st = $db->prepare("UPDATE fretes SET $sets WHERE id = :id");
                $st->execute($campos + ['id' => $id]);
            } else {
                $cols = implode(', ', array_keys($campos));
                $vals = implode(', ', array_map(fn ($c) => ":$c", array_keys($campos)));
                $st = $db->prepare("INSERT INTO fretes ($cols, criado_por) VALUES ($vals, :criado_por)");
                $st->execute($campos + ['criado_por' => $usuarioId]);
                $id = (int) $db->lastInsertId();
            }

            // vínculo com frete grátis
            $solAnterior = $anterior['solicitacao_id'] ?? null;
            if ($solAnterior && (int) $solAnterior !== (int) $d['solicitacao_id']) {
                $db->prepare("UPDATE solicitacoes_credito SET status = 'aprovada'
                               WHERE id = ? AND status = 'utilizada'")->execute([$solAnterior]);
            }
            if ($d['solicitacao_id']) {
                $db->prepare("UPDATE solicitacoes_credito SET status = 'utilizada'
                               WHERE id = ? AND status = 'aprovada'")->execute([$d['solicitacao_id']]);
            }

            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function excluir(int $id): void
    {
        $db = Database::get();
        $db->beginTransaction();
        try {
            $f = $this->buscar($id);
            if ($f && $f['solicitacao_id']) {
                $db->prepare("UPDATE solicitacoes_credito SET status = 'aprovada'
                               WHERE id = ? AND status = 'utilizada'")->execute([$f['solicitacao_id']]);
            }
            $db->prepare('DELETE FROM fretes WHERE id = ?')->execute([$id]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Fatura em andamento da transportadora com esse número; cria uma nova "aberta" se não houver.
     * A transportadora repete números (ex.: 7000 de 05/2026 e de 11/2026), então fatura já enviada
     * para pagamento ou paga não recebe frete novo. Chamada dentro da transação de salvar() e de
     * FaturaRepository::retirarFretes() (correção de conhecimento).
     */
    public function faturaPara(int $transpId, string $numero, ?int $empresaId, string $data, int $usuarioId): int
    {
        $numero = mb_strtoupper(trim($numero));
        // trava a transportadora: dois lançamentos ao mesmo tempo não criam duas faturas iguais
        Database::um('SELECT id FROM transportadoras WHERE id = ? FOR UPDATE', [$transpId]);
        $f = Database::um(
            "SELECT id FROM faturas
              WHERE transportadora_id = ? AND numero_documento = ? AND status IN ('aberta', 'conferida', 'divergente')
              ORDER BY id DESC LIMIT 1",
            [$transpId, $numero]);
        if ($f) {
            return (int) $f['id'];
        }
        Database::get()->prepare(
            'INSERT INTO faturas (transportadora_id, empresa_id, numero_documento, data_entrada, criado_por)
             VALUES (?, ?, ?, ?, ?)')->execute([$transpId, $empresaId, $numero, $data, $usuarioId]);
        return (int) Database::get()->lastInsertId();
    }

    // ------------------------------------------------------------ apoio aos formulários

    public function opcoes(): array
    {
        return [
            'empresas' => Database::todos('SELECT id, nome FROM empresas WHERE ativo = 1 ORDER BY nome'),
            'transportadoras' => Database::todos('SELECT id, nome FROM transportadoras WHERE ativo = 1 ORDER BY nome'),
            'vendedores' => Database::todos(
                'SELECT v.id, v.nome, g.nome gestor FROM vendedores v JOIN gestores g ON g.id = v.gestor_id
                  WHERE v.ativo = 1 ORDER BY g.nome, v.nome'),
            'motivos' => Database::todos('SELECT * FROM motivos_isencao ORDER BY ordem, nome'),
            'pedido_minimo' => $this->pedidoMinimoIsencao(),
        ];
    }

    public function motivoIsencao(int $id): ?array
    {
        return Database::um('SELECT * FROM motivos_isencao WHERE id = ?', [$id]);
    }

    /** Valor do pedido a partir do qual o cliente não paga frete (configuracoes.frete_isento_pedido_minimo). */
    public function pedidoMinimoIsencao(): float
    {
        $c = Database::um("SELECT valor FROM configuracoes WHERE chave = 'frete_isento_pedido_minimo'");
        return (float) ($c['valor'] ?? 10000);
    }

    /** Fretes grátis aprovados (e o já vinculado a este frete, se houver). */
    public function solicitacoesDisponiveis(?int $atual = null): array
    {
        return Database::todos(
            "SELECT s.id, s.vendedor_id, s.valor, s.cliente, v.nome vendedor
               FROM solicitacoes_credito s JOIN vendedores v ON v.id = s.vendedor_id
              WHERE s.tipo = 'frete_gratis' AND (s.status = 'aprovada' OR s.id = ?)
              ORDER BY v.nome, s.id", [$atual ?? 0]);
    }

    public function existe(string $tabela, ?int $id): bool
    {
        if (!$id) {
            return false;
        }
        $permitidas = ['empresas', 'transportadoras', 'vendedores', 'solicitacoes_credito'];
        if (!in_array($tabela, $permitidas, true)) {
            throw new \InvalidArgumentException($tabela);
        }
        return (bool) Database::um("SELECT 1 FROM $tabela WHERE id = ?", [$id]);
    }

    public function solicitacaoValida(int $solId, ?int $vendedorId, ?int $freteId): ?string
    {
        $s = Database::um('SELECT * FROM solicitacoes_credito WHERE id = ?', [$solId]);
        if (!$s || $s['tipo'] !== 'frete_gratis') {
            return 'Solicitação de frete grátis não encontrada.';
        }
        if ((int) $s['vendedor_id'] !== (int) $vendedorId) {
            return 'O frete grátis escolhido é de outro vendedor.';
        }
        $jaUsada = Database::um('SELECT id FROM fretes WHERE solicitacao_id = ? AND id <> ?', [$solId, $freteId ?? 0]);
        if ($s['status'] !== 'aprovada' && !($s['status'] === 'utilizada' && !$jaUsada)) {
            return 'Esse frete grátis não está mais disponível (status: ' . $s['status'] . ').';
        }
        return null;
    }
}
