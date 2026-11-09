<?php

namespace App\Enums;

enum Seniority: string
{
    use HasValues;

    case Intern = 'intern';
    case Junior = 'junior';
    case Mid = 'mid';
    case Senior = 'senior';
    case Lead = 'lead';
}
