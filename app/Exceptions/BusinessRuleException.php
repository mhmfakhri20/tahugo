<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule was violated. The message is user facing (Bahasa Indonesia) and is shown
 * right under the related input by `HandlesInventoryErrors::guardInventory()`.
 */
class BusinessRuleException extends RuntimeException {}
