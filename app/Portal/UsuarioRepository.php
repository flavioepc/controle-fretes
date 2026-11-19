<?php
declare(strict_types=1);

namespace App\Portal;

use App\Core\Database;

/** Usuários do portal e os aplicativos/perfis liberados para cada um (banco 'portal'). */
final class UsuarioRepository
{
    public function listar(): array
    {
        $usuarios = Database::todos(
            'SELECT id, nome, email, ativo, trocar_senha, ultimo_login FROM usuarios ORDER BY ativo DESC, nome',
            [], 'portal');
        $acessos = [];
        foreach (Database::todos(
            'SELECT us.usuario_id, s.slug, s.nome, us.perfil
               FROM usuario_sistema us JOIN sistemas s ON s.id = us.sistema_id
              ORDER BY s.ordem, s.nome', [], 'portal') as $a) {
            $acessos[(int) $a['usuario_id']][$a['slug']] = $a;
        }
        foreach ($usuarios as &$u) {
            $u['acessos'] = $acessos[(int) $u['id']] ?? [];
        }
        return $usuarios;
    }

    /** Aplicativos que podem ser liberados, com a lista de perfis de cada um. */
    public function sistemas(): array
    {
        $linhas = Database::todos(
            'SELECT id, slug, nome, perfis FROM sistemas WHERE ativo = 1 ORDER BY ordem, nome', [], 'portal');
        foreach ($linhas as &$s) {
            $s['perfis'] = array_values(array_filter(array_map('trim', explode(',', (string) $s['perfis']))));
        }
        return $linhas;
    }

    public function buscar(int $id): ?array
    {
        return Database::um('SELECT id, nome, email, ativo FROM usuarios WHERE id = ?', [$id], 'portal');
    }

    public function existeEmail(string $email, ?int $ignorarId): bool
    {
        return (bool) Database::um('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?',
            [$email, $ignorarId ?? 0], 'portal');
    }

    /**
     * Cria ou atualiza o usuário e grava os acessos.
     * $acessos: [sistema_id => perfil] (só os liberados). $senha: só na criação.
     */
    public function salvar(array $d, array $acessos, ?int $id, ?string $senha): int
    {
        $pdo = Database::get('portal');
        $pdo->beginTransaction();
        try {
            if ($id) {
                $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, ativo = ? WHERE id = ?')
                    ->execute([$d['nome'], $d['email'], $d['ativo'], $id]);
            } else {
                $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, ativo, trocar_senha) VALUES (?, ?, ?, ?, 1)')
                    ->execute([$d['nome'], $d['email'], password_hash((string) $senha, PASSWORD_DEFAULT), $d['ativo']]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM usuario_sistema WHERE usuario_id = ?')->execute([$id]);
            $ins = $pdo->prepare('INSERT INTO usuario_sistema (usuario_id, sistema_id, perfil) VALUES (?, ?, ?)');
            foreach ($acessos as $sistemaId => $perfil) {
                $ins->execute([$id, $sistemaId, $perfil]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $id;
    }

    /** Cria só o usuário (sem acessos), com senha provisória. Devolve o id. */
    public function criar(string $nome, string $email, string $senha): int
    {
        $pdo = Database::get('portal');
        $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, ativo, trocar_senha) VALUES (?, ?, ?, 1, 1)')
            ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
        return (int) $pdo->lastInsertId();
    }

    public function excluir(int $id): void
    {
        Database::get('portal')->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
    }

    /** Libera o sistema para o usuário com o perfil dado, se ele ainda não tiver acesso (não troca perfil existente). */
    public function garantirAcesso(int $usuarioId, string $slug, string $perfil): void
    {
        Database::get('portal')->prepare(
            'INSERT IGNORE INTO usuario_sistema (usuario_id, sistema_id, perfil)
             SELECT ?, id, ? FROM sistemas WHERE slug = ?'
        )->execute([$usuarioId, $perfil, $slug]);
    }

    public function alternar(int $id): void
    {
        Database::get('portal')->prepare('UPDATE usuarios SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
    }

    /** Senha provisória: o usuário é obrigado a trocar no próximo acesso. */
    public function redefinirSenha(int $id, string $senha): void
    {
        Database::get('portal')->prepare('UPDATE usuarios SET senha_hash = ?, trocar_senha = 1 WHERE id = ?')
            ->execute([password_hash($senha, PASSWORD_DEFAULT), $id]);
    }

    /** Quantos usuários ativos administram o portal, sem contar $ignorarId. */
    public function outrosAdmins(int $ignorarId): int
    {
        $r = Database::um(
            "SELECT COUNT(*) n FROM usuario_sistema us
               JOIN sistemas s ON s.id = us.sistema_id
               JOIN usuarios u ON u.id = us.usuario_id
              WHERE s.slug = 'usuarios' AND us.perfil = 'admin' AND u.ativo = 1 AND u.id <> ?",
            [$ignorarId], 'portal');
        return (int) ($r['n'] ?? 0);
    }
}
