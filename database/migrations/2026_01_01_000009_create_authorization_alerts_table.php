<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('authorization_id')->constrained()->cascadeOnDelete();

            $table->enum('alert_type', [
                'expiring_soon',
                'expiring_critical',
                'expired',
                'units_low',
                'units_exhausted',
                'pending_activation',
            ]);

            $table->enum('severity', ['notice', 'warning', 'critical']);
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['crp_id', 'acknowledged']);
            $table->index(['authorization_id', 'alert_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_alerts');
    }
};
