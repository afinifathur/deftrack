<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDepartmentIdToCategories extends Migration
{
    public function up(): void
    {
        // Jika tabel categories belum ada, buat tabel lengkap
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();

                // kolom relasi ke departments (boleh null untuk kategori global)
                $table->unsignedBigInteger('department_id')->nullable()->index();

                $table->string('name')->index();
                $table->string('tag')->nullable()->index();

                $table->timestamps();
                $table->softDeletes();
            });

            return; // selesai, tidak perlu alter table lagi
        }

        // Jika tabel sudah ada tapi kolom department_id belum ada, tambahkan
        if (!Schema::hasColumn('categories', 'department_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')
                    ->nullable()
                    ->after('id')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        // Kalau tabelnya tidak ada, tidak perlu apa-apa
        if (!Schema::hasTable('categories')) {
            return;
        }

        // Kalau kolom department_id ada, hapus
        if (Schema::hasColumn('categories', 'department_id')) {
            Schema::table('categories', function (Blueprint $table) {
                // drop index & kolom
                $table->dropIndex(['department_id']);
                $table->dropColumn('department_id');
            });
        }
    }
}
