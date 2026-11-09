{{--
    Tabel tahugo.: header bg canvas teks muted, baris dipisah garis border, scroll horizontal di layar kecil.
    Slot `head` berisi <th>, slot default berisi <tr>, slot `footer` opsional ("Menampilkan X dari Y ...").
    Pakai kelas `.dt-actions` pada sel aksi supaya rata kanan.
--}}
<div {{ $attributes->class('flex flex-col gap-4') }}>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-start text-sm">
            <thead>
                <tr class="bg-canvas text-muted [&>th]:px-4 [&>th]:py-3 [&>th]:text-start [&>th]:font-medium [&>th:first-child]:rounded-s-lg [&>th:last-child]:rounded-e-lg">
                    {{ $head }}
                </tr>
            </thead>
            <tbody class="divide-y divide-border text-text [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="flex flex-col gap-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
            {{ $footer }}
        </div>
    @endisset
</div>
