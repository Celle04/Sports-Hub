<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            if (! Schema::hasColumn('medical_records', 'athlete_id')) {
                $table->foreignId('athlete_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('medical_records', 'examination_date')) {
                $table->date('examination_date')->nullable()->after('athlete_id');
            }

            if (! Schema::hasColumn('medical_records', 'medical_status')) {
                $table->string('medical_status')->default('Pending')->after('examination_date');
            }

            if (! Schema::hasColumn('medical_records', 'next_checkup_date')) {
                $table->date('next_checkup_date')->nullable()->after('medical_status');
            }

            if (! Schema::hasColumn('medical_records', 'findings')) {
                $table->text('findings')->nullable()->after('next_checkup_date');
            }

            if (! Schema::hasColumn('medical_records', 'restrictions')) {
                $table->text('restrictions')->nullable()->after('findings');
            }

            if (! Schema::hasColumn('medical_records', 'medical_certificate')) {
                $table->string('medical_certificate')->nullable()->after('restrictions');
            }

            try {
                $table->dropForeign(['user_id']);
            } catch (\Throwable $exception) {
                // Ignore missing foreign key for legacy records.
            }

            try {
                $table->dropUnique(['user_id']);
            } catch (\Throwable $exception) {
                // Ignore missing unique index for legacy records.
            }
        });

        DB::table('medical_records')
            ->whereNotNull('user_id')
            ->whereNull('athlete_id')
            ->update(['athlete_id' => DB::raw('user_id')]);

        DB::table('medical_records')
            ->whereNull('medical_status')
            ->whereNotNull('clearance')
            ->update(['medical_status' => DB::raw('clearance')]);

        DB::table('medical_records')
            ->whereNull('examination_date')
            ->whereNotNull('last_checkup')
            ->update(['examination_date' => DB::raw('last_checkup')]);
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            if (Schema::hasColumn('medical_records', 'athlete_id')) {
                $table->dropConstrainedForeignId('athlete_id');
            }

            if (Schema::hasColumn('medical_records', 'examination_date')) {
                $table->dropColumn('examination_date');
            }

            if (Schema::hasColumn('medical_records', 'medical_status')) {
                $table->dropColumn('medical_status');
            }

            if (Schema::hasColumn('medical_records', 'next_checkup_date')) {
                $table->dropColumn('next_checkup_date');
            }

            if (Schema::hasColumn('medical_records', 'findings')) {
                $table->dropColumn('findings');
            }

            if (Schema::hasColumn('medical_records', 'restrictions')) {
                $table->dropColumn('restrictions');
            }

            if (Schema::hasColumn('medical_records', 'medical_certificate')) {
                $table->dropColumn('medical_certificate');
            }
        });
    }
};
