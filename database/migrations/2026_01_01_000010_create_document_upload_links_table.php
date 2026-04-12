<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_upload_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->string('token')->unique();

            $table->enum('document_type', [
                'proof_of_disability', 'iep', 'consent_form',
            ]);

            $table->enum('status', ['pending', 'uploaded', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('uploaded_at')->nullable();

            // Reminder tracking
            $table->timestamp('reminder_day1_sent_at')->nullable();
            $table->timestamp('reminder_day3_sent_at')->nullable();
            $table->timestamp('reminder_day7_sent_at')->nullable();

            $table->timestamps();

            $table->index('token');
            $table->index(['client_id', 'status']);
            $table->index(['crp_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_upload_links');
    }
};
