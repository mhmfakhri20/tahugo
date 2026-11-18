<?php

use App\Livewire\Concerns\HandlesInventoryErrors;
use App\Models\Shift;
use App\Services\AttendanceService;
use App\Services\ShiftService;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Buka shift (SHF-2): wajib sudah absen masuk (BR-5), isi kas awal. Satu outlet satu shift terbuka (SHF-1).
 */
new #[Title('Buka shift')] class extends Component {
    use HandlesInventoryErrors;

    public string $openingCash = '';

    public function mount(): void
    {
        $this->authorize('open', Shift::class);

        $state = app(AttendanceService::class)->todayState(auth()->user());

        $message = match ($state['step']) {
            'clock_in' => 'Absen masuk dulu sebelum membuka shift.',
            'on_duty' => 'Shift sudah dibuka. Anda tidak perlu membuka shift lagi.',
            'clock_out', 'done' => 'Shift hari ini sudah ditutup.',
            default => null,
        };

        if ($message) {
            session()->flash('error', $message);
            $this->redirectRoute('outlet.home', navigate: true);
        }
    }

    public function open(ShiftService $service): void
    {
        $validated = $this->validate([
            'openingCash' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], [], ['openingCash' => 'kas awal']);

        $shift = $this->guardInventory(
            fn () => $service->open(auth()->user(), (int) $validated['openingCash']),
            'openingCash',
        );

        session()->flash('success', 'Shift dibuka pukul '.$shift->opened_at->format('H.i').'. Selamat bekerja!');

        $this->redirectRoute('outlet.home', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-5">
    <div>
        <a href="{{ route('outlet.home') }}" wire:navigate class="inline-flex min-h-12 items-center gap-1 text-base font-medium text-muted">
            <flux:icon name="arrow-left" class="size-5" /> Beranda
        </a>
        <h1 class="text-[26px] leading-tight font-bold">Buka shift</h1>
        <p class="text-base text-muted">Hitung uang kembalian di laci sebelum mulai berjualan.</p>
    </div>

    <form wire:submit="open" class="flex flex-col gap-5 rounded-xl border border-border bg-surface p-4">
        <flux:field>
            <flux:label class="text-base!">Kas awal (uang kembalian) *</flux:label>
            <flux:input wire:model="openingCash" type="number" min="0" inputmode="numeric" placeholder="0" autofocus>
                <x-slot name="iconLeading"><span class="text-base text-muted">Rp</span></x-slot>
            </flux:input>
            <flux:description class="text-base!">Tulis 0 bila tidak ada uang kembalian.</flux:description>
            <flux:error name="openingCash" />
        </flux:field>

        <button type="submit" wire:loading.attr="disabled" class="flex min-h-14 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-lg font-semibold text-[#38332B] disabled:bg-border disabled:text-muted">
            <flux:icon name="lock-open" class="size-6" /> Buka shift sekarang
        </button>
    </form>
</div>
