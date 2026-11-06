<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The backup file cannot be restored; nothing was changed.
 */
class InvalidBackup extends RuntimeException {}
