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
        // ORDEN IMPORTANTE: Eliminar primero las que tienen foreign keys

        // Reservations - depends on tables
        Schema::dropIfExists('reservations');

        // Recipe details - depends on recipes and ingredients
        Schema::dropIfExists('recipe_details');

        // Recipes - depends on products
        Schema::dropIfExists('recipes');

        // Ingredient stock - depends on ingredients and warehouses
        Schema::dropIfExists('ingredient_stock');

        // Ingredients
        Schema::dropIfExists('ingredients');

        // Delivery orders - depends on orders
        Schema::dropIfExists('delivery_orders');

        // Cash movements - depends on cash_registers
        Schema::dropIfExists('cash_movements');

        // Cash register expenses - depends on cash_registers
        Schema::dropIfExists('cash_register_expenses');

        // Cash registers
        Schema::dropIfExists('cash_registers');

        // Tables - depends on floors
        Schema::dropIfExists('tables');

        // Floors
        Schema::dropIfExists('floors');

        // Ventas sistema anterior
        Schema::dropIfExists('ventas_sistema_anterior');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new Exception('This migration cannot be reversed - restaurant module removal is irreversible');
    }
};
