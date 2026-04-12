<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->string('authorization_number');
            $table->string('service_code');
            $table->string('service_type');

            $table->date('start_date');
            $table->date('end_date');

            $table->integer('authorized_units');
            $table->integer('units_used')->default(0);

            $table->string('vrc_name')->nullable();
            $table->string('vrc_email')->nullable();
            $table->string('district_office')->nullable();

            $table->enum('status', [
                'pending', 'active', 'expired', 'exhausted', 'terminated',
            ])->default('pending');

            $table->timestamp('last_unit_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('crp_id');
            $table->index('client_id');
            $table->index(['crp_id', 'status']);
            $table->index(['end_date', 'status']);
            $table->unique(['authorization_number', 'crp_id']);
        });

        // Composite FK: client must belong to same CRP
        // clients(id, crp_id) unique constraint is set in the clients migration
        DB::statement('
            ALTER TABLE authorizations
                DROP CONSTRAINT IF EXISTS authorizations_client_id_foreign,
                ADD CONSTRAINT authorizations_client_id_crp_fk
                    FOREIGN KEY (client_id, crp_id)
                    REFERENCES clients(id, crp_id)
        ');

        // Composite unique on authorizations to support composite FK from service_groups
        DB::statement('
            ALTER TABLE authorizations ADD CONSTRAINT authorizations_id_crp_unique UNIQUE (id, crp_id)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('authorizations');
    }
};
