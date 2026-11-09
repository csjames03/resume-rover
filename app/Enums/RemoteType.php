<?php

namespace App\Enums;

enum RemoteType: string
{
    use HasValues;

    case Remote = 'remote';
    case Hybrid = 'hybrid';
    case Onsite = 'onsite';
    case Unknown = 'unknown';

    /**
     * Guess the arrangement from free-text hints such as a location or title.
     */
    public static function infer(?string ...$hints): self
    {
        $text = mb_strtolower(implode(' ', array_filter($hints)));

        return match (true) {
            str_contains($text, 'hybrid') => self::Hybrid,
            str_contains($text, 'remote'), str_contains($text, 'anywhere'), str_contains($text, 'worldwide') => self::Remote,
            (bool) preg_match('/on[\s-]?site|in[\s-]office/', $text) => self::Onsite,
            default => self::Unknown,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Remote => 'Remote',
            self::Hybrid => 'Hybrid',
            self::Onsite => 'On-site',
            self::Unknown => 'Not stated',
        };
    }
}
