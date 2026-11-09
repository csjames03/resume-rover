<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationEmailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\JobSourceController;
use App\Http\Controllers\MatchExplanationController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\ResumeSectionController;
use App\Http\Controllers\ResumeSkillController;
use App\Http\Controllers\TailoredResumeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Master résumé and the parsed profile
    Route::get('resume', [ResumeController::class, 'show'])->name('resume.show');
    Route::post('resume', [ResumeController::class, 'store'])->middleware('throttle:10,1')->name('resume.store');
    Route::post('resume/{resume}/reparse', [ResumeController::class, 'reparse'])->name('resume.reparse');
    Route::delete('resume/{resume}', [ResumeController::class, 'destroy'])->name('resume.destroy');

    Route::post('resume/sections', [ResumeSectionController::class, 'store'])->name('resume.sections.store');
    Route::patch('resume/sections/{section}', [ResumeSectionController::class, 'update'])->name('resume.sections.update');
    Route::delete('resume/sections/{section}', [ResumeSectionController::class, 'destroy'])->name('resume.sections.destroy');

    Route::post('resume/skills', [ResumeSkillController::class, 'store'])->name('resume.skills.store');
    Route::delete('resume/skills/{skill}', [ResumeSkillController::class, 'destroy'])->name('resume.skills.destroy');

    // Discovered jobs and the user's view of them
    Route::get('jobs', [JobPostingController::class, 'index'])->name('jobs.index');
    Route::get('jobs/{posting}', [JobPostingController::class, 'show'])->name('jobs.show');
    Route::post('jobs/{posting}/explanation', [MatchExplanationController::class, 'store'])->middleware('throttle:20,1')->name('jobs.explanation.store');
    Route::post('jobs/{posting}/application', [ApplicationController::class, 'store'])->name('jobs.application.store');
    Route::post('jobs/{posting}/tailored-resumes', [TailoredResumeController::class, 'store'])->middleware('throttle:10,1')->name('jobs.tailored-resumes.store');

    // Tailored drafts
    Route::get('tailored-resumes/{tailoredResume}', [TailoredResumeController::class, 'show'])->name('tailored-resumes.show');
    Route::patch('tailored-resumes/{tailoredResume}', [TailoredResumeController::class, 'update'])->name('tailored-resumes.update');
    Route::post('tailored-resumes/{tailoredResume}/approve', [TailoredResumeController::class, 'approve'])->name('tailored-resumes.approve');
    Route::get('tailored-resumes/{tailoredResume}/download', [TailoredResumeController::class, 'download'])->name('tailored-resumes.download');

    // Application emails: drafted here, reviewed, then sent through the user's Gmail
    Route::post('jobs/{posting}/application-emails', [ApplicationEmailController::class, 'store'])->middleware('throttle:10,1')->name('jobs.application-emails.store');
    Route::get('application-emails/{applicationEmail}', [ApplicationEmailController::class, 'show'])->name('application-emails.show');
    Route::patch('application-emails/{applicationEmail}', [ApplicationEmailController::class, 'update'])->name('application-emails.update');
    Route::post('application-emails/{applicationEmail}/send', [ApplicationEmailController::class, 'send'])->middleware('throttle:10,1')->name('application-emails.send');
    Route::delete('application-emails/{applicationEmail}', [ApplicationEmailController::class, 'destroy'])->name('application-emails.destroy');

    // Application tracker
    Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::patch('applications/{application}', [ApplicationController::class, 'update'])->name('applications.update');
    Route::post('applications/{application}/archive', [ApplicationController::class, 'archive'])->name('applications.archive');
    Route::post('applications/{application}/restore', [ApplicationController::class, 'restore'])->name('applications.restore');

    // Job sources (shared configuration)
    Route::get('sources', [JobSourceController::class, 'index'])->name('sources.index');
    Route::post('sources', [JobSourceController::class, 'store'])->name('sources.store');
    Route::put('sources/{source}', [JobSourceController::class, 'update'])->name('sources.update');
    Route::delete('sources/{source}', [JobSourceController::class, 'destroy'])->name('sources.destroy');
    Route::post('sources/{source}/fetch', [JobSourceController::class, 'fetch'])->middleware('throttle:10,1')->name('sources.fetch');
});

require __DIR__.'/settings.php';
