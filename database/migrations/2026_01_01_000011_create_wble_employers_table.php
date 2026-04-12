<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wble_employers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->constrained()->cascadeOnDelete();

            $table->string('employer_name');
            $table->text('employer_address');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('ein')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('crp_id');
            $table->index(['crp_id', 'is_active']);
        });

        // Composite unique on wble_employers to support composite FKs downstream
        DB::statement('
            ALTER TABLE wble_employers ADD CONSTRAINT wble_employers_id_crp_unique UNIQUE (id, crp_id)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('wble_employers');
    }
};
