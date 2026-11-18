<?php

use App\Models\Attendance;
use App\Models\CashEntry;
use App\Services\PurchaseService;
use App\Services\StockRequestService;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/** Public routes and framework assets that need no login. */
const PUBLIC_ROUTES = ['/', 'login', 'up'];

/**
 * Every GET route of the app (no Livewire/Flux assets, no storage).
 *
 * @return list<RoutingRoute>
 */
function appGetRoutes(): array
{
    return collect(Route::getRoutes())
        ->filter(fn (RoutingRoute $r) => in_array('GET', $r->methods(), true))
        ->reject(fn (RoutingRoute $r) => preg_match('#^(livewire|flux/|storage/|_boost|user/confirm)#', $r->uri()) === 1)
        ->reject(fn (RoutingRoute $r) => in_array($r->uri(), PUBLIC_ROUTES, true))
        ->values()
        ->all();
}

/**
 * Fill route parameters with real models so bindings resolve.
 *
 * @param  array<string, int|string>  $params
 */
function routeUrl(RoutingRoute $route, array $params): string
{
    $uri = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => (string) ($params[$m[1]] ?? 'x'), $route->uri());

    return '/'.ltrim($uri, '/');
}

beforeEach(function () {
    $w = cashWorld();
    $sale = sell($w->staffA, [[$w->paket, 1]]);
    $purchase = app(PurchaseService::class)->record($w->warehouse, ['supplier' => 'X', 'purchase_date' => now()->toDateString()],
        [['item_id' => $w->tahu->id, 'qty' => 10, 'unit_price' => 100]], null, $w->admin);
    $request = app(StockRequestService::class)->create($w->outletA, [$w->tahu->id => 5], null, $w->staffA);

    $this->w = $w;
    $this->params = [
        'attendance' => Attendance::factory()->by($w->staffA)->create()->id,
        'cashEntry' => spend($w->staffA, 5_000)->id,
        'stockRequest' => $request->id,
        'item' => $w->tahu->id,
        'location' => $w->outletA->id,
        'product' => $w->paket->id,
        'purchase' => $purchase->id,
        'shift' => $w->shiftA->id,
        'sale' => $sale->id,
        'path' => 'x',
    ];
});

test('every application route requires login', function () {
    $routes = appGetRoutes();
    expect(count($routes))->toBeGreaterThan(50);

    foreach ($routes as $route) {
        $response = $this->get(routeUrl($route, $this->params));

        expect($response->status())->toBe(302, "Route /{$route->uri()} must redirect guests")
            ->and($response->headers->get('Location'))->toEndWith('/login');
    }
});

test('role areas reject the other roles with 403', function () {
    $w = $this->w;
    $areas = ['owner/' => $w->owner, 'outlet/' => $w->staffA, 'warehouse/' => $w->admin];
    $checked = 0;

    foreach (appGetRoutes() as $route) {
        foreach ($areas as $prefix => $allowed) {
            if (! str_starts_with($route->uri().'/', $prefix)) {
                continue;
            }

            foreach (array_filter($areas, fn ($user) => $user->isNot($allowed)) as $other) {
                $this->actingAs($other)->get(routeUrl($route, $this->params))
                    ->assertForbidden();
                $checked++;
            }
        }
    }

    expect($checked)->toBeGreaterThan(80);
});

test('private files are not reachable through a public URL', function () {
    $entry = CashEntry::find($this->params['cashEntry']);

    // Laravel's signed local-file route refuses unsigned requests; the public disk has no copy.
    expect($this->get('/storage/'.$entry->receipt_photo_path)->status())->toBeIn([403, 404]);
    $this->get('/'.$entry->receipt_photo_path)->assertNotFound();
});

test('models only accept their fillable attributes', function () {
    foreach (glob(app_path('Models/*.php')) as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');
        $model = new $class;

        expect($model->getGuarded() === ['*'] && $model->getFillable() === [])->toBeFalse("{$class} has no fillable list")
            ->and($model->getGuarded())->not->toBe([], "{$class} must not be fully unguarded");
    }
});
