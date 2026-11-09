<div class="px-2 pt-2 pb-1 text-xs font-semibold tracking-wide text-muted uppercase">Menu utama</div>
<flux:sidebar.item icon="home" :href="route('owner.dashboard')" :current="request()->routeIs('owner.dashboard')" wire:navigate>
    Dashboard
</flux:sidebar.item>
<flux:sidebar.item icon="check-badge" :href="route('owner.verification')" :current="request()->routeIs('owner.verification*')" wire:navigate>
    Verifikasi
</flux:sidebar.item>
<flux:sidebar.item icon="document-chart-bar" :href="route('owner.reports')" :current="request()->routeIs('owner.reports*')" wire:navigate>
    Laporan
</flux:sidebar.item>
<flux:sidebar.item icon="finger-print" :href="route('owner.attendance')" :current="request()->routeIs('owner.attendance*')" wire:navigate>
    Absensi
</flux:sidebar.item>

<div class="px-2 pt-4 pb-1 text-xs font-semibold tracking-wide text-muted uppercase">Lainnya</div>
<flux:sidebar.item icon="cog-6-tooth" :href="route('owner.settings')" :current="request()->routeIs('owner.settings*')" wire:navigate>
    Pengaturan
</flux:sidebar.item>
