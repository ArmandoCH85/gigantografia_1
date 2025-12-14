<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Services\SunatService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Blade;
use App\Models\CompanyConfig;
use App\Models\Customer;
use App\Helpers\PdfHelper;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Facturación y Ventas';

    protected static ?string $navigationLabel = 'Comprobantes';

    protected static ?string $modelLabel = 'Comprobante';

    protected static ?string $pluralModelLabel = 'Comprobantes';

    protected static ?string $slug = 'facturacion/comprobantes';

    protected static ?int $navigationSort = 1;

    /**
     * OPTIMIZACIÓN: Agregar eager loading para evitar N+1 queries
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'employee', 'details.product']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Comprobante')
                    ->schema([
                        Forms\Components\Select::make('invoice_type')
                            ->label('Tipo de Comprobante')
                            ->options([
                                'invoice' => 'Factura',
                                'receipt' => 'Boleta',
                                'sales_note' => 'Nota de Venta',
                            ])
                            // Tipo no editable una vez creado
                            ->disabled()
                            ->required(),

                        Forms\Components\TextInput::make('series')
                            ->label('Serie')
                            // Serie fija (proveniente de numeración controlada)
                            ->disabled()
                            ->required(),

                        Forms\Components\TextInput::make('number')
                            ->label('Número')
                            // Número correlativo no editable
                            ->disabled()
                            ->required(),

                        Forms\Components\DatePicker::make('issue_date')
                            ->label('Fecha de Emisión')
                            // Por trazabilidad mantenemos fija la fecha original
                            ->disabled()
                            ->required(),

                        Forms\Components\Select::make('tax_authority_status')
                            ->label('Estado SUNAT')
                            ->options([
                                'pending' => 'Pendiente',
                                'accepted' => 'Aceptado',
                                'rejected' => 'Rechazado',
                                'voided' => 'Anulado',
                            ])
                            // Estado interno solo lectura; cambios mediante acciones
                            ->disabled()
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Cliente')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'name')
                            // Permitimos cambiar el cliente SOLO si aún no ha sido aceptado por SUNAT (factura/boleta). En Notas de Venta siempre editable.
                            ->helperText('Si necesita corregir nombre/documento solo en este comprobante use los campos inferiores. Para modificar el cliente global vaya a: Clientes.')
                            ->disabled(fn(?Invoice $record) => $record && in_array($record->invoice_type, ['invoice', 'receipt']) && in_array($record->sunat_status, ['ACEPTADO', 'RECHAZADO']))
                            ->getOptionLabelFromRecordUsing(fn(Customer $c) => $c->name . ' — ' . $c->document_type . ': ' . $c->document_number)
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (!$state) {
                                    return;
                                }
                                $customer = Customer::find($state);
                                if (!$customer) {
                                    return;
                                }
                                // Siempre sincronizar al seleccionar un cliente para reflejar datos actualizados
                                $set('client_name', $customer->name);
                                $set('client_document', $customer->document_number);
                                $set('client_address', $customer->address);
                            })
                            ->searchable()
                            ->preload(),
                        Forms\Components\Fieldset::make('Datos impresos en este comprobante')
                            ->schema([
                                Forms\Components\TextInput::make('client_name')
                                    ->label('Nombre para este comprobante')
                                    ->maxLength(150)
                                    ->default(fn(?Invoice $record) => $record?->client_name ?? $record?->customer?->name)
                                    ->disabled(fn(?Invoice $record) => $record && in_array($record->invoice_type, ['invoice', 'receipt']) && $record->sunat_status === 'ACEPTADO')
                                    ->helperText('Si se deja vacío se usa el nombre del cliente.'),
                                Forms\Components\TextInput::make('client_document')
                                    ->label('Documento para este comprobante')
                                    ->maxLength(20)
                                    ->default(fn(?Invoice $record) => $record?->client_document ?? $record?->customer?->document_number)
                                    ->disabled(fn(?Invoice $record) => $record && in_array($record->invoice_type, ['invoice', 'receipt']) && $record->sunat_status === 'ACEPTADO')
                                    ->helperText('Reemplaza numero a enviar/impreso; no cambia el cliente original.'),
                                Forms\Components\TextInput::make('client_address')
                                    ->label('Dirección para este comprobante')
                                    ->maxLength(255)
                                    ->default(fn(?Invoice $record) => $record?->client_address ?? $record?->customer?->address)
                                    ->disabled(fn(?Invoice $record) => $record && in_array($record->invoice_type, ['invoice', 'receipt']) && $record->sunat_status === 'ACEPTADO')
                                    ->helperText('Si se deja vacío se usa la dirección del cliente.'),
                                Forms\Components\Placeholder::make('info_documento_cliente')
                                    ->label('Documento del cliente seleccionado')
                                    ->content(function (callable $get) {
                                        $cid = $get('customer_id');
                                        if (!$cid) {
                                            return '—';
                                        }
                                        $c = Customer::find($cid);
                                        return $c ? ($c->document_type . ': ' . $c->document_number) : '—';
                                    })
                            ])->columns(3)
                    ]),

                Forms\Components\Section::make('Importes')
                    ->schema([
                        Forms\Components\TextInput::make('taxable_amount')
                            ->label('Base Imponible')
                            ->prefix('S/')
                            ->disabled()
                            ->numeric(),

                        Forms\Components\TextInput::make('tax')
                            ->label('IGV')
                            ->prefix('S/')
                            ->disabled()
                            ->numeric(),

                        Forms\Components\TextInput::make('total')
                            ->label('Total')
                            ->prefix('S/')
                            ->disabled()
                            ->numeric(),
                    ])->columns(3),

                Forms\Components\Section::make('Anulación')
                    ->schema([
                        Forms\Components\DatePicker::make('voided_date')
                            ->label('Fecha de Anulación')
                            ->disabled(),

                        Forms\Components\Textarea::make('voided_reason')
                            ->label('Motivo de Anulación')
                            ->disabled()
                            ->visible(fn(Invoice $record): bool => $record->tax_authority_status === 'voided'),
                    ]),
                Forms\Components\Section::make('Notas / Auditoría')
                    ->schema([
                        Forms\Components\Placeholder::make('sunat_status_info')
                            ->label('Estado SUNAT actual')
                            ->content(fn(?Invoice $record) => $record?->sunat_status ?? 'Sin enviar'),
                        Forms\Components\Placeholder::make('edit_rules')
                            ->label('Reglas de Edición')
                            ->content('Solo se pueden modificar datos del cliente mientras el comprobante no haya sido ACEPTADO por SUNAT. Los importes se originan en el POS y no deben alterarse manualmente aquí.'),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(function (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder {
                return $query
                    ->orderBy('issue_date', 'desc')
                    ->orderBy('created_at', 'desc');
            })
            ->columns([
                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Fecha Emisión')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Hora Emisión')
                    ->time('H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('formattedNumber')
                    ->label('Número')
                    ->searchable(['series', 'number'])
                    ->sortable(['series', 'number'])
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('invoice_type')
                    ->label('Tipo')
                    ->formatStateUsing(
                        fn(string $state, $record): string =>
                        // Si la serie comienza con 'NV', es una Nota de Venta
                        str_starts_with($record->series, 'NV')
                            ? 'Nota de Venta'
                            : match ($state) {
                                'invoice' => 'Factura',
                                'receipt' => 'Boleta',
                                default => 'Nota de Venta',
                            }
                    )
                    ->colors([
                        'primary' => 'invoice',
                        'success' => fn($state, $record) => $state === 'receipt' && !str_starts_with($record->series, 'NV'),
                        'info' => fn($state, $record) => str_starts_with($record->series, 'NV'),
                        'warning' => fn($state, $record) => $state !== 'invoice' && $state !== 'receipt' && !str_starts_with($record->series, 'NV'),
                    ]),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('tax_authority_status')
                    ->label('Estado')
                    ->formatStateUsing(function ($record): string {
                        // Para Notas de Venta, mostrar el estado interno
                        if ($record->invoice_type === 'sales_note' || str_starts_with($record->series, 'NV')) {
                            return match ($record->tax_authority_status) {
                                'pending' => 'Registrado',
                                'accepted' => 'Aceptado',
                                'rejected' => 'Rechazado',
                                'voided' => 'Anulado',
                                default => 'Registrado',
                            };
                        }

                        // Para Boletas y Facturas, mostrar el estado SUNAT
                        return match ($record->sunat_status) {
                            'PENDIENTE' => 'Registrado',
                            'ENVIANDO' => 'Enviando',
                            'ACEPTADO' => 'Aceptado',
                            'RECHAZADO' => 'Rechazado',
                            'ERROR' => 'Error',
                            null => 'Registrado',
                            default => 'Registrado',
                        };
                    })
                    ->colors([
                        'warning' => fn($record) => ($record->invoice_type === 'sales_note' || str_starts_with($record->series, 'NV'))
                            ? $record->tax_authority_status === 'pending'
                            : in_array($record->sunat_status, ['PENDIENTE', null]),
                        'info' => fn($record) => $record->sunat_status === 'ENVIANDO',
                        'success' => fn($record) => ($record->invoice_type === 'sales_note' || str_starts_with($record->series, 'NV'))
                            ? $record->tax_authority_status === 'accepted'
                            : $record->sunat_status === 'ACEPTADO',
                        'danger' => fn($record) => ($record->invoice_type === 'sales_note' || str_starts_with($record->series, 'NV'))
                            ? in_array($record->tax_authority_status, ['rejected'])
                            : in_array($record->sunat_status, ['RECHAZADO', 'ERROR']),
                        'gray' => fn($record) => $record->tax_authority_status === 'voided',
                    ]),

                Tables\Columns\BadgeColumn::make('sunat_status')
                    ->label('Estado SUNAT')
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'PENDIENTE' => 'Pendiente',
                        'ENVIANDO' => 'Enviando',
                        'ACEPTADO' => 'Aceptado',
                        'RECHAZADO' => 'Rechazado',
                        'ERROR' => 'Error',
                        'NO_APLICA' => 'No aplica',
                        default => 'Sin enviar',
                    })
                    ->colors([
                        'gray' => fn(?string $state): bool => $state === null || $state === 'NO_APLICA',
                        'warning' => 'PENDIENTE',
                        'info' => 'ENVIANDO',
                        'success' => 'ACEPTADO',
                        'danger' => ['RECHAZADO', 'ERROR'],
                    ])
                    ->visible(
                        fn($livewire): bool =>
                        // SOLO mostrar para Boletas y Facturas - NO Notas de Venta
                        in_array($livewire->getTableFilterState('invoice_type'), ['invoice', 'receipt']) ||
                            $livewire->getTableFilterState('invoice_type') === null
                    ),

                Tables\Columns\TextColumn::make('voided_date')
                    ->label('Fecha Anulación')
                    ->date('d/m/Y')
                    ->visible(fn($livewire): bool => $livewire->getTableFilterState('tax_authority_status') === 'voided')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sunat_description')
                    ->label('Motivo Rechazo')
                    ->wrap()
                    ->visible(fn($record) => ($record?->sunat_status === 'RECHAZADO')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('invoice_type')
                    ->label('Tipo')
                    ->options([
                        'invoice' => 'Factura',
                        'receipt' => 'Boleta',
                        'sales_note' => 'Nota de Venta',
                    ]),
                Tables\Filters\SelectFilter::make('tax_authority_status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'accepted' => 'Aceptado',
                        'rejected' => 'Rechazado',
                        'voided' => 'Anulado',
                    ]),
                Tables\Filters\Filter::make('issue_date')
                    ->form([
                        Forms\Components\DatePicker::make('issued_from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('issued_until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['issued_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('issue_date', '>=', $date),
                            )
                            ->when(
                                $data['issued_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('issue_date', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->visible(
                        fn(Invoice $record): bool =>
                        // Permitir editar siempre Notas de Venta.
                        // Para Factura/Boleta permitir solo si aún no aceptada ni anulada.
                        ($record->invoice_type === 'sales_note') ||
                            (in_array($record->invoice_type, ['invoice', 'receipt']) && !in_array($record->sunat_status, ['ACEPTADO']) && $record->tax_authority_status !== 'voided')
                    ),
                Action::make('send_to_sunat')
                    ->label('Enviar a SUNAT')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(
                        fn(Invoice $record): bool =>
                        // SOLO Boletas y Facturas - NO Notas de Venta
                        in_array($record->invoice_type, ['invoice', 'receipt']) &&
                            ($record->sunat_status === 'PENDIENTE' || $record->sunat_status === null)
                    )
                    ->form([
                        Forms\Components\Placeholder::make('info')
                            ->label('Método de Envío')
                            ->content('Se enviará vía QPS (qpse.pe) - Método estable y confiable')
                    ])
                    ->modalHeading('Enviar Comprobante a SUNAT')
                    ->modalDescription(
                        fn(Invoice $record): string =>
                        "Comprobante: {$record->series}-{$record->number}\nCliente: {$record->customer->name}\nTotal: S/ " . number_format($record->total, 2)
                    )
                    ->modalSubmitActionLabel('Enviar a SUNAT')
                    ->action(function (Invoice $record, array $data): void {
                        try {
                            // Envío exclusivamente vía QPS
                            $qpsService = new \App\Services\QpsService();
                            $result = $qpsService->sendInvoiceViaQps($record);

                            if ($result['success']) {
                                Notification::make()
                                    ->title('Comprobante enviado exitosamente')
                                    ->body('El comprobante ha sido enviado y aceptado por SUNAT vía QPS')
                                    ->success()
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Error al enviar comprobante')
                                    ->body($result['message'])
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error inesperado')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
                Action::make('void')
                    ->label('Anular')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn(Invoice $record): bool => $record->canBeVoided())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo de Anulación')
                            ->required()
                            ->minLength(5)
                            ->maxLength(255),
                        Forms\Components\Checkbox::make('confirm')
                            ->label('Confirmo que deseo anular este comprobante y entiendo que esta acción no se puede deshacer.')
                            ->required()
                            ->default(false),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Anular Comprobante')
                    ->modalDescription('Esta acción es irreversible. El comprobante quedará registrado como anulado tanto en el sistema como en SUNAT.')
                    ->modalSubmitActionLabel('Anular Comprobante')
                    ->action(function (Invoice $record, array $data): void {
                        if (!$data['confirm']) {
                            Notification::make()
                                ->title('Debe confirmar la anulación')
                                ->danger()
                                ->send();
                            return;
                        }

                        if (!$record->canBeVoided()) {
                            Notification::make()
                                ->title('No se puede anular este comprobante')
                                ->body('Verifique que no hayan pasado más de 7 días desde su emisión y que no haya sido anulado previamente.')
                                ->danger()
                                ->send();
                            return;
                        }

                        if ($record->void($data['reason'])) {
                            Notification::make()
                                ->title('Comprobante anulado correctamente')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error al anular el comprobante')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('print')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn(Invoice $record) => route('filament.admin.invoices.print-ticket', $record))
                    ->openUrlInNewTab(),
                Action::make('download_xml')
                    ->label('Descargar XML')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(
                        fn(Invoice $record): bool => (function ($path) {
                            if (empty($path)) return false;
                            if (file_exists($path)) return true;
                            $normalized = ltrim(str_replace('\\', '/', $path), '/');
                            if (file_exists(storage_path('app/private/' . $normalized))) return true;
                            if (file_exists(storage_path('app/' . $normalized))) return true;
                            return false;
                        })($record->xml_path)
                    )
                    ->url(fn(Invoice $record): string => route('filament.admin.invoices.download-xml', $record))
                    ->openUrlInNewTab(),
                Action::make('download_cdr')
                    ->label('Descargar CDR')
                    ->icon('heroicon-o-document-check')
                    ->color('warning')
                    ->visible(
                        fn(Invoice $record): bool => (function ($path) {
                            if (empty($path)) return false;
                            if (file_exists($path)) return true;
                            $normalized = ltrim(str_replace('\\', '/', $path), '/');
                            if (file_exists(storage_path('app/private/' . $normalized))) return true;
                            if (file_exists(storage_path('app/' . $normalized))) return true;
                            return false;
                        })($record->cdr_path)
                    )
                    ->url(fn(Invoice $record): string => route('filament.admin.invoices.download-cdr', $record))
                    ->openUrlInNewTab(),
                Action::make('download_pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn(Invoice $record): string => route('filament.admin.invoices.download-pdf', $record))
                    ->openUrlInNewTab(),
                Action::make('resend_to_sunat')
                    ->label('Reenviar a SUNAT')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(
                        fn(Invoice $record): bool =>
                        in_array($record->sunat_status, ['RECHAZADO']) && in_array($record->invoice_type, ['invoice', 'receipt'])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Reenviar Comprobante a SUNAT')
                    ->modalDescription(fn(Invoice $record): string =>
                    "¿Está seguro de reenviar el comprobante {$record->series}-{$record->number} a SUNAT?")
                    ->modalSubmitActionLabel('Reenviar')
                    ->action(function (Invoice $record): void {
                        try {
                            // Usar QPS exclusivamente
                            $qpsService = new \App\Services\QpsService();
                            $result = $qpsService->sendInvoiceViaQps($record);

                            if ($result['success']) {
                                Notification::make()->title('Comprobante reenviado vía QPS')->success()->send();
                            } else {
                                Notification::make()->title('Error al reenviar')->body($result['message'])->danger()->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()->title('Error inesperado')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('crear_nota_credito')
                    ->label('Crear Nota de Crédito')
                    ->icon('heroicon-o-document-minus')
                    ->color('danger')
                    ->visible(
                        fn(Invoice $record): bool =>
                        $record->sunat_status === 'ACEPTADO' &&
                            !$record->hasCreditNotes() &&
                            in_array($record->invoice_type, ['invoice', 'receipt'])
                    )
                    ->form([
                        Forms\Components\Select::make('motivo_codigo')
                            ->label('Motivo de la Nota de Crédito')
                            ->options([
                                '01' => '01 - Anulación de la operación',
                                '02' => '02 - Anulación por error en el RUC',
                                '03' => '03 - Corrección por error en la descripción',
                                '04' => '04 - Descuento global',
                                '05' => '05 - Descuento por ítem',
                                '06' => '06 - Devolución total',
                                '07' => '07 - Devolución por ítem',
                                '08' => '08 - Bonificación',
                                '09' => '09 - Disminución en el valor',
                                '10' => '10 - Otros conceptos',
                            ])
                            ->required()
                            ->default('01'),
                        Forms\Components\Textarea::make('motivo_descripcion')
                            ->label('Descripción del Motivo')
                            ->required()
                            ->maxLength(500)
                            ->default('Anulación de la operación')
                            ->rows(3),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        try {
                            $sunatService = new \App\Services\SunatService();

                            $result = $sunatService->emitirNotaCredito(
                                $record,
                                $data['motivo_codigo'],
                                $data['motivo_descripcion']
                            );

                            if ($result['success']) {
                                $creditNote = $result['credit_note'];
                                Notification::make()
                                    ->title('Nota de crédito creada exitosamente')
                                    ->body("Serie: {$creditNote->serie}-{$creditNote->numero}")
                                    ->success()
                                    ->send();
                            } else {
                                throw new \Exception($result['error']);
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al crear la nota de crédito')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalHeading('Crear Nota de Crédito')
                    ->modalDescription('Genere una nota de crédito para esta factura.')
                    ->modalSubmitActionLabel('Crear Nota de Crédito')
                    ->modalWidth('lg'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // No bulk actions for invoices
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->extremePaginationLinks();
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\InvoiceResource\RelationManagers\CreditNotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('tax_authority_status', 'pending')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
