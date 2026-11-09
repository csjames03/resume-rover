<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    use HasValues;

    case Saved = 'saved';
    case Preparing = 'preparing';
    case Applied = 'applied';
    case Interviewing = 'interviewing';
    case Offer = 'offer';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * Whether the opportunity is still in play.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Rejected, self::Withdrawn], true);
    }
}
