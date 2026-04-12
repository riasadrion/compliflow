<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description');
            $table->json('standards_alignment')->nullable();

            $table->enum('status', [
                'draft', 'pending_approval', 'approved', 'expired', 'revoked',
            ])->default('draft');

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('approved_by')->nullable();

            $table->string('service_code');

            $table->timestamps();
            $table->softDeletes();

            $table->index('crp_id');
            $table->index(['crp_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curricula');
    }
};
