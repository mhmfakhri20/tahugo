{{--
    Pagination tahugo. (override livewire::tailwind). Tanpa teks "Showing x to y of z results":
    keterangan jumlah sudah ada di footer tabel ("Menampilkan X dari Y ..."). Warna memakai token design system.
--}}
@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';

$pageName = $paginator->getPageName();
$base = 'inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-medium';
$idle = $base.' border-border bg-surface text-text hover:bg-canvas';
$disabled = $base.' cursor-default border-border bg-surface text-muted opacity-60';
$current = $base.' border-primary bg-primary font-semibold text-[#38332B]';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Navigasi halaman" class="flex items-center justify-end">
            {{-- Layar kecil: sebelumnya / halaman x dari y / berikutnya --}}
            <div class="flex items-center gap-2 sm:hidden">
                @if ($paginator->onFirstPage())
                    <span class="{{ $disabled }}" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <flux:icon name="chevron-left" class="size-4" />
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="{{ $idle }}" aria-label="{{ __('pagination.previous') }}">
                        <flux:icon name="chevron-left" class="size-4" />
                    </button>
                @endif

                <span class="px-1 text-sm text-muted">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="{{ $idle }}" aria-label="{{ __('pagination.next') }}">
                        <flux:icon name="chevron-right" class="size-4" />
                    </button>
                @else
                    <span class="{{ $disabled }}" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <flux:icon name="chevron-right" class="size-4" />
                    </span>
                @endif
            </div>

            {{-- Layar lebar: tombol nomor halaman --}}
            <div class="hidden items-center gap-1 sm:flex">
                @if ($paginator->onFirstPage())
                    <span class="{{ $disabled }}" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <flux:icon name="chevron-left" class="size-4" />
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="{{ $idle }}" aria-label="{{ __('pagination.previous') }}">
                        <flux:icon name="chevron-left" class="size-4" />
                    </button>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="{{ $disabled }}" aria-disabled="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <span wire:key="paginator-{{ $pageName }}-page{{ $page }}">
                                @if ($page == $paginator->currentPage())
                                    <span class="{{ $current }}" aria-current="page">{{ $page }}</span>
                                @else
                                    <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="{{ $idle }}" aria-label="Ke halaman {{ $page }}">
                                        {{ $page }}
                                    </button>
                                @endif
                            </span>
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="{{ $idle }}" aria-label="{{ __('pagination.next') }}">
                        <flux:icon name="chevron-right" class="size-4" />
                    </button>
                @else
                    <span class="{{ $disabled }}" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <flux:icon name="chevron-right" class="size-4" />
                    </span>
                @endif
            </div>
        </nav>
    @endif
</div>
