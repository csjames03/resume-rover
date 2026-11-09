<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver');
            $table->string('identifier')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('fetch_interval_minutes')->default(360);
            $table->timestampTz('last_fetched_at')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('last_fetched_count')->nullable();
            $table->text('compliance_notes')->nullable();
            $table->timestamps();

            $table->unique(['driver', 'identifier']);
        });

        // Named job_postings because Laravel's queue already owns the `jobs` table.
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_source_id')->constrained()->restrictOnDelete();
            $table->string('external_id');
            $table->string('fingerprint', 64)->index();
            $table->string('title');
            $table->string('company');
            $table->string('location')->nullable();
            $table->string('remote_type')->default('unknown');
            $table->string('employment_type')->nullable();
            $table->string('salary_text')->nullable();
            $table->longText('description');
            $table->string('description_hash', 64);
            $table->string('url', 2048);
            $table->timestampTz('posted_at')->nullable()->index();
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('closed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['job_source_id', 'external_id']);
        });

        Schema::create('job_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->unsignedTinyInteger('min_years')->nullable();
            $table->text('raw_text');
            $table->string('extractor_version');
            $table->timestamps();

            $table->index(['job_posting_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_requirements');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('job_sources');
    }
};
