<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migration.
     */
    public function up(): void
    {
        Schema::table('import_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('import_sessions', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('batches', function (Blueprint $table) {
            if (!Schema::hasColumn('batches', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Rollback migration.
     */
    public function down(): void
    {
        Schema::table('import_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('import_sessions', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });

        Schema::table('batches', function (Blueprint $table) {
            if (Schema::hasColumn('batches', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
};
