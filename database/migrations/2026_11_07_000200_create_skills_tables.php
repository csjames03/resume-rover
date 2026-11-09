<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('category');
            // False for names that are ordinary words or letters ("Go", "C", "Excel"):
            // those skills are only detected through their unambiguous aliases.
            $table->boolean('match_by_name')->default(true);
            $table->timestamps();
        });

        Schema::create('skill_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('alias')->unique(); // always lowercase
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_aliases');
        Schema::dropIfExists('skills');
    }
};
