<?php

namespace App\Livewire\Concerns;

use App\Exceptions\InventoryException;
use Illuminate\Validation\ValidationException;

/**
 * Turns inventory business-rule errors (Bahasa Indonesia) into a validation error on a field,
 * so the message appears right under the related input (NFR-1).
 */
trait HandlesInventoryErrors
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws ValidationException
     */
    protected function guardInventory(callable $callback, string $errorKey = 'inventory'): mixed
    {
        try {
            return $callback();
        } catch (InventoryException $e) {
            throw ValidationException::withMessages([$errorKey => $e->getMessage()]);
        }
    }
}
