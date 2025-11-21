<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    public function up(): void
    {
        // Jika tabel sudah ada (dibuat oleh migration lain), jangan buat lagi
        if (Schema::hasTable('categories')) {
            return;
        }

        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // kolom relasi (boleh dipakai, walau kamu juga punya pivot)
            $table->unsignedBigInteger('department_id')->nullable()->index();

            $table->string('name')->index();
            $table->string('tag')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
}
