<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Conexões PDO, uma por banco: Database::get('portal') / Database::get('fretes').
 * Sempre use prepared statements (->prepare + ->execute) com parâmetros.
 */
final class Database
{
    /** @var array<string, PDO> */
    private static array $conexoes = [];

    public static function get(string $nome = 'fretes'): PDO
    {
        if (!isset(self::$conexoes[$nome])) {
            $banco = config("db.bancos.$nome");
            if (!$banco) {
                throw new \InvalidArgumentException("Banco '$nome' não configurado");
            }
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                config('db.host'), config('db.porta'), $banco);

            self::$conexoes[$nome] = new PDO($dsn, config('db.usuario'), config('db.senha'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$conexoes[$nome]->exec("SET time_zone = '-03:00'");
        }
        return self::$conexoes[$nome];
    }

    /** Atalho: executa e devolve todas as linhas. */
    public static function todos(string $sql, array $params = [], string $banco = 'fretes'): array
    {
        $st = self::get($banco)->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Atalho: executa e devolve a primeira linha (ou null). */
    public static function um(string $sql, array $params = [], string $banco = 'fretes'): ?array
    {
        $st = self::get($banco)->prepare($sql);
        $st->execute($params);
        $linha = $st->fetch();
        return $linha === false ? null : $linha;
    }
}
