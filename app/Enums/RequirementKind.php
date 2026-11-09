<?php

namespace App\Enums;

enum RequirementKind: string
{
    use HasValues;

    case MustHave = 'must_have';
    case NiceToHave = 'nice_to_have';
}
