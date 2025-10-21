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
        Schema::create('service_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('service_plan_id')->nullable();
            $table->integer('requests_count')->default(0);
            $table->decimal('cost', 10, 2)->default(0);
            $table->json('metadata')->nullable(); // Additional usage data
            $table->timestamp('usage_date');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('status', ['success', 'error', 'timeout'])->default('success');
            $table->text('response_time_ms')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'service_id', 'usage_date']);
            $table->index(['service_id', 'usage_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_usages');
    }
};
