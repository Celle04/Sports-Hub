<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Injury and medical incident log for the Medical module.
 *
 * An incident belongs to an athlete (users table, the same athlete_id
 * convention used by medical_records, applications and achievements) so it can
 * be shown inside the athlete's medical profile without duplicating the
 * athlete, sport or coach tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('medical_incidents')) {
            return;
        }

        Schema::create('medical_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained('sports')->nullOnDelete();
            $table->date('incident_date');
            $table->string('activity')->nullable();
            $table->string('injury_type');
            $table->string('body_part')->nullable();
            $table->string('severity')->default('Mild');
            $table->text('description')->nullable();
            $table->text('treatment')->nullable();
            $table->unsignedSmallInteger('rest_period_days')->nullable();
            $table->date('return_to_play_date')->nullable();
            $table->string('medical_clearance')->default('Pending');
            $table->timestamps();

            $table->index(['athlete_id', 'incident_date']);
            $table->index('incident_date');
            $table->index('medical_clearance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_incidents');
    }
};
