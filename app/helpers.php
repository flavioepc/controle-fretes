<?php
declare(strict_types=1);

/** Lê config com notação de ponto: config('db.host') */
function config(string $chave, mixed $padrao = null): mixed
{
    $valor = $GLOBALS['config'];
    foreach (explode('.', $chave) as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            return $padrao;
        }
        $valor = $valor[$parte];
    }
    return $valor;
}

/** Escapa para HTML — use em TODA saída de dado. */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $caminho = ''): string
{
    return BASE_URL . '/' . ltrim($caminho, '/');
}

/** Link do card de um sistema: apps externos (ex.: /WebFolha/) usam a URL como está. */
function url_sistema(array $sistema): string
{
    return !empty($sistema['externo']) ? (string) $sistema['url'] : url((string) $sistema['url']);
}

/** Senha provisória de 10 caracteres fáceis de ditar (sem 0/O, 1/l/I), sempre com letras e números. */
function senha_provisoria(): string
{
    $letras = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $numeros = '23456789';
    $s = '';
    for ($i = 0; $i < 8; $i++) {
        $s .= $letras[random_int(0, strlen($letras) - 1)];
    }
    return $s . $numeros[random_int(0, 7)] . $numeros[random_int(0, 7)];
}

/**
 * A requisição chegou por HTTPS? Atrás do Cloudflare Tunnel ou de outro proxy, o PHP recebe HTTP comum
 * e o HTTPS original só aparece no cabeçalho X-Forwarded-Proto.
 */
function https_ativo(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function asset(string $caminho): string
{
    $arquivo = RAIZ . '/public/assets/' . ltrim($caminho, '/');
    $versao = is_file($arquivo) ? filemtime($arquivo) : 0;   // evita cache velho
    return url('assets/' . ltrim($caminho, '/')) . '?v=' . $versao;
}

function redirecionar(string $caminho): never
{
    header('Location: ' . url($caminho));
    exit;
}

/** 1234.5 -> "R$ 1.234,50" */
function moeda(float|int|string|null $valor, bool $simbolo = true): string
{
    $n = (float) $valor;
    $v = number_format(abs($n), 2, ',', '.');
    $sinal = $n < -0.004 ? '-' : '';
    return $sinal . ($simbolo ? 'R$ ' : '') . $v;
}

/** '2026-10-27' -> '27/10/2026' */
function data_br(?string $data): string
{
    if (!$data) {
        return '—';
    }
    $d = DateTime::createFromFormat('Y-m-d', substr($data, 0, 10));
    return $d ? $d->format('d/m/Y') : $data;
}

/** Mensagem de uma requisição para a próxima (ex.: após redirecionar). */
function flash(string $tipo, ?string $mensagem = null): ?string
{
    if ($mensagem !== null) {
        $_SESSION['_flash'][$tipo] = $mensagem;
        return null;
    }
    $m = $_SESSION['_flash'][$tipo] ?? null;
    unset($_SESSION['_flash'][$tipo]);
    return $m;
}

/** Classe de cor para valores com sinal (positivo / negativo). */
function classe_sinal(float|string|null $valor): string
{
    $v = (float) $valor;
    return $v > 0.004 ? 'valor-positivo' : ($v < -0.004 ? 'valor-negativo' : 'text-body-secondary');
}

/** '12345678000190' -> '12.345.678/0001-90' (null/vazio -> '—') */
function cnpj_br(?string $cnpj): string
{
    if (!$cnpj) {
        return '—';
    }
    return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cnpj) ?? $cnpj;
}

function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $ini = mb_substr($partes[0] ?? '', 0, 1) . mb_substr(count($partes) > 1 ? end($partes) : '', 0, 1);
    return mb_strtoupper($ini);
}

/**
 * Converte o que o usuário digitou em número: "1.234,56" -> 1234.56 ; "93,74" -> 93.74 ; "" -> null
 */
function ler_moeda(mixed $texto): ?float
{
    $t = trim((string) $texto);
    if ($t === '') {
        return null;
    }
    $t = str_replace(['R$', ' ', "\u{00A0}"], '', $t);
    if (str_contains($t, ',')) {                  // formato brasileiro
        $t = str_replace('.', '', $t);
        $t = str_replace(',', '.', $t);
    }
    return is_numeric($t) ? round((float) $t, 2) : NAN;
}

/** Valor para preencher campo de formulário: 1234.5 -> "1.234,50" (null -> "") */
function campo_moeda(mixed $valor): string
{
    return $valor === null || $valor === '' ? '' : number_format((float) $valor, 2, ',', '.');
}

/** Dados do formulário enviados antes de um erro (para não perder o que foi digitado). */
function antigo(string $campo, mixed $padrao = ''): mixed
{
    return $_SESSION['_antigo'][$campo] ?? $padrao;
}

function limpar_antigo(): void
{
    unset($_SESSION['_antigo']);
}

/** Mantém os filtros atuais da URL trocando só alguns parâmetros. */
function url_com(array $trocar, string $caminho): string
{
    $q = array_filter(array_merge($_GET, $trocar), fn ($v) => $v !== null && $v !== '');
    return url($caminho) . ($q ? '?' . http_build_query($q) : '');
}

const UFS = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE',
             'PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];
