<?php

namespace App\Http\Controllers;

use App\Enums\AiTask;
use App\Enums\ApplicationStatus;
use App\Enums\JobSourceStatus;
use App\Enums\MatchCategory;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\JobPostingResource;
use App\Models\JobSource;
use App\Queries\JobPostingSearch;
use App\Services\Ai\AiGateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, JobPostingSearch $search, AiGateway $gateway): Response
    {
        $user = $request->user();
        $resume = $user->activeResume;

        $matchCounts = $user->jobMatches()
            ->whereHas('posting', fn (Builder $q) => $q->open())
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $pipeline = $user->applications()->active()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('dashboard', [
            'resume' => $resume ? [
                'filename' => $resume->original_filename,
                'parse_status' => $resume->parse_status->value,
                'skills_count' => $resume->skills()->count(),
            ] : null,
            'matchCounts' => collect(MatchCategory::cases())->map(fn (MatchCategory $c) => [
                'category' => $c->value,
                'label' => $c->label(),
                'total' => (int) ($matchCounts[$c->value] ?? 0),
            ]),
            'topMatches' => JobPostingResource::collection(
                $search->query($user, ['status' => 'untracked'])
                    ->whereHas('matches', fn (Builder $q) => $q->where('user_id', $user->id)->where('category', '!=', MatchCategory::Low))
                    ->limit(6)
                    ->get(),
            ),
            'pipeline' => collect(ApplicationStatus::cases())->map(fn (ApplicationStatus $s) => [
                'status' => $s->value,
                'label' => $s->label(),
                'total' => (int) ($pipeline[$s->value] ?? 0),
            ]),
            'upcoming' => ApplicationResource::collection(
                $user->applications()->active()->with('posting')
                    ->whereNotNull('next_action_at')
                    ->orderBy('next_action_at')
                    ->limit(5)
                    ->get(),
            ),
            'sources' => [
                'total' => JobSource::query()->enabled()->count(),
                'failing' => JobSource::query()->enabled()->where('last_status', JobSourceStatus::Failed)->pluck('name'),
            ],
            'aiEnabled' => $gateway->isEnabled(AiTask::ExplainMatch),
        ]);
    }
}
