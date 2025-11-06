<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Cor Flange','Netto Potong','Bubut','Bor QC','Gudang Jadi'] as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
    }
}
