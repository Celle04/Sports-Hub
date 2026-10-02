<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_sessions', 'late_grace_minutes')) {
                $table->unsignedSmallInteger('late_grace_minutes')->default(10)->after('end_time');
            }
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->index(['sport_id', 'session_date'], 'attendance_sessions_sport_date_index');
            $table->index(['status', 'session_date'], 'attendance_sessions_status_date_index');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['attendance_session_id', 'status'], 'attendances_session_status_index');
            $table->index('user_id', 'attendances_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_session_status_index');
            $table->dropIndex('attendances_user_id_index');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex('attendance_sessions_sport_date_index');
            $table->dropIndex('attendance_sessions_status_date_index');
            $table->dropColumn('late_grace_minutes');
        });
    }
};
