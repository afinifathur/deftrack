<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            DefectTypeSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            CategoryDepartmentSeeder::class, // tambahan sesuai permintaan
        ]);
    }
}
