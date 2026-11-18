{{--
    Grafik Chart.js (REP-8) dengan warna token design system. Selalu tampilkan juga angkanya
    (tabel/legenda) di dekat grafik: grafik ini aria-hidden.
    config: ['type' => 'bar'|'doughnut', 'labels' => [...], 'datasets' => [['label', 'data', 'color']], 'money' => bool]
--}}
@props([
    'config',
    'height' => 'h-64',
])

@php $json = json_encode($config); @endphp

<div {{ $attributes->class("relative {$height}") }} wire:ignore wire:key="chart-{{ md5($json) }}" x-data="chart(@js($config))" aria-hidden="true">
    <canvas x-ref="canvas"></canvas>
</div>
