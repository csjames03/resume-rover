<?php

namespace App\Enums;

enum SkillSource: string
{
    use HasValues;

    case Parsed = 'parsed';
    case Manual = 'manual';
}
