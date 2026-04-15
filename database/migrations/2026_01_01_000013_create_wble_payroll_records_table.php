<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wble_payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wble_employer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wble_placement_id')->constrained()->cascadeOnDelete();

            $table->date('pay_period_start');
            $table->date('pay_period_end');
            $table->decimal('hours_worked', 6, 2);
            $table->decimal('wage_rate', 8, 2);
            $table->decimal('gross_wages', 10, 2);
            $table->decimal('reimbursement_amount', 10, 2)->nullable();

            $table->date('pay_date');
            $table->date('reimbursement_deadline')->nullable();
            $table->timestamp('employer_signature_date')->nullable();

            $table->enum('deadline_status', [
                'on_track', 'warning', 'critical', 'overdue',
            ])->default('on_track');

            $table->enum('reimbursement_status', [
                'pending', 'submitted', 'paid',
            ])->default('pending');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('crp_id');
            $table->index('client_id');
            $table->index(['crp_id', 'deadline_status']);
            $table->index(['crp_id', 'reimbursement_status']);
        });

        // Composite FKs: client and employer must belong to same CRP
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
            ALTER TABLE wble_payroll_records
                DROP CONSTRAINT IF EXISTS wble_payroll_records_client_id_foreign,
                ADD CONSTRAINT wble_payroll_records_client_id_crp_fk
                    FOREIGN KEY (client_id, crp_id)
                    REFERENCES clients(id, crp_id)
        ');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
            ALTER TABLE wble_payroll_records
                DROP CONSTRAINT IF EXISTS wble_payroll_records_wble_employer_id_foreign,
                ADD CONSTRAINT wble_payroll_records_wble_employer_id_crp_fk
                    FOREIGN KEY (wble_employer_id, crp_id)
                    REFERENCES wble_employers(id, crp_id)
        ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wble_payroll_records');
    }
};
