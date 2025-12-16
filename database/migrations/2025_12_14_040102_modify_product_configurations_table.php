<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Obtener los nombres de Foreign Keys existentes
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'product_configurations' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        // Eliminar foreign keys existentes
        foreach ($foreignKeys as $fk) {
            if (str_contains($fk->CONSTRAINT_NAME, 'finish_')) {
                try {
                    DB::statement("ALTER TABLE product_configurations DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                } catch (\Exception $e) {
                    // Ya eliminada
                }
            }
        }

        Schema::table('product_configurations', function (Blueprint $table) {
            // Eliminar columnas si existen
            if (Schema::hasColumn('product_configurations', 'finish_1_id')) {
                $table->dropColumn([
                    'finish_1_id',
                    'finish_1_quantity',
                    'finish_2_id',
                    'finish_2_quantity',
                    'finish_3_id',
                    'finish_3_quantity'
                ]);
            }

            // Agregar columna JSON para múltiples acabados
            if (!Schema::hasColumn('product_configurations', 'finishes')) {
                $table->json('finishes')->nullable()->after('material_id')
                    ->comment('Array de acabados: [{"finish_id": 1, "quantity": 20}, ...]');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_configurations', function (Blueprint $table) {
            $table->dropColumn('finishes');

            // Restaurar columnas (simplificado, sin FKs para evitar complejidad excesiva en rollback)
            $table->bigInteger('finish_1_id')->unsigned()->nullable();
            $table->integer('finish_1_quantity')->nullable();
            $table->bigInteger('finish_2_id')->unsigned()->nullable();
            $table->integer('finish_2_quantity')->nullable();
            $table->bigInteger('finish_3_id')->unsigned()->nullable();
            $table->integer('finish_3_quantity')->nullable();
        });
    }
};
