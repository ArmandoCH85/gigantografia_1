<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'document_type',
        'document_number',
        'name',
        'phone',
        'email',
        'address',
        'address_references',
        'tax_validated',
        'price_tier_id', // Nuevo campo
        'sales_channel', // Nuevo campo
        'customer_type', // Nuevo campo
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tax_validated' => 'boolean',
    ];

    /**
     * Los tipos de documentos disponibles.
     *
     * @var array<string, string>
     */
    public const DOCUMENT_TYPES = [
        'DNI' => 'DNI',
        'RUC' => 'RUC',
    ];

    /**
     * Obtiene un texto con el documento formateado (tipo y número).
     *
     * @return string
     */
    public function getFormattedDocumentAttribute(): string
    {
        return "{$this->document_type}: {$this->document_number}";
    }

    /**
     * Obtiene la dirección completa con las referencias.
     *
     * @return string|null
     */
    public function getFullAddressAttribute(): ?string
    {
        if (empty($this->address)) {
            return null;
        }

        return empty($this->address_references)
            ? $this->address
            : "{$this->address} - {$this->address_references}";
    }

    /**
     * Valida si el cliente tiene un RUC.
     *
     * @return bool
     */
    public function hasRuc(): bool
    {
        return $this->document_type === 'RUC';
    }

    /**
     * Valida si el cliente tiene un DNI.
     *
     * @return bool
     */
    public function hasDni(): bool
    {
        return $this->document_type === 'DNI';
    }

    /**
     * Scope para filtrar por tipo de documento.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDocumentType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope para filtrar clientes validados fiscalmente.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTaxValidated($query)
    {
        return $query->where('tax_validated', true);
    }

    /**
     * Obtiene las reservas del cliente.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Obtiene las reservas activas del cliente (pendientes o confirmadas).
     */
    public function activeReservations(): HasMany
    {
        return $this->reservations()->whereIn('status', ['pending', 'confirmed']);
    }

    /**
     * Obtiene o crea el cliente genérico del sistema.
     *
     * @return Customer|null
     */
    public static function getGenericCustomer(): ?Customer
    {
        // Buscar cliente genérico existente por ID 7 o por documento
        $genericCustomer = static::where('id', 7)->first();

        if (!$genericCustomer) {
            // Si no existe el ID 7, buscar por documento
            $genericCustomer = static::where('document_type', 'DNI')
                ->where('document_number', '00000000')
                ->first();
        }

        // Si no existe ninguno, crear "Publico en General"
        if (!$genericCustomer) {
            $genericCustomer = static::create([
                'document_type' => 'DNI',
                'document_number' => '00000000',
                'name' => 'Publico en General',
                'phone' => null,
                'email' => null,
                'address' => null,
                'address_references' => null,
                'tax_validated' => false,
            ]);
        }

        return $genericCustomer;
    }

    /**
     * Obtiene el nivel de precios del cliente.
     */
    public function priceTier()
    {
        return $this->belongsTo(CustomerPriceTier::class, 'price_tier_id');
    }
}
