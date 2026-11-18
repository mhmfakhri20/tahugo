<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Support\Period;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters of a report export (REP-7): same query string as the report page (from, to, outlet).
 * Authorization is done in the controller with the same Gate/Policy as the page.
 */
class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'outlet' => ['nullable', 'integer', 'exists:locations,id'],
            'outletFilter' => ['nullable', 'integer', 'exists:locations,id'],
            'userFilter' => ['nullable', 'integer', 'exists:users,id'],
            'statusFilter' => ['nullable', 'in:late,early_leave,no_clock_out'],
        ];
    }

    public function period(): Period
    {
        $default = Period::lastDays(7);

        return new Period(
            $this->validated('from') ?? $default->from->toDateString(),
            $this->validated('to') ?? $default->to->toDateString(),
        );
    }

    public function outletId(): ?int
    {
        $id = $this->validated('outlet') ?? $this->validated('outletFilter');

        return $id !== null ? (int) $id : null;
    }

    /**
     * laporan-penjualan_FJI_2026-11-01_2026-11-14.xlsx
     */
    public function filename(string $report, string $extension = 'xlsx'): string
    {
        $period = $this->period();
        $code = $this->outletId() ? (Location::query()->whereKey($this->outletId())->value('code') ?? 'lokasi') : 'semua';

        return sprintf('laporan-%s_%s_%s_%s.%s', $report, $code, $period->from->toDateString(), $period->to->toDateString(), $extension);
    }
}
