<?php

namespace App\Enums;

enum EmailStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Sent = 'sent';
    case Failed = 'failed';

    /**
     * Failed sends stay editable so the user can fix and retry.
     */
    public function isEditable(): bool
    {
        return $this !== self::Sent;
    }
}
