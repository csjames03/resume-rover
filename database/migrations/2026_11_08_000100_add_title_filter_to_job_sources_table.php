<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_sources', function (Blueprint $table) {
            // Comma-separated words; when set, only postings whose title contains one are kept.
            $table->string('title_filter', 500)->nullable()->after('identifier');
        });
    }

    public function down(): void
    {
        Schema::table('job_sources', function (Blueprint $table) {
            $table->dropColumn('title_filter');
        });
    }
};
