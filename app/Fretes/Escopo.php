<?php
declare(strict_types=1);

namespace App\Fretes;

use App\Core\Auth;
use App\Core\Database;

/**
 * Define o que o usuário logado enxerga no sistema de fretes:
 *   admin / financeiro -> tudo
 *   operador           -> todos os vendedores: lança fretes e pede crédito por eles, mas não aprova
 *                         pedidos nem mexe em faturas (ex.: assistente que lança para gestores e financeiro)
 *   gestor             -> vendedores da própria equipe
 *   vendedor           -> só ele mesmo
 * O que cada perfil pode FAZER (telas e ações) vem do painel de permissões (Permissoes); aqui fica
 * QUAIS vendedores ele enxerga e as regras de equipe.
 */
final class Escopo
{
    public readonly string $perfil;
    public readonly ?int $gestorId;
    public readonly ?int $vendedorId;
    private readonly ?int $usuarioId;

    public function __construct()
    {
        $this->perfil = Auth::perfil('fretes') ?? 'vendedor';
        $uid = Auth::id();
        $this->usuarioId = $uid;
        $this->gestorId = $this->perfil === 'gestor'
            ? $this->buscarId('SELECT id FROM gestores WHERE usuario_id = ? AND ativo = 1', $uid) : null;
        $this->vendedorId = $this->perfil === 'vendedor'
            ? $this->buscarId('SELECT id FROM vendedores WHERE usuario_id = ? AND ativo = 1', $uid) : null;
    }

    /** Enxerga todos os vendedores (sem filtro de equipe). */
    public function veTudo(): bool
    {
        return in_array($this->perfil, ['admin', 'financeiro', 'operador'], true);
    }

    /** Permissão "Ver faturas" do painel (padrão: admin e financeiro). */
    public function veFaturas(): bool
    {
        return Permissoes::pode('faturas.ver');
    }

    /**
     * Condição SQL sobre a coluna de vendedor informada.
     * Ex.: [$sql, $params] = $escopo->filtroVendedor('f.vendedor_id');
     * Sem vínculo (gestor/vendedor sem cadastro ligado ao login) -> não vê nada.
     */
    public function filtroVendedor(string $coluna, ?int $gestorFiltro = null): array
    {
        if ($this->veTudo()) {
            return $gestorFiltro
                ? ["$coluna IN (SELECT id FROM vendedores WHERE gestor_id = ?)", [$gestorFiltro]]
                : ['1 = 1', []];
        }
        if ($this->perfil === 'gestor' && $this->gestorId) {
            return ["$coluna IN (SELECT id FROM vendedores WHERE gestor_id = ?)", [$this->gestorId]];
        }
        if ($this->perfil === 'vendedor' && $this->vendedorId) {
            return ["$coluna = ?", [$this->vendedorId]];
        }
        return ['1 = 0', []];
    }

    /**
     * Precisa da permissão "Aprovar e recusar" (padrão: admin e gestor). Gestor só decide os da própria
     * equipe; quem vê todos os vendedores (admin, e financeiro/operador se liberados) decide qualquer um.
     */
    public function podeDecidir(array $solicitacao): bool
    {
        if (!Permissoes::pode('credito.decidir')) {
            return false;
        }
        if ($this->veTudo()) {
            return true;
        }
        return $this->perfil === 'gestor' && $this->gestorId
            && (int) $solicitacao['gestor_atual_id'] === $this->gestorId;
    }

    /** Pode pedir crédito em nome deste vendedor? (permissão "Pedir uso de crédito" + equipe) */
    public function podeSolicitarPara(int $vendedorId): bool
    {
        if (!Permissoes::pode('credito.solicitar')) {
            return false;
        }
        if ($this->perfil === 'admin') {
            return true;
        }
        if ($this->perfil === 'operador' || $this->perfil === 'financeiro') {
            return (bool) Database::um('SELECT 1 FROM vendedores WHERE id = ? AND ativo = 1', [$vendedorId]);
        }
        if ($this->perfil === 'vendedor') {
            return $this->vendedorId === $vendedorId;
        }
        if ($this->perfil === 'gestor' && $this->gestorId) {
            return (bool) Database::um('SELECT 1 FROM vendedores WHERE id = ? AND gestor_id = ? AND ativo = 1',
                [$vendedorId, $this->gestorId]);
        }
        return false;
    }

    /**
     * Pode cancelar? O vendedor cancela os próprios pedidos pendentes ou aprovados ainda não usados;
     * o operador, os pedidos que ele mesmo fez; gestor/admin, os que podem decidir.
     */
    public function podeCancelar(array $s): bool
    {
        if (!in_array($s['status'], ['pendente', 'aprovada'], true)) {
            return false;
        }
        if ($this->perfil === 'vendedor') {
            return $this->vendedorId === (int) $s['vendedor_id'];
        }
        if ($this->perfil === 'operador') {
            return $this->usuarioId !== null && $this->usuarioId === (int) ($s['solicitado_por'] ?? 0);
        }
        return $this->podeDecidir($s);
    }

    public function semVinculo(): bool
    {
        return ($this->perfil === 'gestor' && !$this->gestorId)
            || ($this->perfil === 'vendedor' && !$this->vendedorId);
    }

    private function buscarId(string $sql, ?int $uid): ?int
    {
        if (!$uid) {
            return null;
        }
        $r = Database::um($sql, [$uid]);
        return $r ? (int) $r['id'] : null;
    }
}
