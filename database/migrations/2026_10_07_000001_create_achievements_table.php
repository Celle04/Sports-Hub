<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('achievements')) {
            return;
        }

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();

            // Athletes are accounts in the users table; athlete_id matches the
            // convention already used by medical_records and applications.
            $table->foreignId('athlete_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained('sports')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('achievement_type');
            $table->string('competition')->nullable();
            $table->string('place')->nullable();
            $table->date('date_achieved');
            $table->string('certificate_path')->nullable();

            $table->timestamps();

            $table->index(['athlete_id', 'date_achieved']);
            $table->index('achievement_type');
            $table->index('sport_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};