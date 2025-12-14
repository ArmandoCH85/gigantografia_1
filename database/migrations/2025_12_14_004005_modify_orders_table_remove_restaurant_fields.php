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
        Schema::table('orders', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['table_id']);
            $table->dropForeign(['cash_register_id']);
            $table->dropForeign(['parent_id']);

            // Drop columns
            $table->dropColumn([
                'table_id',
                'cash_register_id',
                'parent_id',
                'service_type',
                'number_of_guests',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new Exception('This migration cannot be reversed - restaurant module removal is irreversible');
    }
};
