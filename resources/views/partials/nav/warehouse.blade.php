<div class="px-2 pt-2 pb-1 text-xs font-semibold tracking-wide text-muted uppercase">Menu utama</div>
<flux:sidebar.item icon="home" :href="route('warehouse.home')" :current="request()->routeIs('warehouse.home')" wire:navigate>
    Beranda
</flux:sidebar.item>
<flux:sidebar.item icon="archive-box" :href="route('warehouse.stock')" :current="request()->routeIs('warehouse.stock*')" wire:navigate>
    Stok Gudang
</flux:sidebar.item>
<flux:sidebar.item icon="shopping-bag" :href="route('warehouse.purchases')" :current="request()->routeIs('warehouse.purchases*')" wire:navigate>
    Pembelian
</flux:sidebar.item>
<flux:sidebar.item icon="truck" :href="route('warehouse.shipments')" :current="request()->routeIs('warehouse.shipments*')" wire:navigate>
    Pengiriman
</flux:sidebar.item>
<flux:sidebar.item icon="document-chart-bar" :href="route('warehouse.reports')" :current="request()->routeIs('warehouse.reports*')" wire:navigate>
    Laporan Persediaan
</flux:sidebar.item>
