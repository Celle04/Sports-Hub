<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('medical_document_status')->default('Submitted')->after('parent_consent_path');
            $table->string('birth_document_status')->default('Submitted')->after('medical_document_status');
            $table->string('consent_document_status')->default('Submitted')->after('birth_document_status');
            $table->text('rejection_reason')->nullable()->after('status');
            $table->text('review_notes')->nullable()->after('rejection_reason');
            $table->foreignId('reviewed_by')->nullable()->after('review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->json('documents_requested')->nullable()->after('reviewed_at');
            $table->json('document_rejection_notes')->nullable()->after('documents_requested');
            $table->json('review_history')->nullable()->after('document_rejection_notes');
            $table->foreignId('athlete_id')->nullable()->after('review_history')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['athlete_id']);
            $table->dropColumn(['medical_document_status', 'birth_document_status', 'consent_document_status', 'rejection_reason', 'review_notes', 'reviewed_by', 'reviewed_at', 'documents_requested', 'document_rejection_notes', 'review_history', 'athlete_id']);
        });
    }
};
