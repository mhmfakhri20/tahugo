<?php

namespace App\Http\Controllers;

use App\Models\CashEntry;
use App\Services\CashService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an expense receipt photo from the private disk (CASH-1, CASH-4, NFR-4).
 */
class CashReceiptController extends Controller
{
    public function __invoke(CashEntry $cashEntry): StreamedResponse
    {
        Gate::authorize('viewReceipt', $cashEntry);

        $disk = Storage::disk(CashService::RECEIPT_DISK);

        abort_if($cashEntry->receipt_photo_path === null || ! $disk->exists($cashEntry->receipt_photo_path), 404);

        return $disk->response($cashEntry->receipt_photo_path, headers: ['Cache-Control' => 'private, max-age=3600']);
    }
}
