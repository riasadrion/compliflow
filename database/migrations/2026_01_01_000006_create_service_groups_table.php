<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('authorization_id')->constrained()->cascadeOnDelete();

            $table->string('group_name');
            $table->date('session_date');
            $table->string('form_type');

            $table->enum('status', [
                'draft', 'ready', 'submitted', 'approved', 'rejected',
            ])->default('draft');

            $table->timestamps();
            $table->softDeletes();

            $table->index('crp_id');
            $table->index(['crp_id', 'session_date']);
        });

        // Composite FK: authorization must belong to same CRP
        // authorizations(id, crp_id) unique constraint is set in the authorizations migration
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
            ALTER TABLE service_groups
                DROP CONSTRAINT IF EXISTS service_groups_authorization_id_foreign,
                ADD CONSTRAINT service_groups_authorization_id_crp_fk
                    FOREIGN KEY (authorization_id, crp_id)
                    REFERENCES authorizations(id, crp_id)
        ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_groups');
    }
};
