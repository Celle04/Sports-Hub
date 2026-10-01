<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->string('coach_type')->default('Head Coach')->after('specialty');
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropColumn('coach_type');
        });
    }
};
