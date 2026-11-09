<?php

namespace App\Services\Resumes;

use RuntimeException;

/**
 * An expected parsing failure whose message is safe to show the user.
 */
class ResumeParsingFailed extends RuntimeException {}
