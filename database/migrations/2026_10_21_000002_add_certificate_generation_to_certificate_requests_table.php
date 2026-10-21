<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Certificate generation details for a request.
     *
     * The PDF is generated after the request is approved, so the moment it was
     * generated and the reference printed on it are kept with the request.
     * The request status still walks Pending -> Approved -> Issued, where
     * Issued means the certificate is ready for release.
     */
    public function up(): void
    {
        if (Schema::hasColumn('certificate_requests', 'certificate_generated_at')) {
            return;
        }

        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->timestamp('certificate_generated_at')->nullable()->after('certificate_path');
            // The reference identifies the achievement's certificate, so the
            // same achievement keeps its reference when it is re-issued.
            $table->string('certificate_reference')->nullable()->index()->after('certificate_generated_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('certificate_requests', 'certificate_generated_at')) {
            return;
        }

        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropIndex(['certificate_reference']);
            $table->dropColumn(['certificate_generated_at', 'certificate_reference']);
        });
    }
};
