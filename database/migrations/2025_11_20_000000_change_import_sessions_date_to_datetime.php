<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change column `date` from DATE → DATETIME
     */
    public function up(): void
    {
        Schema::table('import_sessions', function (Blueprint $table) {
            // Requires doctrine/dbal
            $table->dateTime('date')->nullable()->change();
        });
    }

    /**
     * Revert change: DATETIME → DATE
     */
    public function down(): void
    {
        Schema::table('import_sessions', function (Blueprint $table) {
            $table->date('date')->nullable()->change();
        });
    }
};
