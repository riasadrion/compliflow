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
        Schema::table('users', function (Blueprint $table) {
            // Super admins are not scoped to a CRP — crp_id becomes nullable
            $table->boolean('is_super_admin')->default(false)->after('role');
            $table->foreignId('crp_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
            $table->foreignId('crp_id')->nullable(false)->change();
        });
    }
};
