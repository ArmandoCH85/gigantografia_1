<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GigantografiaSeeder extends Seeder
{
    public function run(): void
    {
        // Crear o verificar categorías
        $categoryBaner = DB::table('product_categories')->where('name', 'BANER')->first();
        if (!$categoryBaner) {
            DB::table('product_categories')->insert([
                'name' => 'BANER',
                'description' => 'Productos de Banner',
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $categoryBaner = DB::table('product_categories')->where('name', 'BANER')->first();
        }

        $categoryVinil = DB::table('product_categories')->where('name', 'VINIL')->first();
        if (!$categoryVinil) {
            DB::table('product_categories')->insert([
                'name' => 'VINIL',
                'description' => 'Productos de Vinil',
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $categoryVinil = DB::table('product_categories')->where('name', 'VINIL')->first();
        }

        // MATERIALES BANNER
        DB::table('product_materials')->updateOrInsert(
            ['code' => 'b_delgado'],
            [
                'category_id' => $categoryBaner->id,
                'name' => 'B. Delgado',
                'price_factor_standard' => 11.00,
                'price_factor_wholesale' => 8.00,
                'price_factor_campaign' => 4.50,
                'max_width' => null,
                'max_height' => null,
                'sheet_width' => null,
                'active' => 1,
                'updated_at' => now()
            ]
        );

        DB::table('product_materials')->updateOrInsert(
            ['code' => 'b_grueso'],
            [
                'category_id' => $categoryBaner->id,
                'name' => 'B. Grueso',
                'price_factor_standard' => 14.00,
                'price_factor_wholesale' => 11.00,
                'price_factor_campaign' => 7.00,
                'max_width' => null,
                'max_height' => null,
                'sheet_width' => null,
                'active' => 1,
                'updated_at' => now()
            ]
        );

        DB::table('product_materials')->updateOrInsert(
            ['code' => 'b_blackout'],
            [
                'category_id' => $categoryBaner->id,
                'name' => 'B. Blackout',
                'price_factor_standard' => 18.00,
                'price_factor_wholesale' => null,
                'price_factor_campaign' => null,
                'max_width' => null,
                'max_height' => null,
                'sheet_width' => null,
                'active' => 1,
                'updated_at' => now()
            ]
        );

        DB::table('product_materials')->updateOrInsert(
            ['code' => 'lona_trasluc'],
            [
                'category_id' => $categoryBaner->id,
                'name' => 'Lona Trasluc.',
                'price_factor_standard' => 26.00,
                'price_factor_wholesale' => null,
                'price_factor_campaign' => null,
                'max_width' => null,
                'max_height' => 1.20,
                'sheet_width' => null,
                'active' => 1,
                'updated_at' => now()
            ]
        );

        // MATERIALES VINIL
        $materialesVinil = [
            ['code' => 'econ_120', 'name' => 'Econ 120 gr', 'factor' => 20.00, 'max_width' => null],
            ['code' => 'chino_140', 'name' => 'Chino 140 gr', 'factor' => 25.00, 'max_width' => null],
            ['code' => 'intertak_120', 'name' => 'Intertak 120 gr', 'factor' => 28.00, 'max_width' => null],
            ['code' => 'intertak_premium', 'name' => 'Intertak Premium', 'factor' => 30.00, 'max_width' => null],
            ['code' => 'arclad', 'name' => 'Arclad', 'factor' => 42.00, 'max_width' => null],
            ['code' => 'pavonado', 'name' => 'Pavonado', 'factor' => 45.00, 'max_width' => null],
            ['code' => 'pavonado_color', 'name' => 'Pavonado c/color', 'factor' => 50.00, 'max_width' => null],
            ['code' => 'microperforado', 'name' => 'Microperforado', 'factor' => 35.00, 'max_width' => null],
            ['code' => 'vinil_traslucido', 'name' => 'Vinil Traslucido', 'factor' => 40.00, 'max_width' => null],
            ['code' => 'foam', 'name' => 'Foam', 'factor' => 42.50, 'max_width' => 1.20],
            ['code' => 'celtex', 'name' => 'Celtex', 'factor' => 62.50, 'max_width' => 1.20],
        ];

        foreach ($materialesVinil as $material) {
            DB::table('product_materials')->updateOrInsert(
                ['code' => $material['code']],
                [
                    'category_id' => $categoryVinil->id,
                    'name' => $material['name'],
                    'price_factor_standard' => $material['factor'],
                    'price_factor_wholesale' => null,
                    'price_factor_campaign' => null,
                    'max_width' => $material['max_width'],
                    'max_height' => null,
                    'sheet_width' => 1.50,
                    'active' => 1,
                    'updated_at' => now()
                ]
            );
        }

        // ACABADOS
        DB::table('product_finishes')->updateOrInsert(
            ['code' => 'impreso'],
            [
                'name' => 'Impreso',
                'cost_per_unit' => 0.00,
                'cost_formula' => null,
                'formula_type' => 'fixed',
                'requires_quantity' => false,
                'active' => 1,
                'updated_at' => now()
            ]
        );

        DB::table('product_finishes')->updateOrInsert(
            ['code' => 'c_ojales'],
            [
                'name' => 'Con ojales',
                'cost_per_unit' => 0.50,
                'cost_formula' => 'quantity * 0.5',
                'formula_type' => 'per_quantity',
                'requires_quantity' => true,
                'active' => 1,
                'updated_at' => now()
            ]
        );

        DB::table('product_finishes')->updateOrInsert(
            ['code' => 'c_tubos'],
            [
                'name' => 'Con tubos',
                'cost_per_unit' => 5.00,
                'cost_formula' => 'width * 5',
                'formula_type' => 'width_based',
                'requires_quantity' => false,
                'active' => 1,
                'updated_at' => now()
            ]
        );

        DB::table('product_finishes')->updateOrInsert(
            ['code' => 'termosellado'],
            [
                'name' => 'Termosellado',
                'cost_per_unit' => 0.00,
                'cost_formula' => null,
                'formula_type' => 'fixed',
                'requires_quantity' => false,
                'active' => 1,
                'updated_at' => now()
            ]
        );

        //  NIVELES DE PRECIO
        DB::table('customer_price_tiers')->updateOrInsert(
            ['code' => 'estandar'],
            [
                'name' => 'Estándar',
                'description' => 'Precio estándar para clientes regulares',
                'active' => 1,
                'updated_at' => now()
            ]
        );

        DB::table('customer_price_tiers')->updateOrInsert(
            ['code' => 'por_mayor'],
            [
                'name' => 'Por Mayor',
                'description' => 'Precio reducido para compras al por mayor',
                'active' => 1,
                'updated_at' => now()
            ]
        );

        DB::table('customer_price_tiers')->updateOrInsert(
            ['code' => 'campana_politica'],
            [
                'name' => 'Campaña Política',
                'description' => 'Precio especial para campañas políticas',
                'active' => 1,
                'updated_at' => now()
            ]
        );

        echo "\n✅ DATOS CARGADOS CON ÉXITO\n";
        echo "- 15 materiales (4 BANER, 11 VINIL)\n";
        echo "- 4 acabados\n";
        echo "- 3 niveles de precio\n\n";
    }
}
