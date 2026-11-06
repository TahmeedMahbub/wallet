<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A form was submitted twice (double tap or replay). The second submission is
 * ignored because the first one already did the work.
 */
class DuplicateSubmission extends RuntimeException {}
