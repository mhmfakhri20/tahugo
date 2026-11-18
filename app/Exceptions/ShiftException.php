<?php

namespace App\Exceptions;

/**
 * A shift rule was violated (SHF-1..5, BR-4, BR-5, BR-6). The message is user facing.
 */
class ShiftException extends BusinessRuleException {}
