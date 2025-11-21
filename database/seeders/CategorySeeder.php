<?php
namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Department;


class CategorySeeder extends Seeder
{
public function run()
{
// Master categories (one row per global category)
$master = [
'Rusak Cor','Retak Tanjek','Keropos','ID Buntu','Cembung','Rusak Potong',
'Gelembung Angin','Kurang Cairan','Cetakan Rusak','Cairan Bocor','ID Blong',
'OD Blong','Rusak Bubut','Susut','Oval','Oleng','Tebal Blong','RF Blong',
'Serong Besar','Rusak Drat','Pasir Jatuh','Kotoran','Cekung','Spasi Bor','Rusak Bor','Bor Oval','Tebal Oleng','Kebocoran','Salah AISI'
];


$instances = [];
foreach ($master as $name) {
$instances[$name] = Category::firstOrCreate(['name' => $name]);
}


// Map per department name (use actual names in your departments table)
$map = [
'Cor Flange' => ['Rusak Cor','Retak Tanjek','Keropos'],
'Netto Flange' => ['Keropos','ID Buntu','Cembung','Rusak Potong','Gelembung Angin','Kurang Cairan','Cetakan Rusak','Cairan Bocor'],
'OD Flange' => ['Keropos','ID Blong','OD Blong','Rusak Bubut','Susut','Oval','Oleng','Retak'],
'CNC Flange' => ['Keropos','OD Blong','ID Blong','Tebal Blong','RF Blong','Serong Besar','Rusak Drat','Rusak Bubut','Oleng','Retak','Susut','Pasir Jatuh','Kotoran','Cekung'],
'Bor QC' => ['Spasi Bor','Rusak Bor','Bor Oval'],
'Solder Flange' => ['Keropos','Tebal Blong','ID Blong','OD Blong','Retak','Susut','Serong Besar','Rusak Bubut','Tebal Oleng','Kebocoran'],
'Servis Flange' => ['Keropos','OD Blong','ID Blong','Tebal Blong','Rusak Bubut','Susut','Pasir Jatuh','Oleng','Retak'],
'Rusak Gudang' => ['Salah AISI','Keropos','ID Blong','OD Blong','Tebal Blong','Retak'],
];


foreach ($map as $deptName => $cats) {
$dep = Department::where('name', $deptName)->first();
if (!$dep) continue;
$catIds = [];
foreach ($cats as $c) {
if (isset($instances[$c])) $catIds[] = $instances[$c]->id;
}
if (count($catIds)) $dep->categories()->syncWithoutDetaching($catIds);
}
}
}