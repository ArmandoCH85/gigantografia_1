<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_configurations', function (Blueprint $table) {
            // Eliminar columnas de acabados fijos
            $table->dropForeign(['finish_1_id']);
            $table->dropForeign(['finish_2_id']);
            $table->dropForeign(['finish_3_id']);
            $table->dropColumn([
                'finish_1_id',
                'finish_1_quantity',
                'finish_2_id',
                'finish_2_quantity',
                'finish_3_id',
                'finish_3_quantity'
            ]);

            // Agregar columna JSON para múltiples acabados
            $table->json('finishes')->nullable()->after('material_id')
                ->comment('Array de acabados: [{"finish_id": 1, "quantity": 20}, ...]');
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
