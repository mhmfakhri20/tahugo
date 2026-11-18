<?php

namespace App\Console\Commands;

use App\Services\AttendanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * NFR-5 (UU PDP): attendance selfies are kept for a limited time, then deleted. Scheduled daily.
 */
#[Signature('attendance:prune {--days='.AttendanceService::PHOTO_RETENTION_DAYS.' : Hapus foto absen yang lebih tua dari jumlah hari ini}')]
#[Description('Hapus foto absen yang melewati masa simpan (data absen tetap disimpan)')]
class PruneAttendancePhotosCommand extends Command
{
    public function handle(AttendanceService $attendance): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days minimal 1.');

            return self::FAILURE;
        }

        $count = $attendance->prunePhotos($days);

        $this->info("{$count} foto absen yang lebih tua dari {$days} hari sudah dihapus.");

        return self::SUCCESS;
    }
}
