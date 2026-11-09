<?php

namespace App\Enums;

enum MatchCategory: string
{
    use HasValues;

    case Strong = 'strong';
    case Relevant = 'relevant';
    case Low = 'low';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= (int) config('matching.bands.strong') => self::Strong,
            $score >= (int) config('matching.bands.relevant') => self::Relevant,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Strong => 'Strong fit',
            self::Relevant => 'Relevant',
            self::Low => 'Low match',
        };
    }
}
