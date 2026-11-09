<?php

namespace App\Enums;

enum EmploymentType: string
{
    use HasValues;

    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Internship = 'internship';
    case Freelance = 'freelance';

    /**
     * Map a source's wording ("Full-time", "full_time", "Contractor") to a type.
     */
    public static function infer(?string $value): ?self
    {
        $text = mb_strtolower((string) $value);

        return match (true) {
            str_contains($text, 'intern') => self::Internship,
            str_contains($text, 'part') => self::PartTime,
            str_contains($text, 'freelance') => self::Freelance,
            (bool) preg_match('/contract|temporary|temp\b|fixed[\s-]term/', $text) => self::Contract,
            str_contains($text, 'full') || str_contains($text, 'permanent') => self::FullTime,
            default => null,
        };
    }
}
