<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One Gmail account per user, authorized to send only (gmail.send scope).
        Schema::create('email_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('google');
            $table->string('email');
            // Encrypted at rest through the model's `encrypted` casts.
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->string('scopes', 1000);
            // Set when Google rejects the refresh token (revoked, or expired in testing mode).
            $table->timestampTz('needs_reconnect_at')->nullable();
            $table->timestamps();
        });

        // Drafts live here, not in Gmail, so the app never needs access to the mailbox.
        Schema::create('application_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->boolean('attach_resume')->default(true);
            $table->jsonb('flags')->nullable();
            $table->string('status');
            $table->string('drafted_by')->nullable();
            $table->string('gmail_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'job_posting_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_emails');
        Schema::dropIfExists('email_connections');
    }
};
