<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a shift closing photo (outlet / cart condition) from the private disk (SHF-3, NFR-4).
 */
class ShiftPhotoController extends Controller
{
    public function __invoke(Shift $shift): StreamedResponse
    {
        Gate::authorize('viewPhoto', $shift);

        $disk = Storage::disk(ShiftService::PHOTO_DISK);

        abort_if($shift->closing_photo_path === null || ! $disk->exists($shift->closing_photo_path), 404);

        return $disk->response($shift->closing_photo_path, headers: ['Cache-Control' => 'private, max-age=3600']);
    }
}
