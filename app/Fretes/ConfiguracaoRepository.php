<?php
declare(strict_types=1);

namespace App\Fretes;

use App\Core\Database;

/**
 * Chaves gerais do sistema de fretes (tabela `configuracoes`).
 * Hoje: credito_expira_virada_ano, alerta_vencimento_dias e frete_isento_pedido_minimo.
 */
final class ConfiguracaoRepository
{
    /** @return array<string, array> linhas indexadas por chave, com o nome de quem atualizou por último */
    public function todas(): array
    {
        $linhas = Database::todos('SELECT * FROM configuracoes ORDER BY chave');

        $ids = array_values(array_unique(array_filter(array_column($linhas, 'atualizado_por'))));
        $nomePorId = [];
        if ($ids) {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $usuarios = Database::todos("SELECT id, nome FROM usuarios WHERE id IN ($marcas)", $ids, 'portal');
            $nomePorId = array_column($usuarios, 'nome', 'id');
        }
        foreach ($linhas as &$l) {
            $l['atualizado_por_nome'] = $nomePorId[$l['atualizado_por']] ?? null;
        }
        return array_column($linhas, null, 'chave');
    }

    public function salvar(string $chave, string $valor, int $usuarioId): void
    {
        Database::get()->prepare('UPDATE configuracoes SET valor = ?, atualizado_por = ? WHERE chave = ?')
            ->execute([$valor, $usuarioId, $chave]);
    }
}
