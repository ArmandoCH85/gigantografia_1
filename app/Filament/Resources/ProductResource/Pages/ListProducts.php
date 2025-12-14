<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Exception;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    /**
     * Convierte errores técnicos de base de datos en mensajes simples para usuarios
     */
    private function getFriendlyErrorMessage(QueryException $exception): string
    {
        $errorCode = $exception->getCode();
        $errorMessage = $exception->getMessage();

        // Errores de clave foránea (foreign key)
        if ($errorCode == 23000 && str_contains($errorMessage, 'foreign key constraint')) {
            return "🚫 Hay productos que no se pueden mostrar porque tienen datos relacionados eliminados.";
        }

        // Errores de conexión
        if (in_array($errorCode, ['2002', '2003', '2006'])) {
            return "🌐 Problema de conexión al cargar la lista de productos. Espera 10 segundos y vuelve a intentar.";
        }

        // Deadlock o bloqueo de datos
        if ($errorCode == 1213) {
            return "⏳ Los datos están ocupados. Cierra esta ventana, espera 5 segundos y abre de nuevo.";
        }

        // Error genérico
        return "😅 Ocurrió un problema al cargar la lista de productos. Intenta recargar la página.";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->successNotificationTitle('¡Producto creado!')
                ->successNotification(function () {
                    return Notification::make()
                        ->title('¡Producto creado!')
                        ->body('El producto ha sido agregado correctamente a la lista ✅')
                        ->success();
                })
                ->failureNotification(function (QueryException $exception) {
                    $friendlyMessage = $this->getFriendlyErrorMessage($exception);

                    return Notification::make()
                        ->title('Problema al crear el producto')
                        ->body($friendlyMessage . ' (Error detectado correctamente)')
                        ->danger()
                        ->persistent();
                }),


        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            \Filament\Tables\Actions\BulkActionGroup::make([
                \Filament\Tables\Actions\DeleteBulkAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar productos seleccionados')
                    ->modalDescription('¿Estás seguro de que quieres eliminar estos productos? Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, eliminar productos')
                    ->before(function () {
                        // Validar que los productos seleccionados puedan ser eliminados
                        $selectedRecords = $this->getSelectedTableRecords();

                        foreach ($selectedRecords as $record) {
                            if ($record->purchaseDetails()->exists()) {
                                throw new \Exception("Uno o más productos no pueden ser eliminados porque están registrados en compras.");
                            }

                            if ($record->orderDetails()->exists()) {
                                throw new \Exception("Uno o más productos no pueden ser eliminados porque están registrados en pedidos.");
                            }

                            if ($record->recipeDetails()->exists()) {
                                throw new \Exception("Uno o más productos no pueden ser eliminados porque están siendo usados en recetas.");
                            }
                        }
                    })
                    ->action(function () {
                        try {
                            $selectedRecords = $this->getSelectedTableRecords();
                            $count = $selectedRecords->count();

                            foreach ($selectedRecords as $record) {
                                $record->delete();
                            }

                            Notification::make()
                                ->title('¡Productos eliminados!')
                                ->body("Se eliminaron {$count} productos correctamente ✅")
                                ->success()
                                ->send();

                        } catch (QueryException $e) {
                            $friendlyMessage = $this->getFriendlyErrorMessage($e);

                            Notification::make()
                                ->title('No se pueden eliminar los productos')
                                ->body($friendlyMessage)
                                ->danger()
                                ->persistent()
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error en bulk delete de productos: ' . $e->getMessage(), [
                                'selected_count' => $this->getSelectedTableRecords()->count(),
                                'error_code' => $e->getCode(),
                                'error_message' => $e->getMessage()
                            ]);

                        } catch (Exception $e) {
                            $friendlyMessage = "🚫 " . $e->getMessage();

                            Notification::make()
                                ->title('No se pueden eliminar los productos')
                                ->body($friendlyMessage)
                                ->danger()
                                ->persistent()
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error general en bulk delete de productos: ' . $e->getMessage(), [
                                'selected_count' => $this->getSelectedTableRecords()->count()
                            ]);
                        }
                    }),
            ]),
        ];
    }
}
