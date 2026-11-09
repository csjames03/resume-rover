<?php

namespace App\Enums;

enum JobSourceStatus: string
{
    use HasValues;

    case Ok = 'ok';
    case Failed = 'failed';
}
