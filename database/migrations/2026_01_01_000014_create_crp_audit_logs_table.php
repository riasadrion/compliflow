<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crp_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action');           // login, logout, read, create, update, export_pdf, phi_export
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();

            $table->enum('classification', [
                'security', 'compliance', 'operational',
            ])->default('operational');

            // Cryptographic hash chain
            $table->string('previous_hash', 64)->nullable();
            $table->string('current_hash', 64)->nullable();
            $table->unsignedBigInteger('sequence')->nullable();

            $table->timestamp('created_at')->useCurrent();
            // No updated_at — immutable records

            $table->index('crp_id');
            $table->index(['crp_id', 'action']);
            $table->index(['crp_id', 'entity_type', 'entity_id']);
            $table->index('sequence');
        });

        // Genesis record — seed for every fresh install
        // Per-CRP genesis records are inserted by the CRP seeder / observer
    }

    public function down(): void
    {
        Schema::dropIfExists('crp_audit_logs');
    }
};
