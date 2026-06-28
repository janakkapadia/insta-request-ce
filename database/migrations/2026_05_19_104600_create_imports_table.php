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
        Schema::create('imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnDelete();
            $table->string('source_format');
            $table->string('original_filename');
            $table->string('file_hash', 64)->nullable();
            $table->string('status')->default('pending');
            $table->foreignUuid('target_collection_id')->nullable()->constrained('collections')->nullOnDelete();
            $table->string('merge_strategy')->default('create_new');
            $table->json('summary')->nullable();
            $table->json('validation_report')->nullable();
            $table->json('parsed_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
