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
        Schema::create('production_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('production_number');
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('responsible_employee_id')->constrained('employees');
            $table->foreignId('supervisor_employee_id')->nullable()->constrained('employees');
            $table->decimal('material_used', 10, 2)->nullable();
            $table->decimal('material_waste', 10, 2)->nullable();
            $table->decimal('material_missing', 10, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_tracking');
    }
};
