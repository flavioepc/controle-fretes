<?php
declare(strict_types=1);

namespace App\Fretes;

use App\Core\Database;
use PDOException;

/**
 * Solicitações de uso de crédito.
 * A aprovação usa a procedure sp_decidir_solicitacao, que confere o saldo DISPONÍVEL
 * (saldo − reservas) dentro de uma transação — dois gestores aprovando ao mesmo tempo
 * não conseguem passar do saldo.
 */
final class CreditoRepository
{
    public function __construct(private Escopo $escopo)
    {
    }

    /** Pedidos visíveis para o usuário, com o gestor ATUAL do vendedor (quem aprova). */
    public function listar(string $filtroStatus, int $limite = 100): array
    {
        [$w, $p] = $this->escopo->filtroVendedor('s.vendedor_id');
        $sqlStatus = '';
        if ($filtroStatus === 'abertas') {
            $sqlStatus = " AND s.status IN ('pendente','aprovada')";
        } elseif (in_array($filtroStatus, ['pendente', 'aprovada', 'recusada', 'utilizada', 'cancelada'], true)) {
            $sqlStatus = ' AND s.status = ?';
            $p[] = $filtroStatus;
        }
        return Database::todos(
            "SELECT s.*, v.nome vendedor, v.gestor_id gestor_atual_id, g.nome gestor,
                    sv.disponivel, f.id frete_id, f.nota_fiscal frete_nf
               FROM solicitacoes_credito s
               JOIN vendedores v ON v.id = s.vendedor_id
               JOIN gestores g ON g.id = v.gestor_id
               JOIN vw_saldo_vendedor sv ON sv.vendedor_id = s.vendedor_id
               LEFT JOIN fretes f ON f.solicitacao_id = s.id
              WHERE $w $sqlStatus
              ORDER BY s.status = 'pendente' DESC, s.solicitado_em DESC
              LIMIT $limite", $p);
    }

    public function buscar(int $id): ?array
    {
        return Database::um(
            'SELECT s.*, v.gestor_id gestor_atual_id, v.nome vendedor
               FROM solicitacoes_credito s JOIN vendedores v ON v.id = s.vendedor_id WHERE s.id = ?', [$id]);
    }

    /** Pedidos esperando decisão DESTE usuário (contador do menu). */
    public function pendentesParaMim(): int
    {
        if (!Permissoes::pode('credito.decidir')) {
            return 0;
        }
        if ($this->escopo->veTudo()) {
            $r = Database::um("SELECT COUNT(*) n FROM solicitacoes_credito WHERE status = 'pendente'");
        } elseif ($this->escopo->perfil === 'gestor' && $this->escopo->gestorId) {
            $r = Database::um(
                "SELECT COUNT(*) n FROM solicitacoes_credito s JOIN vendedores v ON v.id = s.vendedor_id
                  WHERE s.status = 'pendente' AND v.gestor_id = ?", [$this->escopo->gestorId]);
        } else {
            return 0;
        }
        return (int) ($r['n'] ?? 0);
    }

    /** Saldos dos vendedores que o usuário pode usar no formulário. */
    public function vendedoresParaSolicitar(): array
    {
        $sql = 'SELECT sv.vendedor_id id, sv.vendedor nome, sv.gestor, sv.saldo, sv.reservado, sv.disponivel
                  FROM vw_saldo_vendedor sv WHERE sv.ativo = 1';
        return match ($this->escopo->perfil) {
            'admin', 'operador', 'financeiro' => Database::todos("$sql ORDER BY sv.gestor, sv.vendedor"),
            'gestor'   => $this->escopo->gestorId
                ? Database::todos("$sql AND sv.gestor_id = ? ORDER BY sv.vendedor", [$this->escopo->gestorId]) : [],
            'vendedor' => $this->escopo->vendedorId
                ? Database::todos("$sql AND sv.vendedor_id = ?", [$this->escopo->vendedorId]) : [],
            default    => [],
        };
    }

    public function saldo(int $vendedorId): ?array
    {
        return Database::um('SELECT * FROM vw_saldo_vendedor WHERE vendedor_id = ?', [$vendedorId]);
    }

    public function criar(array $d, int $usuarioId): int
    {
        $gestor = Database::um('SELECT gestor_id FROM vendedores WHERE id = ?', [$d['vendedor_id']]);
        Database::get()->prepare(
            'INSERT INTO solicitacoes_credito
               (vendedor_id, gestor_id, tipo, valor, cliente, justificativa, validade, solicitado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$d['vendedor_id'], $gestor['gestor_id'], $d['tipo'], $d['valor'], $d['cliente'],
                       $d['justificativa'], $d['validade'], $usuarioId]);
        return (int) Database::get()->lastInsertId();
    }

    /**
     * Aprova (true) ou recusa (false). Devolve null se deu certo ou a mensagem de erro da regra.
     */
    public function decidir(int $id, bool $aprovar, int $usuarioId, ?string $motivo): ?string
    {
        try {
            $st = Database::get()->prepare('CALL sp_decidir_solicitacao(?, ?, ?, ?)');
            $st->execute([$id, $aprovar ? 1 : 0, $usuarioId, $motivo]);
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

    /** Cancela um pedido pendente ou aprovado (libera a reserva). Uso "outro" já debitado não cancela. */
    public function cancelar(int $id, int $usuarioId, ?string $motivo): bool
    {
        $st = Database::get()->prepare(
            "UPDATE solicitacoes_credito
                SET status = 'cancelada', decidido_por = ?, decidido_em = NOW(),
                    motivo_decisao = COALESCE(?, 'Cancelada')
              WHERE id = ? AND status IN ('pendente', 'aprovada')
                AND NOT EXISTS (SELECT 1 FROM fretes WHERE solicitacao_id = ?)");
        $st->execute([$usuarioId, $motivo, $id, $id]);
        return $st->rowCount() > 0;
    }
}
