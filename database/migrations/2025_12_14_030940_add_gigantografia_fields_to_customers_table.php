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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('sales_channel')->nullable();
            $table->string('customer_type')->nullable();
            $table->foreignId('price_tier_id')->nullable()->constrained('customer_price_tiers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['price_tier_id']);
            $table->dropColumn(['sales_channel', 'customer_type', 'price_tier_id']);
        });
    }
};
