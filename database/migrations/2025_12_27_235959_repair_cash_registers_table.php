<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cash_registers')) {
            Schema::create('cash_registers', function (Blueprint $table) {
                // Fields from 2025_03_25 (Base)
                $table->id();
                $table->dateTime('opening_datetime');
                $table->dateTime('closing_datetime')->nullable();
                $table->decimal('opening_amount', 12, 2);
                $table->decimal('expected_amount', 12, 2)->nullable();
                $table->decimal('actual_amount', 12, 2)->nullable();
                $table->decimal('difference', 12, 2)->nullable();
                $table->foreignId('opened_by')->constrained('users');
                $table->foreignId('closed_by')->nullable()->constrained('users');
                $table->text('observations')->nullable();
                $table->boolean('is_active')->default(true);
                
                // Fields from 2025_04_04 (Status) - Correcting schema drift
                $table->enum('status', ['open', 'closed'])->default('open');
                
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Do not drop in repair migration to avoid accidental data loss if rolled back
    }
};
