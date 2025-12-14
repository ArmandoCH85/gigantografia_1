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
        Schema::table('payments', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['cash_register_id']);

            // Drop column
            $table->dropColumn('cash_register_id');
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
