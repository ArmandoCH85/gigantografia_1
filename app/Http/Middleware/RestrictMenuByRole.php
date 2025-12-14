<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RestrictMenuByRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Si el usuario tiene el rol "waiter", redirigir al mapa de mesas de Filament
        if ($user && $user->roles->where('name', 'waiter')->count() > 0) {
            $allowedRoutes = [
                'admin/mapa-mesas',

                'admin/login',
                'admin/logout',
                'admin/profile',
                'pos', // Permitir acceso completo al POS legacy
                'tables', // Permitir acceso al mapa de mesas legacy
                'livewire/update', // ✅ CRÍTICO: Permitir peticiones de Livewire
                'livewire/upload-file', // ✅ Permitir subida de archivos de Livewire
                'livewire', // ✅ Permitir todas las rutas de Livewire
                'orders',
                'print/prebill', // ✅ NUEVO: Permitir acceso a pre-cuentas
                'thermal-preview/pre-bill', // ✅ NUEVO: Permitir vista previa de pre-cuentas
            ];

            $currentPath = $request->path();

            // Permitir acceso a rutas específicas o que comiencen con rutas permitidas
            $isAllowed = false;
            foreach ($allowedRoutes as $route) {
                if ($currentPath === $route || str_starts_with($currentPath, $route . '/')) {
                    $isAllowed = true;
                    break;
                }
            }

            // Registrar información para depuración
            Log::info('RestrictMenuByRole middleware - Waiter', [
                'user_id' => $user->id,
                'name' => $user->name,
                'roles' => $user->roles->pluck('name')->toArray(),
                'current_path' => $currentPath,
                'is_allowed' => $isAllowed ? 'Sí' : 'No'
            ]);


            $isAllowedPath = $isAllowed;

            Log::info('RestrictMenuByRole - Verificando acceso waiter', [
                'current_path' => $currentPath,
                'is_allowed' => $isAllowedPath,
                'full_url' => $request->fullUrl(),
                'query_params' => $request->query()
            ]);

            if (!$isAllowedPath) {
                Log::info('RestrictMenuByRole - Redirigiendo waiter a mapa de mesas', [
                    'from_path' => $currentPath,
                    'to_path' => '/admin/mapa-mesas'
                ]);
                return redirect('/admin/mapa-mesas');
            }
        }

        // Si el usuario tiene el rol "delivery", solo permitir acceso a rutas específicas
        if ($user && $user->roles->where('name', 'delivery')->count() > 0) {
            $allowedRoutes = [
                '/',
                'admin',
                'admin/login',
                'admin/logout',
                'admin/profile',
                'admin/resources/delivery-orders',
                'admin/resources/delivery-orders/create',
                'tables',
                'delivery-tracking',
                'pos', // Permitir acceso a la ruta del POS para ver detalles del pedido
                'delivery/order', // Permitir acceso a la ruta de detalles del pedido
                'delivery/my-orders', // Permitir acceso a la ruta de pedidos del repartidor
                'delivery', // Permitir acceso a todas las rutas que comiencen con delivery
                'livewire/update', // ✅ CRÍTICO: Permitir peticiones de Livewire
                'livewire/upload-file', // ✅ Permitir subida de archivos de Livewire
                'livewire', // ✅ Permitir todas las rutas de Livewire
            ];

            $currentPath = $request->path();

            // Permitir acceso a rutas específicas o que comiencen con rutas permitidas
            $isAllowed = false;
            foreach ($allowedRoutes as $route) {
                if ($currentPath === $route || str_starts_with($currentPath, $route . '/')) {
                    $isAllowed = true;
                    break;
                }
            }

            // Registrar información para depuración
            Log::info('RestrictMenuByRole middleware - Delivery', [
                'user_id' => $user->id,
                'name' => $user->name,
                'roles' => $user->roles->pluck('name')->toArray(),
                'current_path' => $currentPath,
                'is_allowed' => $isAllowed ? 'Sí' : 'No'
            ]);

            // Si es la ruta raíz o admin, redirigir directamente a /tables
            if ($currentPath === '' || $currentPath === '/' || $currentPath === 'admin') {
                return redirect('/tables');
            }

            // Si no es una ruta permitida, redirigir directamente a /tables
            if (!$isAllowed) {
                return redirect('/tables');
            }
        }

        return $next($request);
    }
}
