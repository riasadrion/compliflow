<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->constrained()->cascadeOnDelete();

            // PHI — encrypted at rest via model casts
            $table->string('first_name');
            $table->string('last_name');
            $table->string('dob');
            $table->string('ssn_last_four')->nullable();

            // Contact
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Eligibility
            $table->enum('eligibility_status', [
                'pending', 'potentially_eligible', 'eligible', 'ineligible',
            ])->default('pending');

            // Document tracking
            $table->timestamp('proof_of_disability_received_at')->nullable();
            $table->string('proof_of_disability_file_path')->nullable();
            $table->timestamp('iep_received_at')->nullable();
            $table->string('iep_file_path')->nullable();
            $table->timestamp('consent_form_signed_at')->nullable();
            $table->string('consent_form_file_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('crp_id');
            $table->index(['crp_id', 'last_name']);
            $table->index(['crp_id', 'eligibility_status']);

            // Required for composite FK references from authorizations, service_logs, etc.
            $table->unique(['id', 'crp_id'], 'clients_id_crp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
