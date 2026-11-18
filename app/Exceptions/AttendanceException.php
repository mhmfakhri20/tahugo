<?php

namespace App\Exceptions;

/**
 * An attendance (clock in/out) was rejected, e.g. outside the outlet radius (BR-5).
 * The message is user facing (Bahasa Indonesia) and explains how to fix it (NFR-1).
 */
class AttendanceException extends BusinessRuleException {}
