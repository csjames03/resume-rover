<?php

namespace App\Services\Email\Exceptions;

use RuntimeException;

/**
 * Sending failed for a reason the user can act on. The message is safe to show.
 */
class EmailNotSent extends RuntimeException {}
