<?php

namespace App\Services;

use App\Enums\AttendanceType;
use App\Enums\CashDirection;
use App\Enums\ExpenseStatus;
use App\Enums\FundSource;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Enums\SaleStatus;
use App\Models\Attendance;
use App\Models\CashEntry;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single source of report & dashboard numbers (PRD §6.7, REP-1..6). Aggregates run in SQL;
 * void sales are always excluded; days follow Asia/Jakarta (see App\Support\Period).
 *
 * Definitions (docs/ASSUMPTIONS.md F6):
 * - Omzet        = Σ sales.total (completed). Platform sales are gross.
 * - HPP          = −Σ ledger qty (sale_out + sale_void_in) × items.last_purchase_price.
 * - Laba kotor   = Omzet − HPP − approved outlet expenses (per outlet).
 * - Laba-rugi    = Omzet + other income − (approved outlet expenses + approved business expenses,
 *                  which already include purchases via CASH-7). Purchases are never added twice.
 */
class ReportService
{
    public function __construct(
        private readonly StockOverviewService $stockOverview,
        private readonly ShiftService $shifts,
    ) {}

    // ─── Sales (REP-3) ───────────────────────────────────────────────────────

    /**
     * @return Builder<Sale>
     */
    public function salesQuery(Period $period, ?int $locationId = null): Builder
    {
        return Sale::query()
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.created_at', $period->bounds())
            ->when($locationId, fn (Builder $q) => $q->where('sales.location_id', $locationId));
    }

    /**
     * @return array{revenue: int, count: int, pcs: int, average: int}
     */
    public function salesTotals(Period $period, ?int $locationId = null): array
    {
        $row = $this->salesQuery($period, $locationId)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $pcs = (int) $this->salesQuery($period, $locationId)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->sum('sale_items.qty');

        $count = (int) $row->n;
        $revenue = (int) $row->revenue;

        return [
            'revenue' => $revenue,
            'count' => $count,
            'pcs' => $pcs,
            'average' => $count > 0 ? intdiv($revenue, $count) : 0,
        ];
    }

    /**
     * Every day of the period (missing days = 0).
     *
     * @return Collection<string, array{date: string, revenue: int, count: int}>
     */
    public function salesByDay(Period $period, ?int $locationId = null): Collection
    {
        $rows = $this->salesQuery($period, $locationId)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n, COALESCE(SUM(total), 0) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->d, 0, 10));

        return collect($period->dates())->mapWithKeys(fn (string $date) => [$date => [
            'date' => $date,
            'revenue' => (int) ($rows->get($date)?->revenue ?? 0),
            'count' => (int) ($rows->get($date)?->n ?? 0),
        ]]);
    }

    /**
     * @return Collection<int, array{channel: SalesChannel, revenue: int, count: int, share: float}>
     */
    public function salesByChannel(Period $period, ?int $locationId = null): Collection
    {
        $rows = $this->salesQuery($period, $locationId)
            ->selectRaw('channel, COUNT(*) as n, COALESCE(SUM(total), 0) as revenue')
            ->groupBy('channel')
            ->get()
            ->keyBy(fn ($row) => $row->channel instanceof SalesChannel ? $row->channel->value : $row->channel);

        $total = (int) $rows->sum('revenue');

        return collect(SalesChannel::cases())->map(fn (SalesChannel $channel) => [
            'channel' => $channel,
            'revenue' => $revenue = (int) ($rows->get($channel->value)?->revenue ?? 0),
            'count' => (int) ($rows->get($channel->value)?->n ?? 0),
            'share' => $this->share($revenue, $total),
        ]);
    }

    /**
     * Offline sales per payment method (platform orders have none).
     *
     * @return Collection<int, array{method: PaymentMethod, revenue: int, count: int, share: float}>
     */
    public function salesByPaymentMethod(Period $period, ?int $locationId = null): Collection
    {
        $rows = $this->salesQuery($period, $locationId)
            ->where('channel', SalesChannel::Offline)
            ->selectRaw('payment_method, COUNT(*) as n, COALESCE(SUM(total), 0) as revenue')
            ->groupBy('payment_method')
            ->get()
            ->keyBy(fn ($row) => $row->payment_method instanceof PaymentMethod ? $row->payment_method->value : $row->payment_method);

        $total = (int) $rows->sum('revenue');

        return collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
            'method' => $method,
            'revenue' => $revenue = (int) ($rows->get($method->value)?->revenue ?? 0),
            'count' => (int) ($rows->get($method->value)?->n ?? 0),
            'share' => $this->share($revenue, $total),
        ]);
    }

    /**
     * @return Collection<int, array{product: string, qty: int, revenue: int, share: float}>
     */
    public function salesByProduct(Period $period, ?int $locationId = null): Collection
    {
        $rows = $this->salesQuery($period, $locationId)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->selectRaw('products.name as product, SUM(sale_items.qty) as qty, SUM(sale_items.line_total) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->get();

        $total = (int) $rows->sum('revenue');

        return $rows->map(fn ($row) => [
            'product' => $row->product,
            'qty' => (int) $row->qty,
            'revenue' => (int) $row->revenue,
            'share' => $this->share((int) $row->revenue, $total),
        ])->values();
    }

    /**
     * @return Collection<int, array{outlet: Location, revenue: int, count: int, pcs: int, share: float}>
     */
    public function salesByOutlet(Period $period, ?int $locationId = null): Collection
    {
        $rows = $this->salesQuery($period, $locationId)
            ->selectRaw('location_id, COUNT(*) as n, COALESCE(SUM(total), 0) as revenue')
            ->groupBy('location_id')
            ->get()
            ->keyBy('location_id');

        $pcs = $this->salesQuery($period, $locationId)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->selectRaw('sales.location_id as location_id, SUM(sale_items.qty) as pcs')
            ->groupBy('sales.location_id')
            ->pluck('pcs', 'location_id');

        $total = (int) $rows->sum('revenue');

        return $this->outlets($locationId)->map(fn (Location $outlet) => [
            'outlet' => $outlet,
            'revenue' => $revenue = (int) ($rows->get($outlet->id)?->revenue ?? 0),
            'count' => (int) ($rows->get($outlet->id)?->n ?? 0),
            'pcs' => (int) ($pcs->get($outlet->id) ?? 0),
            'share' => $this->share($revenue, $total),
        ])->values();
    }

    // ─── Costs & profit (REP-4) ─────────────────────────────────────────────

    /**
     * HPP per outlet: net ingredients used by sales of the period × last purchase price.
     * Voided sales net to zero (sale_out + sale_void_in).
     *
     * @return Collection<int, int> location_id => HPP (rupiah)
     */
    public function cogs(Period $period, ?int $locationId = null): Collection
    {
        return DB::table('stock_movements as m')
            ->join('sales as s', function ($join) {
                $join->on('s.id', '=', 'm.reference_id')
                    ->where('m.reference_type', '=', (new Sale)->getMorphClass());
            })
            ->join('items as i', 'i.id', '=', 'm.item_id')
            ->whereIn('m.type', [MovementType::SaleOut->value, MovementType::SaleVoidIn->value])
            ->whereBetween('s.created_at', $period->bounds())
            ->when($locationId, fn ($q) => $q->where('s.location_id', $locationId))
            ->selectRaw('s.location_id as location_id, SUM(-m.qty * i.last_purchase_price) as cogs')
            ->groupBy('s.location_id')
            ->pluck('cogs', 'location_id')
            ->map(fn ($value) => (int) round((float) $value));
    }

    /**
     * Approved outlet expenses (in a shift, any fund) per outlet.
     *
     * @return Collection<int, int> location_id => amount
     */
    public function outletExpenses(Period $period, ?int $locationId = null): Collection
    {
        return CashEntry::query()
            ->where('direction', CashDirection::Out)
            ->whereNotNull('shift_id')
            ->where('status', ExpenseStatus::Approved)
            ->whereBetween('created_at', $period->bounds())
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw('location_id, SUM(amount) as total')
            ->groupBy('location_id')
            ->pluck('total', 'location_id')
            ->map(fn ($value) => (int) $value);
    }

    /**
     * Approved expenses (outlet + business) per category; purchases (CASH-7) are their own row.
     *
     * @return Collection<int, array{label: string, kind: string, amount: int, share: float}>
     */
    public function expensesByCategory(Period $period, ?int $locationId = null): Collection
    {
        $rows = CashEntry::query()
            ->where('direction', CashDirection::Out)
            ->where('status', ExpenseStatus::Approved)
            ->whereBetween('created_at', $period->bounds())
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw("category_id, CASE WHEN source_type IS NOT NULL THEN 'purchase' WHEN shift_id IS NOT NULL THEN 'outlet' ELSE 'business' END as kind, SUM(amount) as total")
            ->groupBy('category_id', 'kind')
            ->get();

        $names = ExpenseCategory::query()->pluck('name', 'id');
        $total = (int) $rows->sum('total');

        return $rows->map(fn ($row) => [
            'label' => $row->kind === 'purchase' ? 'Pembelian bahan' : ($names->get($row->category_id) ?? 'Tanpa kategori'),
            'kind' => $row->kind,
            'amount' => (int) $row->total,
        ])
            ->groupBy(fn ($row) => $row['kind'].'|'.$row['label'])
            ->map(fn (Collection $group) => [
                'label' => $group->first()['label'],
                'kind' => $group->first()['kind'],
                'amount' => $amount = (int) $group->sum('amount'),
                'share' => $this->share($amount, $total),
            ])
            ->sortByDesc('amount')
            ->values();
    }

    public function otherIncome(Period $period, ?int $locationId = null): int
    {
        return (int) CashEntry::query()
            ->where('direction', CashDirection::In)
            ->where('status', ExpenseStatus::Approved)
            ->whereBetween('created_at', $period->bounds())
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->sum('amount');
    }

    /**
     * Approved business-level expenses, split purchases (CASH-7) vs others (CASH-6).
     *
     * @return array{purchases: int, other: int, total: int}
     */
    public function businessExpenses(Period $period, ?int $locationId = null): array
    {
        $rows = CashEntry::query()
            ->where('direction', CashDirection::Out)
            ->where('fund_source', FundSource::Business)
            ->where('status', ExpenseStatus::Approved)
            ->whereBetween('created_at', $period->bounds())
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw('CASE WHEN source_type IS NOT NULL THEN 1 ELSE 0 END as is_purchase, SUM(amount) as total')
            ->groupBy('is_purchase')
            ->pluck('total', 'is_purchase');

        $purchases = (int) ($rows->get(1) ?? 0);
        $other = (int) ($rows->get(0) ?? 0);

        return ['purchases' => $purchases, 'other' => $other, 'total' => $purchases + $other];
    }

    /**
     * REP-4: simple business P&L. Purchases count once (as the CASH-7 business expense).
     *
     * @return array{revenue: int, other_income: int, income: int, outlet_expenses: int, purchases: int, business_other: int, expenses: int, net: int}
     */
    public function profitLoss(Period $period, ?int $locationId = null): array
    {
        $revenue = $this->salesTotals($period, $locationId)['revenue'];
        $otherIncome = $this->otherIncome($period, $locationId);
        $outlet = (int) $this->outletExpenses($period, $locationId)->sum();
        $business = $this->businessExpenses($period, $locationId);

        $income = $revenue + $otherIncome;
        $expenses = $outlet + $business['total'];

        return [
            'revenue' => $revenue,
            'other_income' => $otherIncome,
            'income' => $income,
            'outlet_expenses' => $outlet,
            'purchases' => $business['purchases'],
            'business_other' => $business['other'],
            'expenses' => $expenses,
            'net' => $income - $expenses,
        ];
    }

    /**
     * Gross profit per outlet = revenue − HPP − approved outlet expenses.
     *
     * @return Collection<int, array{outlet: Location, revenue: int, cogs: int, expenses: int, gross: int}>
     */
    public function grossProfitByOutlet(Period $period, ?int $locationId = null): Collection
    {
        $sales = $this->salesByOutlet($period, $locationId)->keyBy(fn ($row) => $row['outlet']->id);
        $cogsByOutlet = $this->cogs($period, $locationId);
        $expenses = $this->outletExpenses($period, $locationId);

        return $this->outlets($locationId)->map(function (Location $outlet) use ($sales, $cogsByOutlet, $expenses) {
            $revenue = $sales->get($outlet->id)['revenue'] ?? 0;
            $cogs = (int) ($cogsByOutlet->get($outlet->id) ?? 0);
            $exp = (int) ($expenses->get($outlet->id) ?? 0);

            return ['outlet' => $outlet, 'revenue' => $revenue, 'cogs' => $cogs, 'expenses' => $exp, 'gross' => $revenue - $cogs - $exp];
        })->values();
    }

    // ─── Dashboard (REP-1) ──────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?int $locationId = null): array
    {
        $today = Period::day(now());
        $yesterday = Period::day(now()->subDay());
        $week = Period::lastDays(7);

        $todayTotals = $this->salesTotals($today, $locationId);
        $yesterdayTotals = $this->salesTotals($yesterday, $locationId);

        $gross = (int) $this->grossProfitByOutlet($today, $locationId)->sum('gross');

        $shiftsClosed = Shift::query()
            ->whereBetween('closed_at', $today->bounds())
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->get(['variance']);

        $outletExpensesToday = (int) CashEntry::query()
            ->where('direction', CashDirection::Out)
            ->whereNotNull('shift_id')
            ->where('status', '!=', ExpenseStatus::Rejected)
            ->whereBetween('created_at', $today->bounds())
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->sum('amount');

        $current = $this->salesByDay($week, $locationId);
        $previous = $this->salesByDay($week->previous(), $locationId)->values();

        return [
            'revenue' => $todayTotals['revenue'],
            'revenue_change' => $this->change($todayTotals['revenue'], $yesterdayTotals['revenue']),
            'count' => $todayTotals['count'],
            'count_diff' => $todayTotals['count'] - $yesterdayTotals['count'],
            'expenses' => $outletExpensesToday,
            'gross' => $gross,
            'variance' => (int) $shiftsClosed->sum('variance'),
            'shifts_closed' => $shiftsClosed->count(),
            'shifts_short' => $shiftsClosed->where('variance', '<', 0)->count(),
            'trend' => $current->values()->map(fn (array $day, int $i) => [
                'date' => $day['date'],
                'current' => $day['revenue'],
                'previous' => $previous[$i]['revenue'] ?? 0,
            ]),
            'week_revenue' => (int) $current->sum('revenue'),
            'week_change' => $this->change((int) $current->sum('revenue'), (int) $previous->sum('revenue')),
            'channels' => $this->salesByChannel($week, $locationId),
            'outlets_today' => $this->salesByOutlet($today, $locationId),
        ];
    }

    // ─── Outlet performance (REP-6) ─────────────────────────────────────────

    /**
     * @return Collection<int, array{outlet: Location, revenue: int, previous: int, growth: float|null, count: int, share: float, month_revenue: int, target: int|null, progress: float|null}>
     */
    public function outletPerformance(Period $period): Collection
    {
        $current = $this->salesByOutlet($period)->keyBy(fn ($row) => $row['outlet']->id);
        $previous = $this->salesByOutlet($period->previous())->keyBy(fn ($row) => $row['outlet']->id);
        $month = new Period($period->to->startOfMonth(), $period->to);
        $monthly = $this->salesByOutlet($month)->keyBy(fn ($row) => $row['outlet']->id);

        return $this->outlets()->map(function (Location $outlet) use ($current, $previous, $monthly) {
            $revenue = $current->get($outlet->id)['revenue'] ?? 0;
            $before = $previous->get($outlet->id)['revenue'] ?? 0;
            $monthRevenue = $monthly->get($outlet->id)['revenue'] ?? 0;
            $target = $outlet->monthly_target ?: null;

            return [
                'outlet' => $outlet,
                'revenue' => $revenue,
                'previous' => $before,
                'growth' => $this->change($revenue, $before),
                'count' => $current->get($outlet->id)['count'] ?? 0,
                'share' => $current->get($outlet->id)['share'] ?? 0.0,
                'month_revenue' => $monthRevenue,
                'target' => $target,
                'progress' => $target ? round($monthRevenue / $target * 100, 1) : null,
            ];
        })->sortByDesc('revenue')->values();
    }

    // ─── Inventory (REP-5) ──────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function inventory(Period $period, ?int $locationId = null): array
    {
        $locations = Location::query()->active()
            ->when($locationId, fn ($q) => $q->whereKey($locationId))
            ->orderBy('type')->orderBy('name')->get();
        $ids = $locations->pluck('id');

        $movements = StockMovement::query()
            ->whereIn('location_id', $ids)
            ->whereBetween('created_at', $period->bounds())
            ->selectRaw('location_id, type, COUNT(*) as n, SUM(qty) as qty')
            ->groupBy('location_id', 'type')
            ->get()
            ->map(fn ($row) => [
                'location' => $locations->firstWhere('id', $row->location_id),
                'type' => $row->type instanceof MovementType ? $row->type : MovementType::from($row->type),
                'count' => (int) $row->n,
                'qty' => (string) $row->qty,
            ])
            ->sortBy(fn ($row) => [$row['location']->name, $row['type']->value])
            ->values();

        $purchases = Purchase::query()
            ->with(['items.item', 'location'])
            ->whereBetween('purchase_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->orderByDesc('purchase_date')->orderByDesc('id')
            ->get();

        $shipments = StockRequest::query()
            ->with(['items.item', 'toLocation'])
            ->whereNotNull('shipped_at')
            ->whereBetween('shipped_at', $period->bounds())
            ->when($locationId, fn ($q) => $q->where(fn ($q) => $q->where('to_location_id', $locationId)->orWhere('from_location_id', $locationId)))
            ->orderByDesc('shipped_at')
            ->get();

        $waste = StockMovement::query()
            ->with(['item', 'location'])
            ->where('type', MovementType::Waste)
            ->whereIn('location_id', $ids)
            ->whereBetween('created_at', $period->bounds())
            ->selectRaw('location_id, item_id, SUM(qty) as qty, COUNT(*) as n')
            ->groupBy('location_id', 'item_id')
            ->get();

        return [
            'locations' => $locations,
            'balances' => $this->stockOverview->rows($locations),
            'movements' => $movements,
            'purchases' => $purchases,
            'purchase_total' => (int) $purchases->sum('total'),
            'shipments' => $shipments,
            'shipment_discrepancies' => $shipments->filter(fn (StockRequest $r) => $r->hasDiscrepancy())->count(),
            'waste' => $waste,
        ];
    }

    // ─── Daily outlet report (REP-2) ────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function daily(Shift $shift): array
    {
        $shift->loadMissing(['location', 'opener', 'closer']);

        $byProduct = Sale::query()
            ->where('sales.shift_id', $shift->id)
            ->where('sales.status', SaleStatus::Completed)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->selectRaw('products.name as product, sales.channel as channel, SUM(sale_items.qty) as qty, SUM(sale_items.line_total) as revenue')
            ->groupBy('products.id', 'products.name', 'sales.channel')
            ->get()
            ->groupBy('product')
            ->map(fn (Collection $rows, string $product) => [
                'product' => $product,
                'qty' => (int) $rows->sum('qty'),
                'revenue' => (int) $rows->sum('revenue'),
                'channels' => $rows->mapWithKeys(fn ($row) => [
                    ($row->channel instanceof SalesChannel ? $row->channel->value : $row->channel) => ['qty' => (int) $row->qty, 'revenue' => (int) $row->revenue],
                ])->all(),
            ])
            ->sortByDesc('revenue')
            ->values();

        return [
            'shift' => $shift,
            'cash' => $this->shifts->cashSummary($shift),
            'products' => $byProduct,
            'entries' => CashEntry::query()->with(['category', 'user', 'verifier'])->where('shift_id', $shift->id)->orderBy('id')->get(),
            'attendances' => Attendance::query()->with('user')->where('shift_id', $shift->id)->orderBy('server_time')->get()
                ->groupBy('user_id')
                ->map(fn (Collection $rows) => [
                    'user' => $rows->first()->user,
                    'in' => $rows->firstWhere('type', AttendanceType::ClockIn),
                    'out' => $rows->firstWhere('type', AttendanceType::ClockOut),
                ])->values(),
            'opened_late' => $shift->openedLateMinutes(),
            'closed_early' => $shift->closedEarlyMinutes(),
        ];
    }

    // ─── Attendance recap (ATT-7 export) ─────────────────────────────────────

    /**
     * One row per person per day: clock-in + the same day's clock-out.
     *
     * @return Collection<int, array{in: Attendance, out: Attendance|null}>
     */
    public function attendanceRecap(Period $period, ?int $locationId = null, ?int $userId = null, ?string $status = null): Collection
    {
        $ins = Attendance::query()
            ->with(['user', 'location'])
            ->where('type', AttendanceType::ClockIn)
            ->whereBetween('server_time', $period->bounds())
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('server_time')
            ->get();

        $outs = Attendance::query()
            ->where('type', AttendanceType::ClockOut)
            ->whereIn('user_id', $ins->pluck('user_id')->unique())
            ->whereBetween('server_time', $period->bounds())
            ->get()
            ->keyBy(fn (Attendance $a) => $a->user_id.'|'.$a->server_time->toDateString());

        return $ins
            ->map(fn (Attendance $in) => ['in' => $in, 'out' => $outs->get($in->user_id.'|'.$in->server_time->toDateString())])
            ->filter(fn (array $row) => match ($status) {
                'late' => $row['in']->isLate(),
                'early_leave' => (bool) $row['out']?->isEarlyLeave(),
                'no_clock_out' => $row['out'] === null,
                default => true,
            })
            ->values();
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * Active outlets (or the one requested, even if inactive).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Location>
     */
    public function outlets(?int $locationId = null): \Illuminate\Database\Eloquent\Collection
    {
        return Location::query()->outlets()
            ->when($locationId, fn ($q) => $q->whereKey($locationId), fn ($q) => $q->active())
            ->orderBy('name')
            ->get();
    }

    /**
     * Percentage change, 1 decimal; null when there is nothing to compare with.
     */
    public function change(int $current, int $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    private function share(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }
}
