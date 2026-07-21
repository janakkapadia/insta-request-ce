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
        Schema::table('collection_documentations', function (Blueprint $table) {
            if (! Schema::hasColumn('collection_documentations', 'environment_id')) {
                $table->foreignUuid('environment_id')->nullable()->after('team_id')->constrained('environments')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collection_documentations', function (Blueprint $table) {
            if (Schema::hasColumn('collection_documentations', 'environment_id')) {
                $table->dropForeign(['environment_id']);
                $table->dropColumn('environment_id');
            }
        });
    }
};
