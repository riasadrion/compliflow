<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('crp_id');
            $table->unique(['external_id', 'crp_id'], 'clients_external_id_crp_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_external_id_crp_unique');
            $table->dropColumn('external_id');
        });
    }
};
