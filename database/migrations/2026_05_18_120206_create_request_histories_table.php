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
        Schema::create('request_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(\App\Domains\Teams\Models\Team::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\User::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method');
            $table->string('url');
            $table->integer('status');
            $table->integer('time_ms');
            $table->integer('size_bytes');
            $table->json('request_payload')->nullable();
            $table->json('response_meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_histories');
    }
};
