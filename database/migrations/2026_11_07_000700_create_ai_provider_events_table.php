<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Operational metadata only. Never store prompts, résumé text or model output here.
        Schema::create('ai_provider_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model');
            $table->string('task');
            $table->string('outcome');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_events');
    }
};
