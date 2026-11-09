<?php

namespace App\Enums;

enum SkillCategory: string
{
    use HasValues;

    case Language = 'language';
    case Framework = 'framework';
    case Database = 'database';
    case Cloud = 'cloud';
    case Tool = 'tool';
    case Practice = 'practice';
    case Soft = 'soft';
}
