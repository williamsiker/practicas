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
            $table->string('slug')->unique();
            $table->string('short_description', 500);
            $table->string('department')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->string('coverage')->nullable();
            $table->string('url');
            $table->string('type'); // api-rest, form-web, etc.
            $table->string('status')->default('revision'); // borrador, revision, aprobado
            $table->string('auth_type')->default('ninguna');
            $table->string('schedule')->default('office'); // office|full
            $table->unsignedInteger('monthly_limit')->nullable();
            $table->json('tags')->nullable();
            $table->json('labels')->nullable();
            $table->string('owner')->nullable();
            $table->string('documentation_url')->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->timestamp('approved_at')->nullable();
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
