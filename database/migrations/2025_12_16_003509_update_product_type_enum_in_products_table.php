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
        // MySQL no permite modificar ENUMs directamente con ALTER COLUMN
        // Debemos usar DB::statement para cambiar el ENUM
        DB::statement("ALTER TABLE products MODIFY COLUMN product_type ENUM('ingredient', 'sale_item', 'both', 'gigantografia') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver al enum original (removiendo 'gigantografia')
        DB::statement("ALTER TABLE products MODIFY COLUMN product_type ENUM('ingredient', 'sale_item', 'both') NOT NULL");
    }
};
