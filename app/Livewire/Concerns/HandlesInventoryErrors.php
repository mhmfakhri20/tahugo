<?php

namespace App\Livewire\Concerns;

use App\Exceptions\BusinessRuleException;
use Illuminate\Validation\ValidationException;

/**
 * Turns business-rule errors (inventory, shift, attendance; Bahasa Indonesia) into a validation error on a field,
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
        } catch (BusinessRuleException $e) {
            throw ValidationException::withMessages([$errorKey => $e->getMessage()]);
        }
    }
}
