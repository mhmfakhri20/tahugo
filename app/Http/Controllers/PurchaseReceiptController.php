<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a purchase receipt photo from the private disk (NFR-4).
 */
class PurchaseReceiptController extends Controller
{
    public function __invoke(Purchase $purchase): StreamedResponse
    {
        Gate::authorize('view', $purchase);

        $disk = Storage::disk(PurchaseService::RECEIPT_DISK);

        abort_if($purchase->receipt_photo_path === null || ! $disk->exists($purchase->receipt_photo_path), 404);

        return $disk->response($purchase->receipt_photo_path, headers: ['Cache-Control' => 'private, max-age=3600']);
    }
}
