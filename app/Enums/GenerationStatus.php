<?php

namespace App\Enums;

/**
 * Lifecycle of a piece of AI-generated text stored on a record.
 */
enum GenerationStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
