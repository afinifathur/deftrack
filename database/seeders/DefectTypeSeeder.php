<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DefectType;

class DefectTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Potong','Keropos','Susut','Baret','Pecah','Out of Size','Lainnya'] as $name) {
            DefectType::firstOrCreate(['name' => $name, 'parent_id' => null]);
        }
    }
}
