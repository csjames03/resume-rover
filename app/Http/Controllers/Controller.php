<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

abstract class Controller
{
    /**
     * Show a toast on the next page load.
     *
     * @param  'success'|'info'|'warning'|'error'  $type
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
