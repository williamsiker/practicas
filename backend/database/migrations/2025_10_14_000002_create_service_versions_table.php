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
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('status')->default('available'); // available, maintenance, deprecated, draft
            $table->date('release_date')->nullable();
            $table->string('compatibility')->nullable();
            $table->string('documentation_url')->nullable();
            $table->boolean('is_requestable')->default(true);
            $table->unsignedInteger('limit_suggestion')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
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
