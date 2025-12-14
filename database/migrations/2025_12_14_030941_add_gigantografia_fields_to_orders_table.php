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
            $table->foreignId('designer_id')->nullable()->constrained('employees');
            $table->string('production_number')->nullable();
            $table->date('delivery_date')->nullable();
            $table->time('delivery_time')->nullable();
            $table->string('delivery_type')->nullable();
            $table->time('production_start_time')->nullable();
            $table->time('production_end_time')->nullable();
            $table->string('delivery_province')->nullable();
            $table->string('delivery_recipient_name')->nullable();
            $table->string('delivery_recipient_phone')->nullable();
            $table->string('delivery_recipient_dni')->nullable();
            $table->string('delivery_destination')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['designer_id']);
            $table->dropColumn([
                'designer_id',
                'production_number',
                'delivery_date',
                'delivery_time',
                'delivery_type',
                'production_start_time',
                'production_end_time',
                'delivery_province',
                'delivery_recipient_name',
                'delivery_recipient_phone',
                'delivery_recipient_dni',
                'delivery_destination'
            ]);
        });
    }
};
