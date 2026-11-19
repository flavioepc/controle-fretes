<?php
declare(strict_types=1);

namespace App\Controllers\Fretes;

use App\Core\Auth;
use App\Core\View;
use App\Fretes\FreteRepository;
use App\Fretes\Permissoes;

/**
 * Lançamento, edição, listagem e exclusão de fretes (admin, financeiro e operador; excluir só admin).
 * Cobrança do cliente: "cliente" (a diferença vai para o saldo do vendedor; 0 = frete grátis por conta
 * dele) ou "isento" (a empresa assume pelo motivo escolhido; não mexe no saldo).
 */
final class FreteController
{
    private FreteRepository $repo;

    public function __construct()
    {
        $this->repo = new FreteRepository();
    }

    public function index(): void
    {
        $hoje = new \DateTimeImmutable('today');
        $f = [
            'de'  => $this->data($_GET['de'] ?? null) ?? $hoje->modify('first day of this month')->format('Y-m-d'),
            'ate' => $this->data($_GET['ate'] ?? null) ?? $hoje->format('Y-m-d'),
            'vendedor' => (int) ($_GET['vendedor'] ?? 0) ?: null,
            'transportadora' => (int) ($_GET['transportadora'] ?? 0) ?: null,
            'empresa' => (int) ($_GET['empresa'] ?? 0) ?: null,
            'busca' => trim((string) ($_GET['busca'] ?? '')),
            'situacao' => (string) ($_GET['situacao'] ?? ''),
        ];
        if ($f['de'] > $f['ate']) {
            [$f['de'], $f['ate']] = [$f['ate'], $f['de']];
        }
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $res = $this->repo->listar($f, $pagina);

        View::render('fretes/lista', [
            'titulo' => 'Fretes', 'secao' => 'fretes', 'pagina' => 'fretes',
            'f' => $f, 'res' => $res, 'paginaAtual' => $pagina,
            'paginas' => max(1, (int) ceil($res['total'] / FreteRepository::POR_PAGINA)),
            'opcoes' => $this->repo->opcoes(),
            'scripts' => [asset('vendor/tom-select/tom-select.complete.min.js'), asset('js/selects.js')],
            'estilos' => [asset('vendor/tom-select/tom-select.bootstrap5.min.css')],
        ]);
    }

    public function novo(): void
    {
        $this->formulario(null);
    }

    public function editar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $frete = $this->repo->buscar($id);
        if (!$frete) {
            flash('erro', 'Frete não encontrado.');
            redirecionar('fretes/lancamentos');
        }
        $this->formulario($frete);
    }

    private function formulario(?array $frete): void
    {
        $id = $frete ? (int) $frete['id'] : null;
        View::render('fretes/form', [
            'titulo' => $id ? 'Editar frete' : 'Lançar frete', 'secao' => 'fretes', 'pagina' => 'fretes',
            'frete' => $frete, 'erros' => $_SESSION['_erros'] ?? [],
            'duplicadas' => $_SESSION['_duplicadas'] ?? [],
            'opcoes' => $this->repo->opcoes(),
            'solicitacoes' => $this->repo->solicitacoesDisponiveis($frete['solicitacao_id'] ?? null),
            'scripts' => [asset('vendor/tom-select/tom-select.complete.min.js'), asset('js/selects.js'),
                          asset('js/frete-form.js')],
            'estilos' => [asset('vendor/tom-select/tom-select.bootstrap5.min.css')],
        ]);
        unset($_SESSION['_erros'], $_SESSION['_duplicadas']);
        limpar_antigo();
    }

    public function salvar(): void
    {
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        $atual = $id ? $this->repo->buscar($id) : null;
        if ($id && !$atual) {
            flash('erro', 'Frete não encontrado.');
            redirecionar('fretes/lancamentos');
        }

        [$dados, $erros] = $this->validar($_POST, $atual);

        // NF repetida: avisa e pede confirmação (pode ser legítimo, ex.: 2 volumes).
        // Numa edição, só confere de novo se a nota fiscal foi alterada.
        $nfMudou = !$atual || $atual['nota_fiscal'] !== $dados['nota_fiscal'];
        $duplicadas = ($erros || !$nfMudou) ? [] : $this->repo->mesmaNota($dados['nota_fiscal'], $id);
        if ($duplicadas && empty($_POST['confirmar_duplicada'])) {
            $_SESSION['_duplicadas'] = $duplicadas;
            $erros['nota_fiscal'] = 'Já existe frete com esta nota fiscal. Confira e marque a confirmação para salvar mesmo assim.';
        }

        if ($erros) {
            $_SESSION['_erros'] = $erros;
            $_SESSION['_antigo'] = $_POST;
            flash('erro', 'Corrija os campos destacados.');
            redirecionar($id ? "fretes/lancamentos/editar?id=$id" : 'fretes/lancamentos/novo');
        }

        $novoId = $this->repo->salvar($dados, $id, (int) Auth::id());
        flash('sucesso', $id ? 'Frete atualizado.' : 'Frete lançado com sucesso.');

        if (!$id && !empty($_POST['continuar'])) {
            // mantém data/empresa/transportadora/documento para lançar o próximo item da mesma fatura
            $_SESSION['_antigo'] = array_intersect_key($_POST,
                array_flip(['data', 'empresa_id', 'transportadora_id', 'documento', 'uf']));
            redirecionar('fretes/lancamentos/novo');
        }
        redirecionar('fretes/lancamentos?busca=' . rawurlencode($dados['nota_fiscal']) . '&de=' . $dados['data']
            . '&ate=' . $dados['data'] . '#f' . $novoId);
    }

    public function excluir(): void
    {
        Permissoes::exigir('fretes.excluir');
        $id = (int) ($_POST['id'] ?? 0);
        if ($this->repo->buscar($id)) {
            $this->repo->excluir($id);
            flash('sucesso', 'Frete excluído.');
        }
        redirecionar('fretes/lancamentos');
    }

    // ------------------------------------------------------------------

    /** @return array{0: array, 1: array<string,string>} */
    private function validar(array $in, ?array $atual): array
    {
        $id = $atual ? (int) $atual['id'] : null;
        $e = [];
        $isento = ($in['cobranca'] ?? '') === 'isento';
        $d = [
            'data' => $this->data($in['data'] ?? null),
            'nota_fiscal' => mb_strtoupper(trim((string) ($in['nota_fiscal'] ?? ''))),
            'empresa_id' => (int) ($in['empresa_id'] ?? 0) ?: null,
            'transportadora_id' => (int) ($in['transportadora_id'] ?? 0) ?: null,
            'vendedor_id' => (int) ($in['vendedor_id'] ?? 0) ?: null,
            'uf' => strtoupper(trim((string) ($in['uf'] ?? ''))) ?: null,
            'valor_cliente' => ler_moeda($in['valor_cliente'] ?? ''),
            'valor_transp' => ler_moeda($in['valor_transp'] ?? ''),
            'isencao_id' => $isento ? ((int) ($in['isencao_id'] ?? 0) ?: null) : null,
            'valor_pedido' => ler_moeda($in['valor_pedido'] ?? ''),
            'numero_pedido' => mb_strtoupper(trim((string) ($in['numero_pedido'] ?? ''))) ?: null,
            'documento' => trim((string) ($in['documento'] ?? '')),
            'solicitacao_id' => (int) ($in['solicitacao_id'] ?? 0) ?: null,
            'obs' => trim((string) ($in['obs'] ?? '')) ?: null,
        ];

        if (!$d['data']) {
            $e['data'] = 'Informe uma data válida.';
        } elseif ($d['data'] > date('Y-m-d', strtotime('+7 days'))) {
            $e['data'] = 'Data no futuro. Confira.';
        }
        if ($d['nota_fiscal'] === '' || mb_strlen($d['nota_fiscal']) > 60) {
            $e['nota_fiscal'] = 'Informe a nota fiscal (até 60 caracteres).';
        }
        if (!$this->repo->existe('empresas', $d['empresa_id'])) {
            $e['empresa_id'] = 'Escolha a empresa.';
        }
        if (!$this->repo->existe('transportadoras', $d['transportadora_id'])) {
            $e['transportadora_id'] = 'Escolha a transportadora.';
        }
        if (!$this->repo->existe('vendedores', $d['vendedor_id'])) {
            $e['vendedor_id'] = 'Escolha o vendedor.';
        }
        if ($d['uf'] !== null && !in_array($d['uf'], UFS, true)) {
            $e['uf'] = 'UF inválida.';
        }
        if ($d['valor_cliente'] === null || $isento) {
            $d['valor_cliente'] = 0.0;      // frete grátis do vendedor, ou isento (a empresa assume)
        }
        if (is_nan($d['valor_cliente']) || $d['valor_cliente'] < 0) {
            $e['valor_cliente'] = 'Valor inválido.';
        }
        if ($d['valor_pedido'] !== null && (is_nan($d['valor_pedido']) || $d['valor_pedido'] < 0)) {
            $e['valor_pedido'] = 'Valor inválido.';
        }
        if ($d['numero_pedido'] !== null && mb_strlen($d['numero_pedido']) > 30) {
            $e['numero_pedido'] = 'Até 30 caracteres.';
        }
        if ($isento) {
            $motivo = $d['isencao_id'] ? $this->repo->motivoIsencao($d['isencao_id']) : null;
            $mesmoMotivo = $atual && (int) $atual['isencao_id'] === $d['isencao_id'];
            if (!$motivo || (!$motivo['ativo'] && !$mesmoMotivo)) {
                $e['isencao_id'] = 'Escolha o motivo da isenção.';
            } elseif ($motivo['exige_valor_pedido'] && !isset($e['valor_pedido'])) {
                // a regra vale para lançamentos novos e para quem troca o motivo; o histórico importado não tem
                // o nº nem o valor do pedido (ambos vêm do ERP)
                if ($d['numero_pedido'] === null && !$mesmoMotivo && !isset($e['numero_pedido'])) {
                    $e['numero_pedido'] = 'Informe o nº do pedido no ERP.';
                }
                $minimo = $this->repo->pedidoMinimoIsencao();
                if ($d['valor_pedido'] === null && !$mesmoMotivo) {
                    $e['valor_pedido'] = 'Informe o valor do pedido (mínimo ' . moeda($minimo) . ').';
                } elseif ($d['valor_pedido'] !== null && $d['valor_pedido'] < $minimo) {
                    $e['valor_pedido'] = 'Pedido abaixo de ' . moeda($minimo) . ': não se enquadra na isenção.';
                }
            }
            if ($d['solicitacao_id']) {
                $e['solicitacao_id'] = 'Frete isento não usa crédito do vendedor. Deixe "Não".';
            }
        }
        if ($d['valor_transp'] !== null && (is_nan($d['valor_transp']) || $d['valor_transp'] < 0)) {
            $e['valor_transp'] = 'Valor inválido.';
        }
        if ($d['documento'] !== '' && $d['valor_transp'] === null) {
            $e['valor_transp'] = 'Com nº de documento, informe o valor cobrado pela transportadora.';
        }
        if (mb_strlen($d['documento']) > 40) {
            $e['documento'] = 'Até 40 caracteres.';
        }
        if ($d['solicitacao_id'] && !isset($e['vendedor_id'])) {
            if ($msg = $this->repo->solicitacaoValida($d['solicitacao_id'], $d['vendedor_id'], $id)) {
                $e['solicitacao_id'] = $msg;
            }
        }
        if ($d['obs'] !== null && mb_strlen($d['obs']) > 255) {
            $e['obs'] = 'Até 255 caracteres.';
        }
        return [$d, $e];
    }

    private function data(?string $s): ?string
    {
        if (!$s || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }
        [$a, $m, $d] = array_map('intval', explode('-', $s));
        return checkdate($m, $d, $a) ? $s : null;
    }
}
