<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes the append-only audit trail (AUD-1). Call it INSIDE the DB transaction of the action,
 * so a rolled-back action leaves no log (and a failing log rolls the action back).
 */
class AuditService
{
    /** Keys that are never stored (passwords, tokens, 2FA secrets). */
    private const SENSITIVE = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Indonesian labels for the old/new value table on the audit page. */
    private const LABELS = [
        'name' => 'Nama', 'username' => 'Username', 'phone' => 'No. HP', 'role' => 'Peran',
        'location_id' => 'Lokasi', 'is_active' => 'Aktif', 'code' => 'Kode', 'type' => 'Tipe',
        'address' => 'Alamat', 'latitude' => 'Lintang', 'longitude' => 'Bujur',
        'attendance_radius_m' => 'Radius absen (m)', 'open_time' => 'Jam buka', 'close_time' => 'Jam tutup',
        'monthly_target' => 'Target bulanan', 'status' => 'Status', 'void_reason' => 'Alasan batal',
        'reject_reason' => 'Alasan tolak', 'reason' => 'Alasan', 'amount' => 'Nominal', 'total' => 'Total',
        'number' => 'Nomor', 'counted_cash' => 'Kas fisik', 'expected_cash' => 'Kas seharusnya',
        'variance' => 'Selisih', 'opening_cash' => 'Kas awal', 'prices' => 'Harga per kanal',
        'recipe' => 'Resep', 'items' => 'Bahan', 'qty' => 'Jumlah', 'item' => 'Bahan', 'note' => 'Catatan',
        'balance_after' => 'Saldo setelah', 'username_attempt' => 'Username dicoba', 'password_changed' => 'Password diganti',
    ];

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(AuditAction $action, ?Model $model = null, ?array $old = null, ?array $new = null, ?User $actor = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'user_id' => ($actor ?? auth()->user())?->getKey(),
            'action' => $action,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'location_id' => $this->locationOf($model),
            'old_values' => $this->clean($old),
            'new_values' => $this->clean($new),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent() !== null ? mb_substr($request->userAgent(), 0, 512) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Only the attributes that changed, as [old, new].
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function diff(array $old, array $new): array
    {
        $old = $this->normalize($old);
        $new = $this->normalize($new);
        $changed = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            if (($old[$key] ?? null) != ($new[$key] ?? null)) {
                $changed[] = $key;
            }
        }

        return [
            array_intersect_key($old, array_flip($changed)),
            array_intersect_key($new, array_flip($changed)),
        ];
    }

    public static function fieldLabel(string $key): string
    {
        return self::LABELS[$key] ?? Str::headline($key);
    }

    /**
     * Display a stored value (scalars, booleans, nested arrays) as short Indonesian text.
     */
    public static function display(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'Ya' : 'Tidak',
            is_array($value) => collect($value)->map(fn ($v, $k) => (is_int($k) ? '' : $k.': ').self::display($v))->implode(', '),
            default => (string) $value,
        };
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $values = $this->normalize($values);

        foreach (array_keys($values) as $key) {
            if (in_array($key, self::SENSITIVE, true) || str_contains((string) $key, 'token') || str_contains((string) $key, 'secret')) {
                unset($values[$key]);
            }
        }

        return $values === [] ? null : $values;
    }

    /**
     * Enums → value, dates → string, nested arrays recursively.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalize(array $values): array
    {
        return array_map(fn ($value) => match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_array($value) => $this->normalize($value),
            default => $value,
        }, $values);
    }

    private function locationOf(?Model $model): ?int
    {
        if ($model === null) {
            return null;
        }

        if ($model instanceof Location) {
            return $model->getKey();
        }

        foreach (['location_id', 'to_location_id'] as $key) {
            if (isset($model->{$key})) {
                return (int) $model->{$key};
            }
        }

        return null;
    }
}
