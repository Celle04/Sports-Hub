<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_type')->default('Other')->after('sport_id');
            $table->foreignId('coach_id')->nullable()->after('event_type')->constrained('coaches')->nullOnDelete();
            $table->string('team_name')->nullable()->after('coach_id');
            $table->unsignedInteger('max_participants')->nullable()->after('team_name');
            $table->text('notes')->nullable()->after('max_participants');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['coach_id']);
            $table->dropColumn(['event_type', 'coach_id', 'team_name', 'max_participants', 'notes']);
        });
    }
};
