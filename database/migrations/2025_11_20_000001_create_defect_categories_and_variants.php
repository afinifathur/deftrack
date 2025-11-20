<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('defect_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('defect_type_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('defect_category_id')->constrained('defect_categories')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('label');
            $table->string('code')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('ordering')->default(0);
            $table->timestamps();
        });

        // add snapshot and canonical FK on defect_lines
        Schema::table('defect_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('defect_lines', 'defect_category_id')) {
                $table->foreignId('defect_category_id')->nullable()->after('id')->constrained('defect_categories')->nullOnDelete();
            }
            if (!Schema::hasColumn('defect_lines', 'variant_id')) {
                $table->foreignId('variant_id')->nullable()->after('defect_category_id')->constrained('defect_type_variants')->nullOnDelete();
            }
            if (!Schema::hasColumn('defect_lines', 'qty_kg')) {
                $table->decimal('qty_kg', 12, 3)->default(0)->after('qty_pcs');
            }
            // optional snapshot fields (item_name etc) - for historical accuracy
            if (!Schema::hasColumn('defect_lines', 'item_name')) {
                $table->string('item_name')->nullable()->after('variant_id');
            }
            if (!Schema::hasColumn('defect_lines', 'aisi')) {
                $table->string('aisi')->nullable()->after('item_name');
            }
            if (!Schema::hasColumn('defect_lines', 'size')) {
                $table->string('size')->nullable()->after('aisi');
            }
            if (!Schema::hasColumn('defect_lines', 'line')) {
                $table->string('line')->nullable()->after('size');
            }
            if (!Schema::hasColumn('defect_lines', 'cust_name')) {
                $table->string('cust_name')->nullable()->after('line');
            }
        });
    }

    public function down(): void {
        Schema::table('defect_lines', function (Blueprint $table) {
            if (Schema::hasColumn('defect_lines','cust_name')) $table->dropColumn('cust_name');
            if (Schema::hasColumn('defect_lines','line')) $table->dropColumn('line');
            if (Schema::hasColumn('defect_lines','size')) $table->dropColumn('size');
            if (Schema::hasColumn('defect_lines','aisi')) $table->dropColumn('aisi');
            if (Schema::hasColumn('defect_lines','item_name')) $table->dropColumn('item_name');
            if (Schema::hasColumn('defect_lines','qty_kg')) $table->dropColumn('qty_kg');
            if (Schema::hasColumn('defect_lines','variant_id')) $table->dropForeign(['variant_id']);
            if (Schema::hasColumn('defect_lines','defect_category_id')) $table->dropForeign(['defect_category_id']);
        });

        Schema::dropIfExists('defect_type_variants');
        Schema::dropIfExists('defect_categories');
    }
};
