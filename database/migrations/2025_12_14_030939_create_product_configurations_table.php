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
        Schema::create('product_configurations', function (Blueprint $table) {
            $table->id();
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->foreignId('material_id')->constrained('product_materials');
            $table->foreignId('finish_1_id')->nullable()->constrained('product_finishes');
            $table->foreignId('finish_2_id')->nullable()->constrained('product_finishes');
            $table->foreignId('finish_3_id')->nullable()->constrained('product_finishes');
            $table->integer('finish_1_quantity')->nullable();
            $table->integer('finish_2_quantity')->nullable();
            $table->integer('finish_3_quantity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_configurations');
    }
};
