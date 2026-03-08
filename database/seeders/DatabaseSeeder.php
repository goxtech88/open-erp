<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Empresa demo
        $company = Company::firstOrCreate(
            ['cuit' => '20123456789'],
            [
                'code' => 'DEMO',
                'name' => 'Empresa Demo S.A.',
                'cuit' => '20123456789',
                'fiscal_address' => 'Av. Corrientes 1234, CABA',
                'email' => 'demo@goxtechlabs.com.ar',
            ]
        );

        // Usuario admin
        User::firstOrCreate(
            ['email' => 'admin@erp.local'],
            [
                'company_id' => $company->id,
                'name' => 'Admin ERP',
                'email' => 'admin@erp.local',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }
}
