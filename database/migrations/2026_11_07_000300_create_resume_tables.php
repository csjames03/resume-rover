<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime_type');
            $table->unsignedInteger('file_size');
            $table->string('file_hash', 64);
            $table->longText('raw_text')->nullable();
            $table->string('parse_status')->default('pending');
            $table->text('parse_error')->nullable();
            $table->string('parser')->nullable();
            $table->timestampTz('parsed_at')->nullable();
            // Bumped on every profile edit; match scores remember the version they used.
            $table->unsignedInteger('profile_version')->default(1);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('resume_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('title')->nullable();
            $table->string('organization')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->text('source_text')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->boolean('is_user_edited')->default(false);
            $table->timestamps();

            $table->index(['resume_id', 'type', 'position']);
        });

        // Bullets get their own rows so tailored drafts can cite exactly which line they came from.
        Schema::create('resume_bullets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_section_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('resume_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->timestamps();

            $table->unique(['resume_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_skill');
        Schema::dropIfExists('resume_bullets');
        Schema::dropIfExists('resume_sections');
        Schema::dropIfExists('resumes');
    }
};
