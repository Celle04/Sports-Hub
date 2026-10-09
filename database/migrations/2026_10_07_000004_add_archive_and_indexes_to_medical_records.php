<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports the Medical module's archive action and its list filters.
 *
 * Records are archived instead of deleted by default so a student keeps their
 * medical history; the indexes back the status, examination date, expiration
 * and archive filters used by the records table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            if (! Schema::hasColumn('medical_records', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('notes');
            }
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->index('medical_status');
            $table->index('examination_date');
            $table->index('next_checkup_date');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex(['medical_status']);
            $table->dropIndex(['examination_date']);
            $table->dropIndex(['next_checkup_date']);
            $table->dropIndex(['archived_at']);
        });

        Schema::table('medical_records', function (Blueprint $table) {
            if (Schema::hasColumn('medical_records', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
