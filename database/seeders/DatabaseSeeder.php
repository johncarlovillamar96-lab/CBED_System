<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;use Illuminate\Support\Facades\Hash;use App\Models\User;
class DatabaseSeeder extends Seeder {public function run():void{User::firstOrCreate(['email'=>'admin@cbed.local'],['name'=>'CBED Administrator','password'=>Hash::make('ChangeMe123!'),'role'=>'admin']);}}
