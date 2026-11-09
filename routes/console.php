<?php

use Illuminate\Support\Facades\Schedule;

// Each source also enforces its own minimum interval (see JobSource::isDue).
Schedule::command('postings:fetch --due')->hourly()->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=168')->daily();
