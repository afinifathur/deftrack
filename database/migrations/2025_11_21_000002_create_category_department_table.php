<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryDepartmentTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('category_department')) {
            return;
        }

        Schema::create('category_department', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('department_id');

            $table->timestamps();

            // Index untuk mempercepat query
            $table->index(['category_id', 'department_id']);

            // Kalau mau foreign key:
            // $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            // $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_department');
    }
}
