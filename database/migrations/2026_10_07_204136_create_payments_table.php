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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->string('order_id')->unique();
            $table->string('channel', 10)->default('qris');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->string('status', 10)->default('pending');
            $table->string('gateway_ref')->nullable();
            $table->text('qr_string')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['bill_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
