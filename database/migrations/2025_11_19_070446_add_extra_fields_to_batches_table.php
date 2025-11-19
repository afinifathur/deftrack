<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('batches', function (Blueprint $table) {
            if (!Schema::hasColumn('batches','item_name')) {
                $table->string('item_name')->nullable()->after('item_code');
            }
            if (!Schema::hasColumn('batches','aisi')) {
                $table->string('aisi',50)->nullable()->after('item_name')->index();
            }
            if (!Schema::hasColumn('batches','size')) {
                $table->string('size',50)->nullable()->after('aisi')->index();
            }
            if (!Schema::hasColumn('batches','line')) {
                $table->string('line',100)->nullable()->after('size')->index();
            }
            if (!Schema::hasColumn('batches','cust_name')) {
                $table->string('cust_name',150)->nullable()->after('line')->index();
            }
        });
    }

    public function down(): void {
        Schema::table('batches', function (Blueprint $table) {
            if (Schema::hasColumn('batches','cust_name')) $table->dropColumn('cust_name');
            if (Schema::hasColumn('batches','line')) $table->dropColumn('line');
            if (Schema::hasColumn('batches','size')) $table->dropColumn('size');
            if (Schema::hasColumn('batches','aisi')) $table->dropColumn('aisi');
            // don't drop item_name if other code depends on it
        });
    }
};
