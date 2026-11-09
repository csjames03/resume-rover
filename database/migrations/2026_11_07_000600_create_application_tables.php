<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tailored_resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('status');
            $table->jsonb('content')->nullable();
            $table->jsonb('flagged_claims')->nullable();
            $table->string('provider')->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['resume_id', 'job_posting_id', 'version']);
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tailored_resume_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->timestampTz('applied_at')->nullable();
            $table->string('next_action')->nullable();
            $table->date('next_action_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'job_posting_id']);
            $table->index(['user_id', 'status']);
        });

        // Append-only history; rows are never updated, so there is no updated_at.
        Schema::create('application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_events');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('tailored_resumes');
    }
};
