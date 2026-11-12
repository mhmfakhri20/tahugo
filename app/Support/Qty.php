<?php

namespace App\Support;

/**
 * Exact arithmetic for DECIMAL(12,3) quantities (bcmath, scale 3). Never use float math on stock.
 */
final class Qty
{
    public const SCALE = 3;

    /**
     * Normalize any DB/user value ("20", "20.5", 20.5, null) to a "20.500" string.
     */
    public static function of(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.000';
        }

        if (is_float($value)) {
            return number_format($value, self::SCALE, '.', '');
        }

        $value = trim((string) $value);

        if (! is_numeric($value) || str_contains(strtolower($value), 'e')) {
            return number_format((float) $value, self::SCALE, '.', '');
        }

        // bcadd truncates, so round half away from zero at the 4th decimal first.
        $rounding = str_starts_with($value, '-') ? '-0.0005' : '0.0005';

        return bcadd(bcadd($value, $rounding, 4), '0', self::SCALE);
    }

    public static function add(int|float|string|null $a, int|float|string|null $b): string
    {
        return bcadd(self::of($a), self::of($b), self::SCALE);
    }

    public static function sub(int|float|string|null $a, int|float|string|null $b): string
    {
        return bcsub(self::of($a), self::of($b), self::SCALE);
    }

    public static function mul(int|float|string|null $a, int|float|string|null $b): string
    {
        return bcmul(self::of($a), self::of($b), self::SCALE);
    }

    public static function neg(int|float|string|null $a): string
    {
        return bcmul(self::of($a), '-1', self::SCALE);
    }

    public static function cmp(int|float|string|null $a, int|float|string|null $b): int
    {
        return bccomp(self::of($a), self::of($b), self::SCALE);
    }

    public static function isZero(int|float|string|null $a): bool
    {
        return self::cmp($a, 0) === 0;
    }

    public static function isNegative(int|float|string|null $a): bool
    {
        return self::cmp($a, 0) < 0;
    }

    public static function isPositive(int|float|string|null $a): bool
    {
        return self::cmp($a, 0) > 0;
    }

    /**
     * Rupiah total of qty × unit price, rounded to whole rupiah.
     */
    public static function money(int|float|string|null $qty, int $unitPrice): int
    {
        return (int) round((float) bcmul(self::of($qty), (string) $unitPrice, self::SCALE));
    }
}
