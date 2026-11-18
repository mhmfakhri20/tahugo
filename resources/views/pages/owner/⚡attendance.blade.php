<?php

use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Rekap absensi pemilik (ATT-7): per outlet/karyawan/periode, foto (bisa diperbesar), terlambat/pulang awal,
 * jarak & akurasi, tautan peta; tab Shift berisi ringkasan shift per hari (jam aktual, kas, selisih).
 */
new #[Title('Absensi')] class extends Component {
    use WithPagination;

    #[Url(except: 'attendance')]
    public string $tab = 'attendance';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(except: '')]
    public string $outletFilter = '';

    #[Url(except: '')]
    public string $userFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Attendance::class);

        $this->from = $this->validDate($this->from) ?? today()->toDateString();
        $this->to = $this->validDate($this->to) ?? today()->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'outletFilter', 'userFilter', 'statusFilter', 'tab'], true)) {
            $this->resetPage();
        }
    }

    private function validDate(string $value): ?string
    {
        try {
            return $value !== '' ? CarbonImmutable::createFromFormat('Y-m-d', $value)->toDateString() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(): array
    {
        $from = CarbonImmutable::parse($this->validDate($this->from) ?? today()->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($this->validDate($this->to) ?? today()->toDateString())->endOfDay();

        return $from->lte($to) ? [$from, $to] : [$to->startOfDay(), $from->endOfDay()];
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function outlets(): Collection
    {
        return Location::query()->outlets()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function staff(): Collection
    {
        return User::query()
            ->where('role', Role::OutletStaff)
            ->when($this->outletFilter !== '', fn ($q) => $q->where('location_id', $this->outletFilter))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function today(): \Illuminate\Support\Collection
    {
        $outlets = $this->outlets->where('is_active', true)
            ->when($this->outletFilter !== '', fn ($c) => $c->where('id', (int) $this->outletFilter));

        return app(AttendanceService::class)->todaySummary($outlets->values());
    }

    /**
     * Clock-ins in the period (one row per person per day); the matching clock-out is attached below.
     */
    private function clockInQuery(): Builder
    {
        [$from, $to] = $this->range();

        return Attendance::query()
            ->where('type', AttendanceType::ClockIn)
            ->whereBetween('server_time', [$from, $to])
            ->when($this->outletFilter !== '', fn ($q) => $q->where('location_id', $this->outletFilter))
            ->when($this->userFilter !== '', fn ($q) => $q->where('user_id', $this->userFilter));
    }

    /**
     * @return array{total: int, late: int, early: int}
     */
    #[Computed]
    public function metrics(): array
    {
        return [
            'total' => $this->clockInQuery()->count(),
            'late' => $this->clockInQuery()->where('flags->late', true)->count(),
            'early' => $this->clockInQuery()->whereExists($this->clockOutOfSameDay(earlyOnly: true))->count(),
        ];
    }

    private function clockOutOfSameDay(bool $earlyOnly = false): \Closure
    {
        return function (QueryBuilder $query) use ($earlyOnly): void {
            $query->select(DB::raw(1))
                ->from('attendances as co')
                ->whereColumn('co.user_id', 'attendances.user_id')
                ->where('co.type', AttendanceType::ClockOut->value)
                ->whereRaw('DATE(co.server_time) = DATE(attendances.server_time)')
                ->when($earlyOnly, fn ($q) => $q->where('co.flags->early_leave', true));
        };
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $page = $this->clockInQuery()
            ->with(['user', 'location'])
            ->when($this->statusFilter === 'late', fn ($q) => $q->where('flags->late', true))
            ->when($this->statusFilter === 'early_leave', fn ($q) => $q->whereExists($this->clockOutOfSameDay(earlyOnly: true)))
            ->when($this->statusFilter === 'no_clock_out', fn ($q) => $q->whereNotExists($this->clockOutOfSameDay()))
            ->orderByDesc('server_time')
            ->paginate(15);

        [$from, $to] = $this->range();
        $clockOuts = Attendance::query()
            ->where('type', AttendanceType::ClockOut)
            ->whereIn('user_id', $page->pluck('user_id')->unique())
            ->whereBetween('server_time', [$from, $to])
            ->get()
            ->keyBy(fn (Attendance $a) => $a->user_id.'|'.$a->server_time->toDateString());

        $page->getCollection()->each(fn (Attendance $in) => $in->setRelation(
            'clockOut',
            $clockOuts->get($in->user_id.'|'.$in->server_time->toDateString()),
        ));

        return $page;
    }

    #[Computed]
    public function shifts(): LengthAwarePaginator
    {
        [$from, $to] = $this->range();

        return Shift::query()
            ->with(['location', 'opener', 'closer'])
            ->whereBetween('opened_at', [$from, $to])
            ->when($this->outletFilter !== '', fn ($q) => $q->where('location_id', $this->outletFilter))
            ->orderByDesc('opened_at')
            ->paginate(15);
    }
}; ?>

@php
    $metrics = $this->metrics;
    $absentToday = $this->today->flatMap(fn ($row) => $row['absent']);
@endphp

<div class="flex flex-col gap-6" x-data="{ photo: { url: '', title: '' } }">
    <x-page-header title="Absensi" description="Pantau kehadiran, ketepatan waktu, dan shift setiap cabang." />

    {{-- Filter --}}
    <section class="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-5">
        <flux:input wire:model.live="from" type="date" label="Dari tanggal" :max="now()->toDateString()" />
        <flux:input wire:model.live="to" type="date" label="Sampai tanggal" :max="now()->toDateString()" />
        <flux:select wire:model.live="outletFilter" label="Cabang">
            <flux:select.option value="">Semua cabang</flux:select.option>
            @foreach ($this->outlets as $outlet)
                <flux:select.option :value="$outlet->id">{{ $outlet->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="userFilter" label="Karyawan">
            <flux:select.option value="">Semua karyawan</flux:select.option>
            @foreach ($this->staff as $person)
                <flux:select.option :value="$person->id">{{ $person->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="statusFilter" label="Status">
            <flux:select.option value="">Semua status</flux:select.option>
            <flux:select.option value="late">Terlambat</flux:select.option>
            <flux:select.option value="early_leave">Pulang awal</flux:select.option>
            <flux:select.option value="no_clock_out">Belum absen pulang</flux:select.option>
        </flux:select>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-metric-card label="Absen masuk" :value="$metrics['total'].' kali'" icon="user-plus" hint="Dalam periode terpilih" />
        <x-metric-card label="Terlambat" :value="$metrics['late'].' kali'" icon="clock" hint="Masuk setelah jam buka" />
        <x-metric-card label="Pulang awal" :value="$metrics['early'].' kali'" icon="arrow-right-start-on-rectangle" hint="Pulang sebelum jam tutup" />
        <x-metric-card label="Belum absen hari ini" :value="$absentToday->count().' orang'" icon="user-minus"
            :hint="$absentToday->isEmpty() ? 'Semua penjaga sudah absen' : $absentToday->pluck('name')->take(3)->implode(', ')" />
    </div>

    <nav class="-mx-1 overflow-x-auto border-b border-border" aria-label="Jenis rekap">
        <ul class="flex min-w-max gap-6 px-1">
            @foreach (['attendance' => 'Kehadiran karyawan', 'shifts' => 'Ringkasan shift'] as $key => $label)
                <li>
                    <button
                        type="button"
                        wire:click="$set('tab', '{{ $key }}')"
                        @if ($tab === $key) aria-current="page" @endif
                        @class([
                            '-mb-px flex h-12 items-center gap-2 border-b-2 text-sm',
                            'border-primary font-semibold text-text' => $tab === $key,
                            'border-transparent text-muted hover:text-text' => $tab !== $key,
                        ])
                    >{{ $label }}</button>
                </li>
            @endforeach
        </ul>
    </nav>

    @if ($tab === 'shifts')
        <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-semibold text-text">Ringkasan shift</h2>
                <p class="text-sm text-muted">Jam buka/tutup aktual dibanding jam standar outlet, beserta kas shift. Waktu dalam WIB.</p>
            </div>

            @if ($this->shifts->isEmpty())
                <x-empty-state icon="clock" title="Belum ada shift" description="Tidak ada shift pada periode dan cabang ini." />
            @else
                <x-data-table>
                    <x-slot:head>
                        <th>Tanggal / cabang</th>
                        <th>Buka</th>
                        <th>Tutup</th>
                        <th>Kas awal</th>
                        <th>Kas seharusnya</th>
                        <th>Kas fisik</th>
                        <th>Selisih</th>
                        <th class="text-end!">Foto</th>
                    </x-slot:head>

                    @foreach ($this->shifts as $shift)
                        <tr wire:key="shift-{{ $shift->id }}">
                            <td>
                                <div class="font-medium">{{ $shift->opened_at->translatedFormat('d M Y') }}</div>
                                <div class="text-xs text-muted">{{ $shift->location->name }}</div>
                            </td>
                            <td class="whitespace-nowrap">
                                <div>{{ $shift->opened_at->format('H.i') }} <span class="text-xs text-muted">/ std {{ $shift->location->open_time ? substr($shift->location->open_time, 0, 5) : '—' }}</span></div>
                                <div class="text-xs text-muted">{{ $shift->opener->name }}</div>
                                @if ($late = $shift->openedLateMinutes())
                                    <x-status-badge variant="warning">Telat {{ $late }}m</x-status-badge>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                @if ($shift->isOpen())
                                    <x-status-badge variant="success">Masih buka</x-status-badge>
                                @else
                                    <div>{{ $shift->closed_at->format('H.i') }} <span class="text-xs text-muted">/ std {{ $shift->location->close_time ? substr($shift->location->close_time, 0, 5) : '—' }}</span></div>
                                    <div class="text-xs text-muted">{{ $shift->closer?->name }}</div>
                                    @if ($early = $shift->closedEarlyMinutes())
                                        <x-status-badge variant="warning">Awal {{ $early }}m</x-status-badge>
                                    @endif
                                @endif
                            </td>
                            <td class="whitespace-nowrap">{{ rupiah($shift->opening_cash) }}</td>
                            <td class="whitespace-nowrap">{{ $shift->expected_cash !== null ? rupiah($shift->expected_cash) : '—' }}</td>
                            <td class="whitespace-nowrap">{{ $shift->counted_cash !== null ? rupiah($shift->counted_cash) : '—' }}</td>
                            <td class="whitespace-nowrap">
                                @if ($shift->variance === null)
                                    <span class="text-muted">—</span>
                                @elseif ($shift->variance === 0)
                                    <x-status-badge variant="success">Pas</x-status-badge>
                                @elseif ($shift->variance < 0)
                                    <x-status-badge variant="danger">Kurang {{ rupiah(abs($shift->variance)) }}</x-status-badge>
                                @else
                                    <x-status-badge variant="warning">Lebih {{ rupiah($shift->variance) }}</x-status-badge>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($shift->closing_photo_path)
                                    @php $url = route('shifts.photo', $shift); $title = 'Kondisi outlet · '.$shift->location->name.' · '.$shift->closed_at->translatedFormat('d M Y H.i'); @endphp
                                    <button type="button" class="inline-block" x-on:click="photo = { url: @js($url), title: @js($title) }; $flux.modal('photo-viewer').show()" aria-label="Perbesar foto kondisi outlet">
                                        <img src="{{ $url }}" alt="Foto kondisi outlet" loading="lazy" class="size-12 rounded-lg border border-border object-cover" />
                                    </button>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    <x-slot:footer>
                        <span>Menampilkan {{ $this->shifts->count() }} dari {{ $this->shifts->total() }} shift</span>
                        {{ $this->shifts->links() }}
                    </x-slot:footer>
                </x-data-table>
            @endif
        </section>
    @else
        <section class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4 sm:p-6">
            <div>
                <h2 class="text-lg font-semibold text-text">Kehadiran karyawan</h2>
                <p class="text-sm text-muted">Waktu memakai jam server (WIB). Ketuk foto untuk memperbesar.</p>
            </div>

            @if ($this->rows->isEmpty())
                <x-empty-state icon="finger-print" title="Belum ada absen" description="Tidak ada absen masuk pada periode dan filter ini." />
            @else
                <x-data-table>
                    <x-slot:head>
                        <th>Karyawan</th>
                        <th>Tanggal</th>
                        <th>Absen masuk</th>
                        <th>Absen pulang</th>
                        <th>Jarak & akurasi</th>
                        <th>Status</th>
                    </x-slot:head>

                    @foreach ($this->rows as $in)
                        @php $out = $in->clockOut; @endphp
                        <tr wire:key="att-{{ $in->id }}">
                            <td>
                                <div class="font-medium">{{ $in->user->name }}</div>
                                <div class="text-xs text-muted">{{ $in->location->name }}</div>
                            </td>
                            <td class="whitespace-nowrap">{{ $in->server_time->translatedFormat('D, d M Y') }}</td>
                            @foreach ([$in, $out] as $record)
                                <td class="whitespace-nowrap">
                                    @if ($record)
                                        <div class="flex items-center gap-3">
                                            @if ($record->photo_path)
                                                @php $url = route('attendances.photo', $record); $title = $record->type->label().' · '.$in->user->name.' · '.$record->server_time->translatedFormat('d M Y H.i'); @endphp
                                                <button type="button" x-on:click="photo = { url: @js($url), title: @js($title) }; $flux.modal('photo-viewer').show()" aria-label="Perbesar foto {{ strtolower($record->type->label()) }}">
                                                    <img src="{{ $url }}" alt="Foto {{ strtolower($record->type->label()) }}" loading="lazy" class="size-12 rounded-lg border border-border object-cover" />
                                                </button>
                                            @else
                                                <span class="flex size-12 items-center justify-center rounded-lg bg-canvas text-center text-[10px] leading-tight text-muted" title="Foto dihapus setelah masa simpan">Foto<br>dihapus</span>
                                            @endif
                                            <div>
                                                <div class="font-medium">{{ $record->server_time->format('H.i') }} WIB</div>
                                                <a href="{{ $record->mapsUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-medium text-text underline underline-offset-2">
                                                    <flux:icon name="map-pin" class="size-3.5" /> Lihat di peta
                                                </a>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted">Belum absen pulang</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-xs whitespace-nowrap text-muted">
                                @foreach (array_filter([$in, $out]) as $record)
                                    <div>{{ $record->type === \App\Enums\AttendanceType::ClockIn ? 'Masuk' : 'Pulang' }}: {{ number_format((float) $record->distance_m, 0, ',', '.') }} m (±{{ number_format((float) $record->accuracy_m, 0, ',', '.') }} m)</div>
                                @endforeach
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @if ($in->isLate())
                                        <x-status-badge variant="warning">Terlambat {{ $in->flags['late_minutes'] ?? 0 }}m</x-status-badge>
                                    @endif
                                    @if ($out?->isEarlyLeave())
                                        <x-status-badge variant="warning">Pulang awal {{ $out->flags['early_minutes'] ?? 0 }}m</x-status-badge>
                                    @endif
                                    @if (! $in->isLate() && ! $out?->isEarlyLeave())
                                        <x-status-badge variant="success">Tepat waktu</x-status-badge>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach

                    <x-slot:footer>
                        <span>Menampilkan {{ $this->rows->count() }} dari {{ $this->rows->total() }} absen masuk</span>
                        {{ $this->rows->links() }}
                    </x-slot:footer>
                </x-data-table>
            @endif
        </section>
    @endif

    {{-- Foto diperbesar --}}
    <flux:modal name="photo-viewer" class="w-full max-w-2xl">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg" x-text="photo.title"></flux:heading>
            <img :src="photo.url" alt="Foto diperbesar" class="max-h-[75vh] w-full rounded-xl object-contain" />
        </div>
    </flux:modal>
</div>
