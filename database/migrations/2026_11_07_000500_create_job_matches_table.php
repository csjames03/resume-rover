<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('resume_profile_version');
            $table->unsignedTinyInteger('score');
            $table->string('category');
            $table->jsonb('strengths');
            $table->jsonb('gaps');
            $table->string('scoring_version');
            $table->timestampTz('scored_at');
            $table->text('explanation')->nullable();
            $table->string('explanation_status')->nullable();
            $table->timestampTz('explained_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'job_posting_id']);
            $table->index(['user_id', 'category', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_matches');
    }
};
