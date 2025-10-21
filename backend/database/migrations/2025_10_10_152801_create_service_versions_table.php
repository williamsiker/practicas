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
        Schema::create('service_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('enhanced_services')->onDelete('cascade');
            $table->string('version_number');
            $table->text('version_description')->nullable();
            $table->json('changelog')->nullable();
            $table->enum('status', ['active', 'deprecated', 'retired'])->default('active');
            $table->boolean('is_default')->default(false);

            // Version-specific configurations
            $table->string('endpoint_url');
            $table->json('parameters')->nullable();
            $table->json('responses')->nullable();
            $table->json('breaking_changes')->nullable();

            // Deprecation info
            $table->timestamp('deprecated_at')->nullable();
            $table->timestamp('retirement_date')->nullable();
            $table->string('migration_guide_url')->nullable();

            $table->timestamps();

            // Ensure unique versions per service
            $table->unique(['service_id', 'version_number']);
            $table->index(['service_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_versions');
    }
};
