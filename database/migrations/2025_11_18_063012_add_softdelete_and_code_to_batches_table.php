<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('batches', function (Blueprint $table) {
            if (!Schema::hasColumn('batches', 'batch_code')) {
                $table->string('batch_code', 50)->nullable()->after('id')->index();
            }
            // soft deletes
            if (!Schema::hasColumn('batches', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void {
        Schema::table('batches', function (Blueprint $table) {
            if (Schema::hasColumn('batches', 'batch_code')) {
                $table->dropColumn('batch_code');
            }
            if (Schema::hasColumn('batches', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
