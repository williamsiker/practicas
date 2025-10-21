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
        Schema::create('enhanced_services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description');
            $table->string('url');
            $table->enum('method', ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])->default('GET');
            $table->enum('status', ['draft', 'pending', 'active', 'inactive', 'maintenance', 'rejected'])->default('draft');
            $table->string('version')->default('1.0.0');
            $table->foreignId('publisher_id')->nullable()->constrained('users')->onDelete('cascade');

            // Authentication configuration
            $table->boolean('requires_auth')->default(false);
            $table->enum('auth_type', ['none', 'token', 'api_key', 'oauth'])->default('none');
            $table->json('auth_config')->nullable();

            // Documentation
            $table->text('documentation')->nullable();
            $table->json('parameters')->nullable(); // Input parameters
            $table->json('responses')->nullable(); // Response formats
            $table->json('error_codes')->nullable(); // Error handling
            $table->json('validations')->nullable(); // Validation rules

            // Metrics configuration
            $table->boolean('metrics_enabled')->default(false);
            $table->json('metrics_config')->nullable();

            // Demo configuration
            $table->boolean('has_demo')->default(false);
            $table->string('demo_url')->nullable();

            // Pricing and limits
            $table->decimal('base_price', 10, 2)->default(0);
            $table->json('pricing_tiers')->nullable();
            $table->integer('max_requests_per_day')->default(1000);
            $table->integer('max_requests_per_month')->default(30000);
            $table->json('features')->nullable();

            // Approval workflow
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            // Terms acceptance
            $table->boolean('terms_accepted')->default(false);
            $table->timestamp('terms_accepted_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['publisher_id', 'status']);
            $table->index(['status', 'version']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enhanced_services');
    }
};
