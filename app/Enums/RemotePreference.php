<?php

namespace App\Enums;

enum RemotePreference: string
{
    use HasValues;

    case Remote = 'remote';
    case Hybrid = 'hybrid';
    case Onsite = 'onsite';
    case Any = 'any';
}
