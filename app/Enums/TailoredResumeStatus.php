<?php

namespace App\Enums;

enum TailoredResumeStatus: string
{
    use HasValues;

    case Generating = 'generating';
    case Draft = 'draft';
    case Approved = 'approved';
    case Failed = 'failed';
}
