<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A closed date range in Asia/Jakarta (whole days). Timestamps are stored in the app timezone,
 * so `whereBetween(created_at, [from, to])` and `DATE(created_at)` follow the WIB calendar.
 */
final class Period
{
    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    public function __construct(\DateTimeInterface|string $from, \DateTimeInterface|string $to)
    {
        $tz = config('app.timezone');
        $a = CarbonImmutable::parse($from, $tz)->setTimezone($tz)->startOfDay();
        $b = CarbonImmutable::parse($to, $tz)->setTimezone($tz)->endOfDay();

        [$this->from, $this->to] = $a->lte($b) ? [$a, $b] : [$b->startOfDay(), $a->endOfDay()];
    }

    public static function day(\DateTimeInterface|string $date): self
    {
        return new self($date, $date);
    }

    /**
     * The last $days days ending today (inclusive).
     */
    public static function lastDays(int $days, ?\DateTimeInterface $end = null): self
    {
        $end = CarbonImmutable::instance($end ?? now());

        return new self($end->subDays($days - 1), $end);
    }

    /**
     * Same length, right before this one.
     */
    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->subDays($days), $this->from->subDay());
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * @return list<string> Y-m-d of every day in the period
     */
    public function dates(): array
    {
        $dates = [];

        for ($day = $this->from; $day->lte($this->to); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bounds(): array
    {
        return [$this->from, $this->to];
    }

    public function label(): string
    {
        return $this->from->isSameDay($this->to)
            ? $this->from->translatedFormat('d M Y')
            : $this->from->translatedFormat('d M').' – '.$this->to->translatedFormat('d M Y');
    }
}
