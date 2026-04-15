<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('authorization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('curriculum_id')->nullable()->constrained('curricula')->nullOnDelete();

            $table->string('service_code');
            $table->string('form_type');
            $table->date('service_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('units');

            $table->enum('report_status', [
                'draft', 'ready', 'submitted', 'approved', 'rejected',
            ])->default('draft');

            $table->enum('report_type', [
                'initial', 'midpoint', 'conclusion',
            ])->nullable();

            $table->timestamp('ready_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            // Locking — immutable once set
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();

            // PHI — encrypted at rest via model cast
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();

            $table->timestamp('last_billed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('crp_id');
            $table->index('client_id');
            $table->index('authorization_id');
            $table->index('service_group_id');
            $table->index('service_date');
            $table->index(['crp_id', 'service_date']);
            $table->index(['service_date', 'start_time', 'end_time']);
        });

        // Composite FK: client must belong to same CRP
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
            ALTER TABLE service_logs
                DROP CONSTRAINT IF EXISTS service_logs_client_id_foreign,
                ADD CONSTRAINT service_logs_client_id_crp_fk
                    FOREIGN KEY (client_id, crp_id)
                    REFERENCES clients(id, crp_id)
        ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_logs');
    }
};
