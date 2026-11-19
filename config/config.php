<?php
/**
 * Configuração padrão. NÃO coloque senhas reais aqui:
 * crie config/config.local.php (fica fora do Git) retornando só o que muda.
 */
$config = [
    'app_nome' => 'Intranet Fabreck',
    'debug'    => false,                // no notebook, ligue em config.local.php: ['debug' => true]
    'timezone' => 'America/Sao_Paulo',

    'db' => [
        'host'    => '127.0.0.1',
        'porta'   => 3306,
        'usuario' => 'root',
        'senha'   => '',                // padrão do XAMPP
        'bancos'  => [
            'portal' => 'fabreck_portal',
            'fretes' => 'fabreck_fretes',
        ],
    ],

    'sessao' => [
        'nome'          => 'FABRECK_SESS',
        'expira_minutos'=> 120,         // inatividade
    ],

    'login' => [
        'max_tentativas' => 5,          // por e-mail
        'janela_minutos' => 15,
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}
return $config;
