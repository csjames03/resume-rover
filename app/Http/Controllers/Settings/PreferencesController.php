<?php

namespace App\Http\Controllers\Settings;

use App\Enums\RemotePreference;
use App\Enums\Seniority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PreferencesUpdateRequest;
use App\Jobs\ScoreMatches;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreferencesController extends Controller
{
    public function edit(Request $request): Response
    {
        $preference = $request->user()->preferenceOrDefault();

        return Inertia::render('settings/preferences', [
            'preferences' => [
                'target_titles' => $preference->target_titles ?? [],
                'locations' => $preference->locations ?? [],
                'remote_preference' => $preference->remote_preference->value,
                'seniority' => $preference->seniority?->value,
                'excluded_keywords' => $preference->excluded_keywords ?? [],
            ],
            'remoteOptions' => RemotePreference::options(),
            'seniorityOptions' => Seniority::options(),
        ]);
    }

    public function update(PreferencesUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->preference()->updateOrCreate(['user_id' => $user->id], $request->preferences());
        ScoreMatches::dispatch($user->id);

        $this->toast('Preferences saved. Re-scoring your matches…');

        return back();
    }
}
