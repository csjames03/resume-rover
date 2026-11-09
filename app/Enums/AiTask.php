<?php

namespace App\Enums;

/**
 * Each AI feature is a task, so providers and models can be routed per task.
 */
enum AiTask: string
{
    use HasValues;

    case ParseResume = 'parse_resume';
    case ExplainMatch = 'explain_match';
    case TailorResume = 'tailor_resume';
    case DraftEmail = 'draft_email';
}
