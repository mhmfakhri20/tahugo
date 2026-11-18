<?php

namespace App\Exceptions;

/**
 * A POS rule was violated (POS-1..8, BR-4, BR-6). The message is user facing (Bahasa Indonesia).
 */
class SaleException extends BusinessRuleException {}
