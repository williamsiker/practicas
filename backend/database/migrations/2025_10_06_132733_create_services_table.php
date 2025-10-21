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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->decimal('base_price', 10, 2)->default(0);
            $table->json('pricing_tiers')->nullable(); // For different pricing plans
            $table->integer('max_requests_per_day')->default(1000);
            $table->integer('max_requests_per_month')->default(30000);
            $table->json('features')->nullable(); // JSON array of features
            $table->string('api_endpoint')->nullable();
            $table->text('documentation_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
