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
        Schema::table('quotation_details', function (Blueprint $table) {
            $table->decimal('width', 10, 2)->nullable()->after('quantity');
            $table->decimal('height', 10, 2)->nullable()->after('width');
            $table->foreignId('material_id')->nullable()->after('height')->constrained('product_materials');
            $table->json('finishes')->nullable()->after('material_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_details', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
            $table->dropColumn(['width', 'height', 'material_id', 'finishes']);
        });
    }
};
