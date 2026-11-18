<?php

namespace App\Exceptions;

/**
 * A cash-flow rule was violated (CASH-1..7, BR-4, BR-6). The message is user facing (Bahasa Indonesia).
 */
class CashException extends BusinessRuleException {}
