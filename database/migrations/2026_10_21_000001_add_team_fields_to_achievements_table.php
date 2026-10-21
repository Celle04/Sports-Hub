<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Team achievements.
     *
     * An achievement is either earned by one athlete or won by a team. Team
     * records have no single athlete account, so the athlete reference becomes
     * optional and the team name is stored next to it.
     */
    public function up(): void
    {
        if (Schema::hasColumn('achievements', 'team_name')) {
            return;
        }

        Schema::table('achievements', function (Blueprint $table) {
            $table->string('team_name')->nullable()->after('athlete_id');
        });

        Schema::table('achievements', function (Blueprint $table) {
            $table->foreignId('athlete_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('achievements', 'team_name')) {
            return;
        }

        Schema::table('achievements', function (Blueprint $table) {
            $table->dropColumn('team_name');
        });

        // The athlete reference only goes back to NOT NULL when no team
        // record is left behind.
        if (! DB::table('achievements')->whereNull('athlete_id')->exists()) {
            Schema::table('achievements', function (Blueprint $table) {
                $table->foreignId('athlete_id')->nullable(false)->change();
            });
        }
    }
};
