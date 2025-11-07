<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder {
    public function run(): void {
        $defaults = [
            ['name'=>'Admin QC',   'email'=>'adminqc@peroniks.com', 'role'=>'admin_qc'],
            ['name'=>'Kabag QC',   'email'=>'kabagqc@peroniks.com', 'role'=>'kabag_qc'],
            ['name'=>'Direktur',   'email'=>'direktur@peroniks.com', 'role'=>'direktur'],
            ['name'=>'Auditor',    'email'=>'auditor@peroniks.com',  'role'=>'auditor'],
        ];
        foreach ($defaults as $u) {
            User::updateOrCreate(
                ['email'=>$u['email']],
                ['name'=>$u['name'], 'password'=>Hash::make('password'), 'role'=>$u['role']]
            );
        }
    }
}
