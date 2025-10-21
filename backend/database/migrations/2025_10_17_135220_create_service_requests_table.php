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
            $table->string('name');
            $table->text('description');
            $table->string('url');
            $table->enum('method', ['GET', 'POST', 'PUT', 'DELETE', 'PATCH']);
            $table->string('version')->default('1.0.0');
            $table->boolean('requires_auth')->default(false);
            $table->enum('auth_type', ['none', 'token', 'api_key', 'oauth'])->default('none');
            $table->json('auth_config')->nullable();
            $table->longText('documentation');
            $table->json('parameters')->nullable();
            $table->json('responses')->nullable();
            $table->json('error_codes')->nullable();
            $table->json('validations')->nullable();
            $table->boolean('metrics_enabled')->default(false);
            $table->json('metrics_config')->nullable();
            $table->boolean('has_demo')->default(false);
            $table->string('demo_url')->nullable();
            $table->decimal('base_price', 10, 2)->default(0);
            $table->json('pricing_tiers')->nullable();
            $table->integer('max_requests_per_day')->default(1000);
            $table->integer('max_requests_per_month')->default(30000);
            $table->json('features')->nullable();
            $table->text('justification'); // Business justification for the service
            $table->boolean('terms_accepted')->default(false);
            $table->timestamp('terms_accepted_at')->nullable();
            $table->enum('status', ['pending_review', 'approved', 'rejected', 'needs_modification'])->default('pending_review');
            $table->unsignedBigInteger('publisher_id'); // User who requested the service
            $table->unsignedBigInteger('reviewed_by')->nullable(); // Admin who reviewed
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('approved_service_id')->nullable(); // Link to created service if approved
            $table->timestamps();

            $table->index(['publisher_id', 'status']);
            $table->index('status');
            $table->unique('name'); // Ensure unique service names
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
