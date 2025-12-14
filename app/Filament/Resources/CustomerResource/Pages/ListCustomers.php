<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Exception;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    /**
     * Convierte errores técnicos de base de datos en mensajes simples para usuarios
     */
    private function getFriendlyErrorMessage(QueryException $exception): string
    {
        $errorCode = $exception->getCode();
        $errorMessage = $exception->getMessage();

        // Errores de clave foránea (foreign key)
        if ($errorCode == 23000 && str_contains($errorMessage, 'foreign key constraint')) {
            return "🚫 Hay clientes que no se pueden mostrar porque tienen datos relacionados eliminados.";
        }

        // Errores de conexión
        if (in_array($errorCode, ['2002', '2003', '2006'])) {
            return "🌐 Problema de conexión al cargar la lista de clientes. Espera 10 segundos y vuelve a intentar.";
        }

        // Deadlock o bloqueo de datos
        if ($errorCode == 1213) {
            return "⏳ Los datos están ocupados. Cierra esta ventana, espera 5 segundos y abre de nuevo.";
        }

        // Error genérico
        return "😅 Ocurrió un problema al cargar la lista de clientes. Intenta recargar la página.";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->successNotificationTitle('¡Cliente creado!')
                ->successNotification(function () {
                    return Notification::make()
                        ->title('¡Cliente creado!')
                        ->body('El cliente ha sido agregado correctamente a la lista ✅')
                        ->success();
                })
                ->failureNotification(function (QueryException $exception) {
                    $friendlyMessage = $this->getFriendlyErrorMessage($exception);

                    return Notification::make()
                        ->title('Problema al crear el cliente')
                        ->body($friendlyMessage)
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
                    ->modalHeading('Eliminar clientes seleccionados')
                    ->modalDescription('¿Estás seguro de que quieres eliminar estos clientes? Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, eliminar clientes')
                    ->before(function () {
                        // Validar que los clientes seleccionados puedan ser eliminados
                        $selectedRecords = $this->getSelectedTableRecords();

                        foreach ($selectedRecords as $record) {
                            if ($record->orders()->exists()) {
                                throw new \Exception("Uno o más clientes no pueden ser eliminados porque tienen pedidos realizados.");
                            }

                            if ($record->deliveryOrders()->exists()) {
                                throw new \Exception("Uno o más clientes no pueden ser eliminados porque tienen pedidos de delivery.");
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
                                ->title('¡Clientes eliminados!')
                                ->body("Se eliminaron {$count} clientes correctamente ✅")
                                ->success()
                                ->send();

                        } catch (QueryException $e) {
                            $friendlyMessage = $this->getFriendlyErrorMessage($e);

                            Notification::make()
                                ->title('No se pueden eliminar los clientes')
                                ->body($friendlyMessage)
                                ->danger()
                                ->persistent()
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error en bulk delete de clientes: ' . $e->getMessage(), [
                                'selected_count' => $this->getSelectedTableRecords()->count(),
                                'error_code' => $e->getCode(),
                                'error_message' => $e->getMessage()
                            ]);

                        } catch (Exception $e) {
                            $friendlyMessage = "🚫 " . $e->getMessage();

                            Notification::make()
                                ->title('No se pueden eliminar los clientes')
                                ->body($friendlyMessage)
                                ->danger()
                                ->persistent()
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error general en bulk delete de clientes: ' . $e->getMessage(), [
                                'selected_count' => $this->getSelectedTableRecords()->count()
                            ]);
                        }
                    }),
            ]),
        ];
    }
}
