<?php

namespace App\Services\Email\Exceptions;

use RuntimeException;

/**
 * Gmail can't be used right now: never connected, or Google revoked access.
 * The message is safe to show the user.
 */
class GmailNotConnected extends RuntimeException {}
