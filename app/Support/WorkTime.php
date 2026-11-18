<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Compares a moment with an outlet's standard opening/closing time ("HH:MM", Asia/Jakarta) (ATT-5).
 * A null standard time means "no rule": no flag is given.
 */
final class WorkTime
{
    /**
     * Whole minutes $moment is after $standard on the same day, or null when on time / no standard.
     */
    public static function lateMinutes(?string $standard, DateTimeInterface $moment): ?int
    {
        if ($standard === null) {
            return null;
        }

        $moment = CarbonImmutable::instance($moment)->setTimezone(config('app.timezone'));
        $minutes = (int) floor($moment->setTimeFromTimeString($standard)->diffInMinutes($moment, false));

        return $minutes > 0 ? $minutes : null;
    }

    /**
     * Whole minutes $moment is before $standard on the same day, or null when not early / no standard.
     */
    public static function earlyMinutes(?string $standard, DateTimeInterface $moment): ?int
    {
        if ($standard === null) {
            return null;
        }

        $moment = CarbonImmutable::instance($moment)->setTimezone(config('app.timezone'));
        $minutes = (int) floor($moment->diffInMinutes($moment->setTimeFromTimeString($standard), false));

        return $minutes > 0 ? $minutes : null;
    }
}
