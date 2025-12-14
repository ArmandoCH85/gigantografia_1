<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GigantografiaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Customer Price Tiers
        $tiers = ['Estándar', 'Por Mayor', 'Campaña Política'];
        foreach ($tiers as $tier) {
            DB::table('customer_price_tiers')->updateOrInsert(['name' => $tier], ['created_at' => now(), 'updated_at' => now()]);
        }

        // 2. Product Categories (Ensure they exist)
        $bannerId = DB::table('product_categories')->where('name', 'Baner')->value('id');
        if (!$bannerId) {
            $bannerId = DB::table('product_categories')->insertGetId(['name' => 'Baner', 'created_at' => now(), 'updated_at' => now()]);
        }

        $vinilId = DB::table('product_categories')->where('name', 'Vinil')->value('id');
        if (!$vinilId) {
            $vinilId = DB::table('product_categories')->insertGetId(['name' => 'Vinil', 'created_at' => now(), 'updated_at' => now()]);
        }

        // 3. Product Materials
        // Baner (Prices set to 0 as they were not specified in the document, unlike Vinil)
        $baners = [
            ['name' => 'B. Delgado', 'unit_price' => 0.00],
            ['name' => 'B. Grueso', 'unit_price' => 0.00],
            ['name' => 'B. Blackout', 'unit_price' => 0.00],
            ['name' => 'Lona Trasluc.', 'unit_price' => 0.00, 'max_height' => 1.20],
        ];

        foreach ($baners as $baner) {
            DB::table('product_materials')->updateOrInsert(
                ['name' => $baner['name']],
                array_merge($baner, ['category_id' => $bannerId, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        // Vinil
        $vinils = [
            ['name' => 'Econ 120 gr', 'unit_price' => 18.00],
            ['name' => 'Chino 140 gr', 'unit_price' => 22.50],
            ['name' => 'Intertak 120 gr', 'unit_price' => 25.20],
            ['name' => 'Intertak Premium', 'unit_price' => 27.00],
            ['name' => 'Arclad', 'unit_price' => 37.80],
            ['name' => 'Pavonado', 'unit_price' => 40.50],
            ['name' => 'Pavonado c/color', 'unit_price' => 45.00],
            ['name' => 'Microperforado', 'unit_price' => 31.50],
            ['name' => 'Vinil Traslucido', 'unit_price' => 36.00],
            ['name' => 'Foam', 'unit_price' => 38.25, 'max_width' => 1.20],
            ['name' => 'Celtex', 'unit_price' => 56.25, 'max_width' => 1.20],
        ];

        foreach ($vinils as $vinil) {
            DB::table('product_materials')->updateOrInsert(
                ['name' => $vinil['name']],
                array_merge($vinil, ['category_id' => $vinilId, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        // 4. Product Finishes
        $finishes = [
            ['name' => 'Impreso', 'additional_cost' => 0.00],
            ['name' => 'Con ojales', 'additional_cost' => 3.00, 'requires_quantity' => true],
            ['name' => 'Con tubos', 'additional_cost' => 12.00],
            ['name' => 'Termosellado', 'additional_cost' => 0.00],
        ];

        foreach ($finishes as $finish) {
            DB::table('product_finishes')->updateOrInsert(
                ['name' => $finish['name']],
                array_merge($finish, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
