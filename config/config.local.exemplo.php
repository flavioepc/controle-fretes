<?php
/**
 * Modelo do config/config.local.php (o arquivo real fica fora do Git).
 * Copie para config.local.php e preencha só o que muda em relação ao config.php.
 *
 * No servidor de produção:  usuário próprio do MySQL (nunca root sem senha) e debug desligado.
 * No notebook (XAMPP):      basta ['debug' => true] para ver os erros completos na tela.
 */
return [
    'debug' => false,
    'db' => [
        'usuario' => 'fabreck_app',
        'senha'   => 'troque-por-uma-senha-forte',
    ],
];
