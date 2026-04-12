<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_log_id')->constrained()->cascadeOnDelete();

            $table->string('form_type'); // VR-121X, VR-122X

            $table->enum('status', [
                'processing', 'completed', 'failed',
            ])->default('processing');

            $table->string('file_path')->nullable();
            $table->string('pdf_hash')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);

            // Locking (cascade from service_log)
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // CRITICAL: prevents duplicate exports and race conditions
            $table->unique(['service_log_id', 'form_type'], 'unique_service_form');

            $table->index('crp_id');
            $table->index('status');
            $table->index(['crp_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_forms');
    }
};
