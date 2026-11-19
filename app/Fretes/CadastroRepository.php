<?php
declare(strict_types=1);

namespace App\Fretes;

use App\Core\Database;

/**
 * Cadastros administrativos: empresas, transportadoras, gestores e vendedores.
 * Sem exclusão: registros em uso por fretes/faturas/créditos só são inativados (ativo=0).
 */
final class CadastroRepository
{
    // ------------------------------------------------------------ empresas

    public function listarEmpresas(): array
    {
        return Database::todos('SELECT * FROM empresas ORDER BY ativo DESC, nome');
    }

    public function existeNomeEmpresa(string $nome, ?int $ignorarId): bool
    {
        return (bool) Database::um('SELECT 1 FROM empresas WHERE nome = ? AND id <> ?', [$nome, $ignorarId ?? 0]);
    }

    public function salvarEmpresa(array $d, ?int $id): void
    {
        if ($id) {
            Database::get()->prepare('UPDATE empresas SET nome = ?, cnpj = ? WHERE id = ?')
                ->execute([$d['nome'], $d['cnpj'], $id]);
        } else {
            Database::get()->prepare('INSERT INTO empresas (nome, cnpj) VALUES (?, ?)')
                ->execute([$d['nome'], $d['cnpj']]);
        }
    }

    public function alternarEmpresa(int $id): void
    {
        Database::get()->prepare('UPDATE empresas SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
    }

    // ------------------------------------------------------------ transportadoras (mesmo formato de empresas)

    public function listarTransportadoras(): array
    {
        return Database::todos('SELECT * FROM transportadoras ORDER BY ativo DESC, nome');
    }

    public function existeNomeTransportadora(string $nome, ?int $ignorarId): bool
    {
        return (bool) Database::um(
            'SELECT 1 FROM transportadoras WHERE nome = ? AND id <> ?', [$nome, $ignorarId ?? 0]);
    }

    public function salvarTransportadora(array $d, ?int $id): void
    {
        if ($id) {
            Database::get()->prepare('UPDATE transportadoras SET nome = ?, cnpj = ? WHERE id = ?')
                ->execute([$d['nome'], $d['cnpj'], $id]);
        } else {
            Database::get()->prepare('INSERT INTO transportadoras (nome, cnpj) VALUES (?, ?)')
                ->execute([$d['nome'], $d['cnpj']]);
        }
    }

    public function alternarTransportadora(int $id): void
    {
        Database::get()->prepare('UPDATE transportadoras SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
    }

    // ------------------------------------------------------------ gestores

    public function listarGestores(): array
    {
        $linhas = Database::todos(
            "SELECT g.*, (SELECT COUNT(*) FROM vendedores v WHERE v.gestor_id = g.id AND v.ativo = 1) qtd_vendedores
               FROM gestores g ORDER BY g.ativo DESC, g.nome");
        return $this->comUsuario($linhas);
    }

    public function existeNomeGestor(string $nome, ?int $ignorarId): bool
    {
        return (bool) Database::um('SELECT 1 FROM gestores WHERE nome = ? AND id <> ?', [$nome, $ignorarId ?? 0]);
    }

    public function salvarGestor(array $d, ?int $id): void
    {
        if ($id) {
            Database::get()->prepare('UPDATE gestores SET nome = ?, usuario_id = ? WHERE id = ?')
                ->execute([$d['nome'], $d['usuario_id'], $id]);
        } else {
            Database::get()->prepare('INSERT INTO gestores (nome, usuario_id) VALUES (?, ?)')
                ->execute([$d['nome'], $d['usuario_id']]);
        }
    }

    public function alternarGestor(int $id): void
    {
        Database::get()->prepare('UPDATE gestores SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
    }

    // ------------------------------------------------------------ vendedores

    public function listarVendedores(): array
    {
        $linhas = Database::todos(
            'SELECT v.*, g.nome gestor FROM vendedores v JOIN gestores g ON g.id = v.gestor_id
              ORDER BY v.ativo DESC, g.nome, v.nome');
        return $this->comUsuario($linhas);
    }

    public function existeNomeVendedor(string $nome, ?int $ignorarId): bool
    {
        return (bool) Database::um('SELECT 1 FROM vendedores WHERE nome = ? AND id <> ?', [$nome, $ignorarId ?? 0]);
    }

    public function salvarVendedor(array $d, ?int $id): void
    {
        if ($id) {
            Database::get()->prepare(
                'UPDATE vendedores SET nome = ?, gestor_id = ?, usuario_id = ?, credito_expira = ? WHERE id = ?')
                ->execute([$d['nome'], $d['gestor_id'], $d['usuario_id'], $d['credito_expira'], $id]);
        } else {
            Database::get()->prepare(
                'INSERT INTO vendedores (nome, gestor_id, usuario_id, credito_expira) VALUES (?, ?, ?, ?)')
                ->execute([$d['nome'], $d['gestor_id'], $d['usuario_id'], $d['credito_expira']]);
        }
    }

    public function alternarVendedor(int $id): void
    {
        Database::get()->prepare('UPDATE vendedores SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
    }

    // ------------------------------------------------------------ apoio

    public function gestoresAtivos(): array
    {
        return Database::todos('SELECT id, nome FROM gestores WHERE ativo = 1 ORDER BY nome');
    }

    /** Usuários do portal, para vincular o login de um gestor/vendedor (opcional). */
    public function usuariosPortal(): array
    {
        return Database::todos('SELECT id, nome, email FROM usuarios WHERE ativo = 1 ORDER BY nome', [], 'portal');
    }

    public function usuarioExiste(int $id): bool
    {
        return (bool) Database::um('SELECT 1 FROM usuarios WHERE id = ? AND ativo = 1', [$id], 'portal');
    }

    /** Gestor ou vendedor ('gestores'/'vendedores') com o usuário vinculado, ou null. */
    public function buscarPessoa(string $tabela, int $id): ?array
    {
        if (!in_array($tabela, ['gestores', 'vendedores'], true)) {
            throw new \InvalidArgumentException($tabela);
        }
        return Database::um("SELECT id, nome, usuario_id FROM $tabela WHERE id = ?", [$id]);
    }

    public function vincularUsuario(string $tabela, int $id, int $usuarioId): void
    {
        if (!in_array($tabela, ['gestores', 'vendedores'], true)) {
            throw new \InvalidArgumentException($tabela);
        }
        Database::get()->prepare("UPDATE $tabela SET usuario_id = ? WHERE id = ?")->execute([$usuarioId, $id]);
    }

    public function existeId(string $tabela, ?int $id, bool $somenteAtivo = false): bool
    {
        if (!$id) {
            return false;
        }
        $permitidas = ['empresas', 'transportadoras', 'gestores', 'vendedores'];
        if (!in_array($tabela, $permitidas, true)) {
            throw new \InvalidArgumentException($tabela);
        }
        $sql = "SELECT 1 FROM $tabela WHERE id = ?" . ($somenteAtivo ? ' AND ativo = 1' : '');
        return (bool) Database::um($sql, [$id]);
    }

    /** Junta nome/e-mail do usuário do portal (banco separado) nas linhas que têm usuario_id. */
    private function comUsuario(array $linhas): array
    {
        $ids = array_values(array_unique(array_filter(array_column($linhas, 'usuario_id'))));
        $porId = [];
        if ($ids) {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $usuarios = Database::todos("SELECT id, nome, email, ativo FROM usuarios WHERE id IN ($marcas)", $ids, 'portal');
            $porId = array_column($usuarios, null, 'id');
        }
        foreach ($linhas as &$l) {
            $u = $porId[$l['usuario_id']] ?? null;
            $l['usuario_nome'] = $u['nome'] ?? null;
            $l['usuario_email'] = $u['email'] ?? null;
            $l['usuario_ativo'] = $u ? (bool) $u['ativo'] : null;
        }
        return $linhas;
    }
}
