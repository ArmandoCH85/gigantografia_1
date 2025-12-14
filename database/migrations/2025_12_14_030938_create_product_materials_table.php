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
        Schema::create('product_materials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('max_width', 8, 2)->nullable();
            $table->decimal('max_height', 8, 2)->nullable();
            $table->foreignId('category_id')->constrained('product_categories');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_materials');
    }
};
