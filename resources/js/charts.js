/**
 * Simple charts for the reports (REP-8) with Chart.js. Colors come from the design-system tokens
 * (CSS variables from @theme in app.css), so light/dark mode stay consistent.
 *
 * Usage: <div wire:ignore x-data="chart({ type: 'bar', labels: [...], datasets: [{ label, data, color: 'primary' }], money: true })">
 *          <canvas x-ref="canvas"></canvas>
 *        </div>
 * Every chart on a page is also shown as numbers (table / legend) next to it for accessibility.
 */
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, DoughnutController, ArcElement, Tooltip, Legend);

function token(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(`--color-${name}`).trim() || '#8B8173';
}

function rupiah(value) {
    return 'Rp' + Math.round(value || 0).toLocaleString('id-ID');
}

function shortRupiah(value) {
    if (Math.abs(value) >= 1_000_000) {
        return (value / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
    }

    if (Math.abs(value) >= 1_000) {
        return (value / 1_000).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' rb';
    }

    return String(value);
}

function chart(config) {
    return {
        instance: null,

        init() {
            const muted = token('muted');
            const border = token('border');
            const money = config.money ?? true;
            const isDoughnut = config.type === 'doughnut';
            const format = (value) => (money ? rupiah(value) : Number(value).toLocaleString('id-ID'));

            const datasets = config.datasets.map((dataset) => {
                const colors = Array.isArray(dataset.color) ? dataset.color.map(token) : token(dataset.color ?? 'primary');

                return {
                    label: dataset.label,
                    data: dataset.data,
                    backgroundColor: colors,
                    borderColor: isDoughnut ? token('surface') : colors,
                    borderWidth: isDoughnut ? 2 : 0,
                    borderRadius: isDoughnut ? 0 : 6,
                    maxBarThickness: 28,
                };
            });

            this.instance = new Chart(this.$refs.canvas, {
                type: config.type ?? 'bar',
                data: { labels: config.labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    cutout: isDoughnut ? '62%' : undefined,
                    plugins: {
                        legend: {
                            display: config.legend ?? datasets.length > 1,
                            position: 'bottom',
                            labels: { color: muted, font: { family: 'Poppins', size: 12 }, boxWidth: 12, boxHeight: 12 },
                        },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => `${ctx.dataset.label ? ctx.dataset.label + ': ' : ''}${format(ctx.parsed.y ?? ctx.parsed)}`,
                            },
                        },
                    },
                    scales: isDoughnut
                        ? {}
                        : {
                              x: { grid: { display: false }, ticks: { color: muted, font: { family: 'Poppins', size: 11 } } },
                              y: {
                                  beginAtZero: true,
                                  grid: { color: border },
                                  border: { display: false },
                                  ticks: {
                                      color: muted,
                                      font: { family: 'Poppins', size: 11 },
                                      callback: (value) => (money ? shortRupiah(value) : value),
                                  },
                              },
                          },
                },
            });
        },

        destroy() {
            this.instance?.destroy();
        },
    };
}

function register() {
    window.Alpine.data('chart', chart);
}

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}
