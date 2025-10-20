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
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_version_id')->nullable()->constrained('service_versions')->nullOnDelete();
            $table->string('consumer_name')->nullable();
            $table->string('consumer_email')->nullable();
            $table->string('schedule')->default('office'); // office|full|custom
            $table->time('custom_start')->nullable();
            $table->time('custom_end')->nullable();
            $table->unsignedInteger('monthly_limit')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
