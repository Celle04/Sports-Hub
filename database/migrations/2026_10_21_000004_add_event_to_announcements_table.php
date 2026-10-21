<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link an announcement to the event it announces.
     *
     * The landing page publishes an announcement whenever an event is
     * scheduled, and the link lets the announcement point at the event it
     * belongs to and hides itself when the event is called off.
     */
    public function up(): void
    {
        if (Schema::hasColumn('announcements', 'event_id')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('body')->constrained('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('announcements', 'event_id')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
