<?php

namespace App\Enums;

enum ResumeParseStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Processing = 'processing';
    case Parsed = 'parsed';
    case Failed = 'failed';
}
