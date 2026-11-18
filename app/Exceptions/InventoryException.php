<?php

namespace App\Exceptions;

/**
 * A business rule of the inventory flow was violated (e.g. illegal status transition,
 * not enough stock). The message is user facing (Bahasa Indonesia).
 */
class InventoryException extends BusinessRuleException {}
