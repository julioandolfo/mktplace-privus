<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\StockLocation;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default company (required for marketplace accounts)
        Company::create([
            'name'          => 'Minha Empresa',
            'document_type' => 'cnpj',
            'document'      => '00.000.000/0001-00',
            'is_active'     => true,
        ]);

        // Admin user
        User::create([
            'name' => 'Admin',
            'email' => 'admin@privus.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'theme_preference' => 'dark',
        ]);

        // Default stock location
        StockLocation::create([
            'name' => 'Deposito Principal',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Sample categories
        $eletronicos = Category::create(['name' => 'Eletronicos', 'slug' => 'eletronicos']);
        Category::create(['name' => 'Acessorios', 'slug' => 'acessorios', 'parent_id' => $eletronicos->id]);
        Category::create(['name' => 'Roupas', 'slug' => 'roupas']);
        Category::create(['name' => 'Casa e Decoracao', 'slug' => 'casa-decoracao']);
        Category::create(['name' => 'Beleza e Saude', 'slug' => 'beleza-saude']);

        // Sample brands
        Brand::create(['name' => 'Marca Propria', 'slug' => 'marca-propria']);

        // Default system settings
        SystemSetting::set('general', 'system_name', 'MktPlace Privus');
        SystemSetting::set('general', 'currency', 'BRL');
        SystemSetting::set('general', 'timezone', 'America/Sao_Paulo');
        SystemSetting::set('general', 'date_format', 'd/m/Y');
    }
}
