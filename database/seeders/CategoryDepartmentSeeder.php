<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryDepartmentSeeder extends Seeder
{
    public function run()
    {
        // mapping: department_name => [category_name, ...]
        $map = [
            'Cor Flange' => ['Rusak Cor','Retak Tanjek','Keropos'],
            'Netto Potong' => ['Keropos','ID Buntu','Cembung','Rusak Potong','Gelembung Angin','Kurang Cairan','Cetakan Rusak','Cairan Bocor'],
            'OD Flange' => ['Keropos','ID Blong','OD Blong','Rusak Bubut','Susut','Oval','Oleng','Retak'],
            'CNC Flange' => ['Keropos','OD Blong','ID Blong','Tebal Blong','RF Blong','Serong Besar','Rusak Drat','Rusak Bubut','Oleng','Retak','Susut','Pasir Jatuh','Kotoran','Cekung'],
            'Bor QC' => ['Spasi Bor','Rusak Bor','Bor Oval'],
            'Solder Flange' => ['Keropos','Tebal Blong','ID Blong','OD Blong','Retak','Susut','Serong Besar','Rusak Bubut','Tebal Oleng','Kebocoran'],
            'Servis Flange' => ['Keropos','OD Blong','ID Blong','Tebal Blong','Rusak Bubut','Susut','Pasir Jatuh','Oleng','Retak'],
            'Rusak Gudang' => ['Salah AISI','Keropos','ID Blong','OD Blong','Tebal Blong','Retak'],
        ];

        foreach ($map as $deptName => $cats) {
            $dept = DB::table('departments')->where('name', $deptName)->first();
            if (!$dept) continue;

            foreach ($cats as $cname) {
                $cat = DB::table('categories')->where('name', $cname)->first();
                if (!$cat) {
                    // jika kategori belum ada, buat sebagai global (department_id null)
                    $catId = DB::table('categories')->insertGetId([
                        'department_id' => null,
                        'name' => $cname,
                        'tag' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $catId = $cat->id;
                }

                // insert pivot jika belum
                $exists = DB::table('category_department')->where('category_id', $catId)->where('department_id', $dept->id)->first();
                if (!$exists) {
                    DB::table('category_department')->insert([
                        'category_id' => $catId,
                        'department_id' => $dept->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
