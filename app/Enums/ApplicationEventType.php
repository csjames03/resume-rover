<?php

namespace App\Enums;

enum ApplicationEventType: string
{
    use HasValues;

    case Created = 'created';
    case StatusChanged = 'status_changed';
    case NoteUpdated = 'note_updated';
    case TailoredResumeAttached = 'tailored_resume_attached';
    case Archived = 'archived';
    case Restored = 'restored';
    case EmailSent = 'email_sent';
}
