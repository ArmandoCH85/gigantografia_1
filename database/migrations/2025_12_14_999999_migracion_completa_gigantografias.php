<?php

/**
 * ================================================================
 * MIGRACIÓN COMPLETA GIGANTOGRAFÍAS - CORRECIONES
 * ================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PARTE 1: CORREGIR product_materials
        if (Schema::hasTable('product_materials')) {
            Schema::table('product_materials', function (Blueprint $table) {
                if (!Schema::hasColumn('product_materials', 'code')) {
                    $table->string('code', 50)->nullable()->after('id');
                }
                if (!Schema::hasColumn('product_materials', 'active')) {
                    $table->boolean('active')->default(1);
                }
                if (Schema::hasColumn('product_materials', 'unit_price')) {
                    $table->dropColumn('unit_price');
                }
                if (!Schema::hasColumn('product_materials', 'price_factor_standard')) {
                    $table->decimal('price_factor_standard', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('product_materials', 'price_factor_wholesale')) {
                    $table->decimal('price_factor_wholesale', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('product_materials', 'price_factor_campaign')) {
                    $table->decimal('price_factor_campaign', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('product_materials', 'sheet_width')) {
                    $table->decimal('sheet_width', 10, 2)->nullable();
                }
            });

            $materials = DB::table('product_materials')->get();
            foreach ($materials as $material) {
                if (empty($material->code)) {
                    DB::table('product_materials')->where('id', $material->id)->update(['code' => 'temp_' . $material->id]);
                }
            }

            $indexes = DB::select("SHOW INDEX FROM product_materials WHERE Key_name = 'product_materials_code_unique'");
            if (empty($indexes)) {
                Schema::table('product_materials', function (Blueprint $table) {
                    $table->unique('code');
                });
            }
        }

        // PARTE 2: CORREGIR product_finishes
        if (Schema::hasTable('product_finishes')) {
            Schema::table('product_finishes', function (Blueprint $table) {
                if (!Schema::hasColumn('product_finishes', 'code')) {
                    $table->string('code', 50)->nullable()->after('id');
                }
                if (!Schema::hasColumn('product_finishes', 'active')) {
                    $table->boolean('active')->default(1);
                }
                if (Schema::hasColumn('product_finishes', 'additional_cost')) {
                    $table->dropColumn('additional_cost');
                }
                if (!Schema::hasColumn('product_finishes', 'cost_per_unit')) {
                    $table->decimal('cost_per_unit', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('product_finishes', 'cost_formula')) {
                    $table->string('cost_formula', 100)->nullable();
                }
                if (!Schema::hasColumn('product_finishes', 'formula_type')) {
                    $table->enum('formula_type', ['fixed', 'per_quantity', 'width_based', 'height_based'])->default('fixed');
                }
            });

            $finishes = DB::table('product_finishes')->get();
            foreach ($finishes as $finish) {
                if (empty($finish->code)) {
                    DB::table('product_finishes')->where('id', $finish->id)->update(['code' => 'temp_' . $finish->id]);
                }
            }

            $indexes = DB::select("SHOW INDEX FROM product_finishes WHERE Key_name = 'product_finishes_code_unique'");
            if (empty($indexes)) {
                Schema::table('product_finishes', function (Blueprint $table) {
                    $table->unique('code');
                });
            }
        }

        // PARTE 3: customer_price_tiers
        if (Schema::hasTable('customer_price_tiers')) {
            Schema::table('customer_price_tiers', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_price_tiers', 'code')) {
                    $table->string('code', 50)->nullable()->after('id');
                }
                if (!Schema::hasColumn('customer_price_tiers', 'description')) {
                    $table->text('description')->nullable()->after('name');
                }
                if (!Schema::hasColumn('customer_price_tiers', 'active')) {
                    $table->boolean('active')->default(1);
                }
            });

            $tiers = DB::table('customer_price_tiers')->get();
            foreach ($tiers as $tier) {
                if (empty($tier->code)) {
                    DB::table('customer_price_tiers')->where('id', $tier->id)->update(['code' => 'temp_' . $tier->id]);
                }
            }

            $indexes = DB::select("SHOW INDEX FROM customer_price_tiers WHERE Key_name = 'customer_price_tiers_code_unique'");
            if (empty($indexes)) {
                Schema::table('customer_price_tiers', function (Blueprint $table) {
                    $table->unique('code');
                });
            }
        }

        // PARTE 4: customers
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'sales_channel')) {
                    $table->string('sales_channel', 50)->nullable();
                }
                if (!Schema::hasColumn('customers', 'customer_type')) {
                    $table->string('customer_type', 50)->nullable();
                }
                if (!Schema::hasColumn('customers', 'price_tier_id')) {
                    $table->foreignId('price_tier_id')->nullable()->constrained('customer_price_tiers');
                }
            });
        }

        // PARTE 5: orders
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'designer_id')) {
                    $table->foreignId('designer_id')->nullable()->constrained('employees');
                }
                if (!Schema::hasColumn('orders', 'production_number')) {
                    $table->string('production_number', 20)->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_date')) {
                    $table->date('delivery_date')->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_time')) {
                    $table->time('delivery_time')->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_type')) {
                    $table->enum('delivery_type', ['local', 'tercero', 'instalacion'])->nullable();
                }
                if (!Schema::hasColumn('orders', 'production_start_time')) {
                    $table->time('production_start_time')->nullable();
                }
                if (!Schema::hasColumn('orders', 'production_end_time')) {
                    $table->time('production_end_time')->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_province')) {
                    $table->string('delivery_province', 100)->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_recipient_name')) {
                    $table->string('delivery_recipient_name', 255)->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_recipient_phone')) {
                    $table->string('delivery_recipient_phone', 20)->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_recipient_dni')) {
                    $table->string('delivery_recipient_dni', 15)->nullable();
                }
                if (!Schema::hasColumn('orders', 'delivery_destination')) {
                    $table->string('delivery_destination', 255)->nullable();
                }
            });
        }

        // PARTE 6: payments
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'installment_number')) {
                    $table->integer('installment_number')->nullable();
                }
            });
        }

        // PARTE 7: employees
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'employee_role')) {
                    $table->string('employee_role', 50)->nullable();
                }
            });
        }

        // PARTE 8: quotations
        if (Schema::hasTable('quotations')) {
            Schema::table('quotations', function (Blueprint $table) {
                if (!Schema::hasColumn('quotations', 'commercial_employee_id')) {
                    $table->foreignId('commercial_employee_id')->nullable()->constrained('employees');
                }
            });
        }

        echo "\n✅ MIGRACIÓN COMPLETADA CON ÉXITO\n";
        echo "Ejecuta: php artisan db:seed --class=GigantografiaSeeder para cargar los datos\n\n";
    }

    public function down(): void
    {
        // Revertir cambios...
    }
};
