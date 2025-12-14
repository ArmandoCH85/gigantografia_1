<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ProductStock;

class Product extends Model
{
    use SoftDeletes;

    /**
     * OPTIMIZACIÓN: Relaciones que se cargan por defecto para evitar N+1
     */
    protected $with = ['category'];

    /**
     * Los tipos de productos disponibles.
     */
    const TYPE_SALE_ITEM = 'sale_item'; // Venta por unidad (stock)
    const TYPE_SERVICE_GIGANTOGRAFIA = 'gigantografia'; // Venta por m2 (medidas)

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'sale_price',
        'current_cost',
        'current_stock',
        'product_type',
        'category_id',
        'active',
        'image_path',
        'available'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'sale_price' => 'decimal:2',
        'current_cost' => 'decimal:2',
        'current_stock' => 'decimal:3',
        'active' => 'boolean',
        'has_recipe' => 'boolean',
        'available' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Accessor para el campo 'price' - redirige a 'sale_price'
     * Esto mantiene compatibilidad con el POS que espera $product->price
     */
    public function getPriceAttribute()
    {
        return $this->sale_price;
    }

    /**
     * Accessor para el campo 'image' - genera la URL completa de la imagen
     * Esto mantiene compatibilidad con diferentes vistas que esperan $product->image
     */
    public function getImageAttribute()
    {
        if (!$this->image_path) {
            return null;
        }

        return asset('storage/' . $this->image_path);
    }

    /**
     * Obtiene la categoría del producto.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Verifica si el producto es un artículo de venta.
     */
    public function isSaleItem(): bool
    {
        return $this->product_type === self::TYPE_SALE_ITEM;
    }

    /**
     * Verifica si el producto es un servicio de gigantografía (por m2).
     */
    public function isGigantografia(): bool
    {
        return $this->product_type === self::TYPE_SERVICE_GIGANTOGRAFIA;
    }

    /**
     * Obtiene los movimientos de inventario asociados a este producto.
     */
    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class, 'product_id');
    }

    /**
     * Calcula el stock actual basado en los movimientos de inventario.
     *
     * @return float El stock actual calculado
     */
    public function calculateCurrentStock(): float
    {
        return $this->inventoryMovements()->sum('quantity') ?? 0;
    }

    /**
     * Actualiza el stock actual basado en los movimientos de inventario.
     *
     * @return bool Si la actualización fue exitosa
     */
    public function updateCurrentStock(): bool
    {
        $this->current_stock = $this->calculateCurrentStock();
        return $this->save();
    }

    /**
     * Calcula el costo promedio basado en los movimientos de inventario de compra.
     *
     * @return float El costo promedio calculado
     */
    public function calculateAverageCost(): float
    {
        // Obtener solo movimientos de compra con cantidad positiva
        $purchaseMovements = $this->inventoryMovements()
            ->where('movement_type', InventoryMovement::TYPE_PURCHASE)
            ->where('quantity', '>', 0)
            ->get();

        if ($purchaseMovements->isEmpty()) {
            return $this->current_cost ?? 0;
        }

        $totalCost = 0;
        $totalQuantity = 0;

        foreach ($purchaseMovements as $movement) {
            $totalCost += $movement->quantity * $movement->unit_cost;
            $totalQuantity += $movement->quantity;
        }

        return $totalQuantity > 0 ? $totalCost / $totalQuantity : 0;
    }

    /**
     * Actualiza el costo promedio basado en los movimientos de inventario.
     *
     * @return bool Si la actualización fue exitosa
     */
    public function updateAverageCost(): bool
    {
        $this->current_cost = $this->calculateAverageCost();
        return $this->save();
    }

    /**
     * Agrega stock al producto utilizando el método FIFO.
     *
     * @param float $quantity Cantidad a agregar
     * @param float $unitCost Costo unitario
     * @param int|null $warehouseId ID del almacén
     * @param string|null $expiryDate Fecha de vencimiento (formato Y-m-d)
     * @param int|null $purchaseId ID de la compra relacionada
     * @return IngredientStock|null El stock creado (si es un ingrediente) o null (si es un producto normal)
     */
    public function addStock(float $quantity, float $unitCost, ?int $warehouseId = null, ?string $expiryDate = null, ?int $purchaseId = null)
    {
        // Crear registro en ProductStock y actualizar el stock
        $stock = ProductStock::create([
            'product_id' => $this->id,
            'warehouse_id' => $warehouseId ?? Warehouse::where('is_default', true)->first()?->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'expiry_date' => $expiryDate,
            'status' => 'available',
            'purchase_id' => $purchaseId
        ]);

        $this->current_stock += $quantity;

        // Calcular el nuevo costo promedio
        $totalCost = ($this->current_stock - $quantity) * $this->current_cost + $quantity * $unitCost;
        $this->current_cost = $this->current_stock > 0 ? $totalCost / $this->current_stock : $unitCost;

        $this->save();

        return $stock;
    }
}
