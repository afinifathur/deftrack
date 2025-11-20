<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use DB;
use Carbon\Carbon;

class DefectCategorySeeder extends Seeder {
    public function run(): void {
        $now = Carbon::now();
        $cats = [
            'Keropos','Susut','Out of Size','Potong','Retak','Bubut Error','Bor Error','Netto Potong'
        ];
        foreach($cats as $c) {
            $slug = Str::slug($c);
            $id = DB::table('defect_categories')->insertGetId([
                'name'=>$c,'slug'=>$slug,'description'=>null,'created_at'=>$now,'updated_at'=>$now
            ]);

            // create a generic global variant (visible to all)
            DB::table('defect_type_variants')->insert([
                'defect_category_id'=>$id,
                'department_id'=>null,
                'label'=>$c,
                'code'=>strtoupper(Str::substr($slug,0,6)),
                'active'=>1,
                'ordering'=>0,
                'created_at'=>$now,'updated_at'=>$now
            ]);
        }
    }
}
