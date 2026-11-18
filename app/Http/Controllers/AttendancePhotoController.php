<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an attendance selfie from the private disk (ATT-4, NFR-4/5).
 */
class AttendancePhotoController extends Controller
{
    public function __invoke(Attendance $attendance): StreamedResponse
    {
        Gate::authorize('viewPhoto', $attendance);

        $disk = Storage::disk(AttendanceService::PHOTO_DISK);

        // Pruned after the retention period (NFR-5) → 404.
        abort_if($attendance->photo_path === null || ! $disk->exists($attendance->photo_path), 404);

        return $disk->response($attendance->photo_path, headers: ['Cache-Control' => 'private, max-age=3600']);
    }
}
