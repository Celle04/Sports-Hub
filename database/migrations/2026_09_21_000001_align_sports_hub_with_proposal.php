<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('student_id')->nullable()->after('name');
            $table->string('gender', 30)->nullable()->after('grade');
            $table->foreignId('sport_id')->nullable()->after('sport')->constrained('sports')->nullOnDelete();
            $table->string('medical_certificate_path')->nullable();
            $table->string('birth_certificate_path')->nullable();
            $table->string('parent_consent_path')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
            $table->string('student_id')->nullable()->unique()->after('sport_id');
            $table->string('phone', 50)->nullable()->after('student_id');
            $table->string('profile_photo_path')->nullable()->after('phone');
        });

        Schema::table('coaches', function (Blueprint $table) {
            $table->foreignId('sport_id')->nullable()->after('specialty')->constrained('sports')->nullOnDelete();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('sport_id')->nullable()->after('body')->constrained('sports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', fn (Blueprint $table) => $table->dropConstrainedForeignId('sport_id'));
        Schema::table('coaches', fn (Blueprint $table) => $table->dropConstrainedForeignId('sport_id'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['student_id']);
            $table->dropColumn(['username', 'student_id', 'phone', 'profile_photo_path']);
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sport_id');
            $table->dropColumn(['student_id', 'gender', 'medical_certificate_path', 'birth_certificate_path', 'parent_consent_path']);
        });
    }
};
