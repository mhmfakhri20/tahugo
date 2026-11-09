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
<flux:sidebar.item icon="cog-6-tooth" :href="route('owner.settings')" :current="request()->routeIs('owner.settings*')" wire:navigate>
    Pengaturan
</flux:sidebar.item>
