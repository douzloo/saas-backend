<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a gateway is recognized by the platform but has not been
 * implemented yet.
 */
class NotImplementedException extends Exception {}
