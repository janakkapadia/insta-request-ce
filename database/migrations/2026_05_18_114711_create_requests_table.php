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
        Schema::create('requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('folder_id')->nullable()->constrained('collection_folders')->cascadeOnDelete();
            $table->string('name');
            $table->string('method')->default('GET');
            $table->string('url')->nullable();
            $table->json('headers')->nullable();
            $table->json('query_params')->nullable();
            $table->json('body')->nullable();
            $table->text('auth')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
