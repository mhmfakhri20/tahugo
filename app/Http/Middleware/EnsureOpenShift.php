<?php

namespace App\Http\Middleware;

use App\Services\ShiftService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-4 / SHF-1: sales and expenses can only be recorded while the user's outlet has an open shift.
 * Used on the Kasir & Pengeluaran pages (alias `open-shift`).
 */
class EnsureOpenShift
{
    public function __construct(private readonly ShiftService $shifts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $outlet = $request->user()?->location;

        if ($outlet === null || $this->shifts->openShiftFor($outlet) === null) {
            $message = 'Buka shift dulu sebelum mencatat penjualan atau pengeluaran.';

            if ($request->expectsJson()) {
                abort(409, $message);
            }

            return redirect()->route('outlet.home')->with('error', $message);
        }

        return $next($request);
    }
}
