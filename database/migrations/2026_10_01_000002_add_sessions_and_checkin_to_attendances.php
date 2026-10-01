<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('attendance_session_id')->nullable()->after('id')->constrained('attendance_sessions')->nullOnDelete();
            $table->timestamp('check_in_time')->nullable()->after('attended_on');
            $table->unique(['attendance_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique(['attendance_session_id', 'user_id']);
            $table->dropConstrainedForeignId('attendance_session_id');
            $table->dropColumn('check_in_time');
        });
    }
};
