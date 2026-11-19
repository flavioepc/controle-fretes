<?php
declare(strict_types=1);

namespace App\Fretes;

/** Lê e valida os filtros da URL (?de=&ate=&empresa=&gestor=). */
final class Filtros
{
    public readonly string $de;
    public readonly string $ate;
    public readonly ?int $empresaId;
    public readonly ?int $gestorId;
    public readonly string $atalho;

    public const ATALHOS = [
        'mes'       => 'Este mês',
        'mes_ant'   => 'Mês anterior',
        '30d'       => 'Últimos 30 dias',
        '90d'       => 'Últimos 90 dias',
        'ano'       => 'Este ano',
    ];

    public function __construct(array $q, Escopo $escopo)
    {
        $hoje = new \DateTimeImmutable('today');
        $this->atalho = isset(self::ATALHOS[$q['periodo'] ?? '']) ? $q['periodo'] : '';

        [$de, $ate] = match ($this->atalho) {
            'mes_ant' => [$hoje->modify('first day of last month'), $hoje->modify('last day of last month')],
            '30d'     => [$hoje->modify('-29 days'), $hoje],
            '90d'     => [$hoje->modify('-89 days'), $hoje],
            'ano'     => [$hoje->setDate((int) $hoje->format('Y'), 1, 1), $hoje],
            'mes'     => [$hoje->modify('first day of this month'), $hoje],
            default   => [self::data($q['de'] ?? null) ?? $hoje->modify('first day of this month'),
                          self::data($q['ate'] ?? null) ?? $hoje],
        };
        if ($de > $ate) {
            [$de, $ate] = [$ate, $de];
        }
        $this->de = $de->format('Y-m-d');
        $this->ate = $ate->format('Y-m-d');

        $this->empresaId = self::id($q['empresa'] ?? null);
        // só admin/financeiro escolhem o gestor; os demais já têm escopo fixo
        $this->gestorId = $escopo->veTudo() ? self::id($q['gestor'] ?? null) : null;
    }

    public function dias(): int
    {
        return (int) (new \DateTime($this->de))->diff(new \DateTime($this->ate))->days + 1;
    }

    public function ativos(): bool
    {
        return $this->empresaId !== null || $this->gestorId !== null;
    }

    private static function data(?string $s): ?\DateTimeImmutable
    {
        if (!$s || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        return $d ?: null;
    }

    private static function id(mixed $v): ?int
    {
        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }
}
