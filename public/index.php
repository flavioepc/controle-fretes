<?php
declare(strict_types=1);

/**
 * Ponto de entrada único. Todas as URLs passam por aqui (ver .htaccess).
 */
// servidor embutido do PHP (php -S): deixa ele entregar css/js/imagens
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\ContaController;
use App\Controllers\PortalController;
use App\Controllers\UsuarioController;
use App\Controllers\Fretes\DashboardController;
use App\Controllers\Fretes\FreteController;
use App\Controllers\Fretes\CreditoController;
use App\Controllers\Fretes\ExtratoController;
use App\Controllers\Fretes\FaturaController;
use App\Controllers\Fretes\CadastroController;
use App\Controllers\Fretes\ConfiguracaoController;
use App\Controllers\Fretes\CreditoExpiradoController;
use App\Controllers\Fretes\MovimentoController;
use App\Controllers\Fretes\PermissaoController;
use App\Core\Router;

$r = new Router();

// Portal
$r->get('/login', [AuthController::class, 'formulario']);
$r->post('/login', [AuthController::class, 'entrar']);
$r->post('/sair', [AuthController::class, 'sair'], ['auth']);
$r->get('/', [PortalController::class, 'inicio'], ['auth']);
$r->get('/trocar-senha', [ContaController::class, 'formularioSenha'], ['auth']);
$r->post('/trocar-senha', [ContaController::class, 'trocarSenha'], ['auth']);

// Usuários e acessos (administração do portal)
$adminPortal = ['auth', 'usuarios:admin'];
$r->get('/usuarios', [UsuarioController::class, 'index'], $adminPortal);
$r->post('/usuarios/salvar', [UsuarioController::class, 'salvar'], $adminPortal);
$r->post('/usuarios/alternar', [UsuarioController::class, 'alternar'], $adminPortal);
$r->post('/usuarios/redefinir-senha', [UsuarioController::class, 'redefinirSenha'], $adminPortal);

// Sistema de fretes  (middleware 'fretes' = precisa ter acesso a este sistema)
$r->get('/fretes', [DashboardController::class, 'index'], ['auth', 'fretes']);

// Daqui para baixo, quem pode o quê vem do painel de permissões ('pode:chave', ver App\Fretes\Permissoes;
// o padrão de cada perfil está em Permissoes::CATALOGO). Dashboard, uso de crédito e extrato abrem para
// todos os perfis — o que cada um enxerga é filtrado pelo Escopo.
$lancar = ['auth', 'pode:fretes.lancar'];
$r->get('/fretes/lancamentos', [FreteController::class, 'index'], $lancar);
$r->get('/fretes/lancamentos/novo', [FreteController::class, 'novo'], $lancar);
$r->get('/fretes/lancamentos/editar', [FreteController::class, 'editar'], $lancar);
$r->post('/fretes/lancamentos/salvar', [FreteController::class, 'salvar'], $lancar);
$r->post('/fretes/lancamentos/excluir', [FreteController::class, 'excluir'], ['auth', 'pode:fretes.excluir']);

// Uso de crédito: todos os perfis entram; o que cada um pode fazer é checado no controller (Escopo)
$r->get('/fretes/credito', [CreditoController::class, 'index'], ['auth', 'fretes']);
$r->post('/fretes/credito/solicitar', [CreditoController::class, 'solicitar'], ['auth', 'pode:credito.solicitar']);
$r->post('/fretes/credito/decidir', [CreditoController::class, 'decidir'], ['auth', 'pode:credito.decidir']);
$r->post('/fretes/credito/cancelar', [CreditoController::class, 'cancelar'], ['auth', 'pode:credito.solicitar|credito.decidir']);

// Extrato: fretes e movimentos numa linha do tempo, por vendedor
$r->get('/fretes/extrato', [ExtratoController::class, 'index'], ['auth', 'fretes']);

// Pagamento de saldo negativo e transferência de crédito entre vendedores (e o estorno dos dois)
$r->get('/fretes/movimentos', [MovimentoController::class, 'index'],
    ['auth', 'pode:movimentos.pagamento|movimentos.transferencia|movimentos.estorno']);
$r->post('/fretes/movimentos/pagamento', [MovimentoController::class, 'pagar'], ['auth', 'pode:movimentos.pagamento']);
$r->post('/fretes/movimentos/transferencia', [MovimentoController::class, 'transferir'], ['auth', 'pode:movimentos.transferencia']);
$r->post('/fretes/movimentos/estornar', [MovimentoController::class, 'estornar'], ['auth', 'pode:movimentos.estorno']);

// Faturas: conferência e baixa dos boletos das transportadoras
$imprimir = ['auth', 'pode:faturas.imprimir'];
$conferir = ['auth', 'pode:faturas.conferir'];
$corrigir = ['auth', 'pode:faturas.corrigir'];
$r->get('/fretes/faturas', [FaturaController::class, 'index'], ['auth', 'pode:faturas.ver']);
$r->get('/fretes/faturas/ver', [FaturaController::class, 'ver'], ['auth', 'pode:faturas.ver']);
$r->get('/fretes/faturas/imprimir', [FaturaController::class, 'imprimir'], $imprimir);  // operador imprime p/ o financeiro
$r->post('/fretes/faturas/impressa', [FaturaController::class, 'registrarImpressao'], $imprimir); // histórico
$r->get('/fretes/impressao',[FaturaController::class, 'impressao'], $imprimir);         // várias faturas de uma vez
$r->post('/fretes/faturas/conferir', [FaturaController::class, 'conferir'], $conferir);
$r->post('/fretes/faturas/enviar', [FaturaController::class, 'enviar'], $conferir);
$r->post('/fretes/faturas/pagar', [FaturaController::class, 'pagar'], $conferir);
$r->post('/fretes/faturas/alterar', [FaturaController::class, 'alterar'], $corrigir);  // corrige enviada/paga, com histórico
$r->post('/fretes/faturas/conhecimento', [FaturaController::class, 'trocarConhecimento'], $corrigir); // nº errado
$r->post('/fretes/faturas/retirar', [FaturaController::class, 'retirarFretes'], $corrigir);   // tira fretes do conhecimento

// Cadastros: empresas, transportadoras, gestores e vendedores
$admin = ['auth', 'pode:cadastros'];
$r->get('/fretes/cadastros', [CadastroController::class, 'index'], $admin);
$r->post('/fretes/cadastros/empresas/salvar', [CadastroController::class, 'salvarEmpresa'], $admin);
$r->post('/fretes/cadastros/empresas/alternar', [CadastroController::class, 'alternarEmpresa'], $admin);
$r->post('/fretes/cadastros/transportadoras/salvar', [CadastroController::class, 'salvarTransportadora'], $admin);
$r->post('/fretes/cadastros/transportadoras/alternar', [CadastroController::class, 'alternarTransportadora'], $admin);
$r->post('/fretes/cadastros/gestores/salvar', [CadastroController::class, 'salvarGestor'], $admin);
$r->post('/fretes/cadastros/gestores/alternar', [CadastroController::class, 'alternarGestor'], $admin);
$r->post('/fretes/cadastros/gestores/criar-acesso', [CadastroController::class, 'criarAcessoGestor'], $admin);
$r->post('/fretes/cadastros/vendedores/criar-acesso', [CadastroController::class, 'criarAcessoVendedor'], $admin);
$r->post('/fretes/cadastros/vendedores/salvar', [CadastroController::class, 'salvarVendedor'], $admin);
$r->post('/fretes/cadastros/vendedores/alternar', [CadastroController::class, 'alternarVendedor'], $admin);

// Configurações: chaves gerais do sistema de fretes
$r->get('/fretes/configuracoes', [ConfiguracaoController::class, 'index'], ['auth', 'pode:configuracoes']);
$r->post('/fretes/configuracoes/salvar', [ConfiguracaoController::class, 'salvar'], ['auth', 'pode:configuracoes']);

// Créditos expirados: consulta (padrão: admin, financeiro, gestor) e resgate/expiração manual (admin, financeiro)
$r->get('/fretes/creditos-expirados', [CreditoExpiradoController::class, 'index'], ['auth', 'pode:expirados.ver']);
$r->post('/fretes/creditos-expirados/resgatar', [CreditoExpiradoController::class, 'resgatar'], ['auth', 'pode:expirados.acoes']);
$r->post('/fretes/creditos-expirados/expirar', [CreditoExpiradoController::class, 'expirar'], ['auth', 'pode:expirados.acoes']);

// Permissões: o que cada perfil pode fazer (padrão: só admin, que nunca perde este acesso)
$r->get('/fretes/permissoes', [PermissaoController::class, 'index'], ['auth', 'pode:permissoes']);
$r->post('/fretes/permissoes/salvar', [PermissaoController::class, 'salvar'], ['auth', 'pode:permissoes']);
$r->post('/fretes/permissoes/restaurar', [PermissaoController::class, 'restaurar'], ['auth', 'pode:permissoes']);

try {
    $r->despachar($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    error_log((string) $e);                     // em produção o detalhe fica só no log do servidor
    if (config('debug') && !$e instanceof PDOException) {
        throw $e;                               // em desenvolvimento mostra o erro completo do PHP
    }
    http_response_code(500);
    $banco = $e instanceof PDOException;
    $msg = config('debug') ? $e->getMessage()
        : ($banco ? 'Não foi possível acessar o banco de dados.' : 'Ocorreu um erro inesperado.');
    echo '<h1 style="font-family:sans-serif">' . ($banco ? 'Erro de banco de dados' : 'Erro no sistema') . '</h1>'
        . '<p style="font-family:sans-serif">' . e($msg) . '</p>'
        . '<p style="font-family:sans-serif">Tente de novo em alguns minutos. Se continuar, avise o administrador.</p>';
}
