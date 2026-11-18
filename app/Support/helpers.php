<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('rupiah')) {
    /**
     * Format an integer rupiah amount as "Rp4.850.000" (design system).
     */
    function rupiah(int|string|null $amount): string
    {
        return 'Rp'.number_format((int) $amount, 0, ',', '.');
    }
}

if (! function_exists('audit')) {
    /**
     * Record an audit log entry (AUD-1). Call it inside the DB transaction of the action.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    function audit(AuditAction $action, ?Model $model = null, ?array $old = null, ?array $new = null, ?User $actor = null): AuditLog
    {
        return app(AuditService::class)->log($action, $model, $old, $new, $actor);
    }
}

if (! function_exists('qty')) {
    /**
     * Format a DECIMAL(12,3) quantity without trailing zeros, Indonesian style ("1,5", "1.200").
     */
    function qty(int|float|string|null $value): string
    {
        $formatted = number_format((float) $value, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
