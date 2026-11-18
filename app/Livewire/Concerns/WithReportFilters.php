<?php

namespace App\Livewire\Concerns;

use App\Models\Location;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Period (WIB) + outlet filter shared by the report pages (F6). Defaults to the last 7 days.
 */
trait WithReportFilters
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(except: '')]
    public string $outlet = '';

    public function mountWithReportFilters(): void
    {
        $default = Period::lastDays(7);

        $this->from = $this->validDate($this->from) ?? $default->from->toDateString();
        $this->to = $this->validDate($this->to) ?? $default->to->toDateString();
    }

    #[Computed]
    public function period(): Period
    {
        return new Period(
            $this->validDate($this->from) ?? now()->toDateString(),
            $this->validDate($this->to) ?? now()->toDateString(),
        );
    }

    public function outletId(): ?int
    {
        return ctype_digit($this->outlet) ? (int) $this->outlet : null;
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function outletOptions(): Collection
    {
        return Location::query()->outlets()->orderBy('name')->get();
    }

    /**
     * Quick ranges for the filter bar.
     */
    public function setRange(string $range): void
    {
        $period = match ($range) {
            'today' => Period::day(now()),
            'month' => new Period(now()->startOfMonth(), now()),
            '30' => Period::lastDays(30),
            default => Period::lastDays(7),
        };

        $this->from = $period->from->toDateString();
        $this->to = $period->to->toDateString();
    }

    private function validDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
