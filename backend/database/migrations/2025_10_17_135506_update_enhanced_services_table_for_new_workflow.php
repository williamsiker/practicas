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
        Schema::table('enhanced_services', function (Blueprint $table) {
            // Update status enum to include 'ready_to_publish' and 'published'
            $table->dropColumn('status');
        });

        Schema::table('enhanced_services', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'pending',
                'ready_to_publish', // New: Service approved by admin, ready for publisher to configure and publish
                'published',        // New: Service published by publisher and available to consumers
                'active',           // Service is active and being used
                'inactive',
                'maintenance',
                'rejected',
            ])->default('draft')->after('method');

            // Add field to track the source service request
            $table->unsignedBigInteger('source_request_id')->nullable()->after('publisher_id');
            $table->index('source_request_id');

            // Add fields for Phase 3 publication workflow
            $table->timestamp('published_at')->nullable()->after('approved_at');
            $table->unsignedBigInteger('published_by')->nullable()->after('published_at');
            $table->json('operational_config')->nullable()->after('published_by'); // For schedules, limits, access control
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enhanced_services', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropIndex(['source_request_id']);
            $table->dropColumn(['source_request_id', 'published_at', 'published_by', 'operational_config']);
        });

        Schema::table('enhanced_services', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending', 'active', 'inactive', 'maintenance', 'rejected'])
                ->default('draft')
                ->after('method');
        });
    }
};
