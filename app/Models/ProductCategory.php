<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'product_categories';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'parent_category_id',
        'visible_in_menu',
        'display_order'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'visible_in_menu' => 'boolean',
        'display_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtiene la categoría padre.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_category_id');
    }

    /**
     * Obtiene las subcategorías.
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_category_id');
    }

    /**
     * Obtiene los productos de esta categoría.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Obtiene los materiales asociados a esta categoría.
     */
    public function materials(): HasMany
    {
        return $this->hasMany(ProductMaterial::class, 'category_id');
    }
}
