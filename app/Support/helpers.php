<?php

if (! function_exists('rupiah')) {
    /**
     * Format an integer rupiah amount as "Rp4.850.000" (design system).
     */
    function rupiah(int|string|null $amount): string
    {
        return 'Rp'.number_format((int) $amount, 0, ',', '.');
    }
}

if (! function_exists('qty')) {
    /**
     * Format a DECIMAL(12,3) quantity without trailing zeros, Indonesian style ("1,5", "1.200").
     */
    function qty(int|float|string|null $value): string
    {
        $formatted = number_format((float) $value, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
