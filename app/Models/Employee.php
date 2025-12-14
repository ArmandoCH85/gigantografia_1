<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\DeliveryOrder;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'document_number',
        'phone',
        'address',
        'position',
        'employee_role', // diseñador, comercial, responsable_produccion, supervisor
        'hire_date',
        'base_salary',
        'user_id',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'hire_date' => 'date',
        'base_salary' => 'decimal:2',
    ];

    /**
     * Obtiene el nombre completo del empleado.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Obtiene el usuario asociado al empleado.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtiene los pedidos de delivery asignados a este empleado.
     */
    public function deliveryOrders()
    {
        return $this->hasMany(DeliveryOrder::class, 'delivery_person_id');
    }

    /**
     * Scope para filtrar empleados por posición.
     */
    public function scopeByPosition($query, string $position)
    {
        return $query->where('position', $position);
    }

    /**
     * Scope para filtrar repartidores.
     */
    public function scopeDeliveryPersons($query)
    {
        return $query->where('position', 'Delivery');
    }

    /**
     * Verifica si el empleado es un repartidor.
     */
    public function isDeliveryPerson(): bool
    {
        return $this->position === 'Delivery';
    }

    /**
     * Scope para filtrar empleados por rol específico.
     * Roles: diseñador, comercial, responsable_produccion, supervisor
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('employee_role', $role);
    }
}
