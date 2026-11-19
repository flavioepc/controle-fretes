<?php
declare(strict_types=1);

/**
 * Cria (ou reativa) um administrador da intranet: acesso admin ao sistema de fretes e à tela
 * "Usuários e acessos". Gera uma senha provisória, que a pessoa troca no primeiro acesso.
 * Use na instalação do servidor, quando ainda não existe nenhum admin para entrar pela tela.
 *
 * Uso:  php tools/criar_admin.php "Nome Completo" email@fabreck.com.br
 */
if (PHP_SAPI !== 'cli') {
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Portal\UsuarioRepository;

[$nome, $email] = [trim((string) ($argv[1] ?? '')), mb_strtolower(trim((string) ($argv[2] ?? '')))];
if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php tools/criar_admin.php \"Nome Completo\" email@fabreck.com.br\n");
    exit(1);
}

$usuarios = new UsuarioRepository();
$senha = senha_provisoria();
$existente = Database::um('SELECT id FROM usuarios WHERE email = ?', [$email], 'portal');
if ($existente) {
    $id = (int) $existente['id'];
    $usuarios->redefinirSenha($id, $senha);
    Database::get('portal')->prepare('UPDATE usuarios SET ativo = 1 WHERE id = ?')->execute([$id]);
} else {
    $id = $usuarios->criar($nome, $email, $senha);
}

// perfil admin mesmo que a pessoa já tivesse outro perfil nesses sistemas
Database::get('portal')->prepare(
    "INSERT INTO usuario_sistema (usuario_id, sistema_id, perfil)
     SELECT ?, id, 'admin' FROM sistemas WHERE slug IN ('fretes', 'usuarios')
     ON DUPLICATE KEY UPDATE perfil = 'admin'"
)->execute([$id]);
Auth::registrar(null, 'admin_criado_cli', $email);

echo ($existente ? 'Administrador reativado' : 'Administrador criado') . ": $email\n";
echo "Senha provisória: $senha  (o sistema pede uma nova senha no primeiro acesso)\n";
