<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wble_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wble_employer_id')->constrained()->cascadeOnDelete();

            $table->string('job_title');
            $table->text('job_duties')->nullable();
            $table->date('planned_start_date');
            $table->date('actual_start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamp('district_notice_sent_at')->nullable();

            $table->enum('status', [
                'pending', 'active', 'completed', 'terminated',
            ])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->index('crp_id');
            $table->index('client_id');
            $table->index('wble_employer_id');
        });

        // Composite FKs: client and employer must belong to same CRP
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
            ALTER TABLE wble_placements
                DROP CONSTRAINT IF EXISTS wble_placements_client_id_foreign,
                ADD CONSTRAINT wble_placements_client_id_crp_fk
                    FOREIGN KEY (client_id, crp_id)
                    REFERENCES clients(id, crp_id)
        ');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
            ALTER TABLE wble_placements
                DROP CONSTRAINT IF EXISTS wble_placements_wble_employer_id_foreign,
                ADD CONSTRAINT wble_placements_wble_employer_id_crp_fk
                    FOREIGN KEY (wble_employer_id, crp_id)
                    REFERENCES wble_employers(id, crp_id)
        ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wble_placements');
    }
};
