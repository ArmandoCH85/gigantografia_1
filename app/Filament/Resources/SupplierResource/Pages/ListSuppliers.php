<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Exception;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    /**
     * Convierte errores técnicos de base de datos en mensajes amigables para usuarios
     */
    private function getFriendlyErrorMessage(QueryException $exception): string
    {
        $errorCode = $exception->getCode();
        $errorMessage = $exception->getMessage();

        // Errores de clave foránea (foreign key)
        if ($errorCode == 23000 && str_contains($errorMessage, 'foreign key constraint')) {
            return "🚫 ¡Algunos proveedores no se pueden mostrar!\n\n📋 Motivo: Tienen datos relacionados que fueron eliminados incorrectamente.\n\n💡 Qué hacer:\n• Contacta al administrador del sistema\n• Mientras tanto, puedes crear nuevos proveedores\n• Los datos existentes están seguros";
        }

        // Errores de conexión
        if (in_array($errorCode, ['2002', '2003', '2006'])) {
            return "🌐 ¡Problema de conexión a internet!\n\n💡 Qué hacer:\n• Verifica tu conexión a internet\n• Espera 10 segundos y recarga la página\n• Si persiste, contacta al administrador";
        }

        // Deadlock o bloqueo de datos
        if ($errorCode == 1213) {
            return "⏳ ¡Los datos están siendo usados por otro usuario!\n\n💡 Qué hacer:\n• Espera 5 segundos\n• Recarga la página\n• Intenta de nuevo";
        }

        // Error genérico
        return "😅 ¡Ups! Algo salió mal al cargar la lista de proveedores.\n\n💡 Qué hacer:\n• Recarga la página\n• Si el problema persiste, contacta al administrador\n• Mientras tanto, puedes crear nuevos proveedores";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->successNotificationTitle('🎉 ¡Proveedor creado exitosamente!')
                ->successNotification(function () {
                    return Notification::make()
                        ->title('🎉 ¡Proveedor creado exitosamente!')
                        ->body('El nuevo proveedor ha sido agregado correctamente a tu lista.')
                        ->success()
                        ->duration(5000);
                })
                ->failureNotification(function (QueryException $exception) {
                    $friendlyMessage = $this->getFriendlyErrorMessage($exception);

                    return Notification::make()
                        ->title('❌ No se pudo crear el proveedor')
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
                    ->modalHeading('🗑️ Eliminar proveedores seleccionados')
                    ->modalDescription('¿Estás seguro de que quieres eliminar estos proveedores? Esta acción no se puede deshacer y solo funcionará si los proveedores no tienen compras o ingredientes asociados.')
                    ->modalSubmitActionLabel('Sí, eliminar proveedores')
                    ->modalCancelActionLabel('Cancelar')
                    ->before(function () {
                        // Validar que los proveedores seleccionados puedan ser eliminados
                        $selectedRecords = $this->getSelectedTableRecords();
                        $problemProviders = [];

                        foreach ($selectedRecords as $record) {
                            $issues = [];
                            
                            if ($record->purchases()->exists()) {
                                $purchasesCount = $record->purchases()->count();
                                $issues[] = "{$purchasesCount} compra(s)";
                            }

                            if ($record->ingredients()->exists()) {
                                $ingredientsCount = $record->ingredients()->count();
                                $issues[] = "{$ingredientsCount} ingrediente(s)";
                            }

                            if (!empty($issues)) {
                                $problemProviders[] = "• {$record->business_name}: " . implode(', ', $issues);
                            }
                        }

                        if (!empty($problemProviders)) {
                            $providersList = implode("\n", $problemProviders);
                            throw new \Exception("🚫 ¡No se pueden eliminar algunos proveedores!\n\n📋 Proveedores con datos asociados:\n{$providersList}\n\n💡 Qué hacer:\n• Elimina primero las compras e ingredientes asociados\n• O deselecciona esos proveedores\n• Luego podrás eliminar los demás");
                        }
                    })
                    ->action(function () {
                        try {
                            $selectedRecords = $this->getSelectedTableRecords();
                            $count = $selectedRecords->count();
                            $deletedNames = $selectedRecords->pluck('business_name')->take(3)->join(', ');
                            $moreText = $count > 3 ? " y " . ($count - 3) . " más" : "";

                            foreach ($selectedRecords as $record) {
                                $record->delete();
                            }

                            Notification::make()
                                ->title('🎉 ¡Proveedores eliminados exitosamente!')
                                ->body("Se eliminaron {$count} proveedores: {$deletedNames}{$moreText}.")
                                ->success()
                                ->duration(5000)
                                ->send();

                        } catch (QueryException $e) {
                            $friendlyMessage = $this->getFriendlyErrorMessage($e);

                            Notification::make()
                                ->title('❌ No se pudieron eliminar los proveedores')
                                ->body($friendlyMessage)
                                ->danger()
                                ->persistent()
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error en bulk delete de proveedores: ' . $e->getMessage(), [
                                'selected_count' => $this->getSelectedTableRecords()->count(),
                                'error_code' => $e->getCode(),
                                'error_message' => $e->getMessage()
                            ]);

                        } catch (Exception $e) {
                            $friendlyMessage = $e->getMessage();

                            Notification::make()
                                ->title('❌ No se pudieron eliminar los proveedores')
                                ->body($friendlyMessage)
                                ->danger()
                                ->persistent()
                                ->send();

                            \Illuminate\Support\Facades\Log::error('Error general en bulk delete de proveedores: ' . $e->getMessage(), [
                                'selected_count' => $this->getSelectedTableRecords()->count()
                            ]);
                        }
                    }),
            ]),
        ];
    }
}
