<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('specialty');
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('last_checkup')->nullable();
            $table->string('clearance')->default('Pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('Present');
            $table->date('attended_on');
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->date('published_at')->nullable();
            $table->string('status')->default('Published');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('coaches');
    }
};
