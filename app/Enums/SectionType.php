<?php

namespace App\Enums;

enum SectionType: string
{
    use HasValues;

    case Summary = 'summary';
    case Experience = 'experience';
    case Project = 'project';
    case Education = 'education';
    case Certification = 'certification';
    case Link = 'link';
    case Other = 'other';

    /**
     * Whether entries of this type count toward years of experience.
     */
    public function countsAsExperience(): bool
    {
        return $this === self::Experience;
    }
}
