<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SummaryResource\Pages;
use App\Filament\Resources\SummaryResource\RelationManagers;
use App\Models\Summary;
use App\Models\Invoice;
use App\Services\SunatService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Carbon\Carbon;
use Exception;

class SummaryResource extends Resource
{
    protected static ?string $model = Summary::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Resúmenes de Boletas';

    protected static ?string $modelLabel = 'Resumen de Boletas';

    protected static ?string $pluralModelLabel = 'Resúmenes de Boletas';

    protected static ?string $navigationGroup = 'Facturación y Ventas';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del Resumen')
                    ->schema([
                        Forms\Components\TextInput::make('correlativo')
                            ->label('Correlativo')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Se genera automáticamente'),

                        Forms\Components\DatePicker::make('fecha_referencia')
                            ->label('Fecha de Referencia')
                            ->required()
                            ->default(now()->subDay())
                            ->helperText('Fecha de las boletas a incluir en el resumen')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (!$state) return;

                                // Verificar si ya existe un resumen para esta fecha
                                $existingSummary = Summary::where('fecha_referencia', $state)->first();

                                // Contar boletas disponibles
                                $boletasCount = Invoice::where('invoice_type', 'receipt')
                                    ->whereDate('issue_date', $state)
                                    ->whereIn('sunat_status', ['ACEPTADO', 'PENDIENTE'])
                                    ->count();

                                $totalAmount = Invoice::where('invoice_type', 'receipt')
                                    ->whereDate('issue_date', $state)
                                    ->whereIn('sunat_status', ['ACEPTADO', 'PENDIENTE'])
                                    ->sum('total');

                                // Actualizar campos informativos
                                $set('receipts_count', $boletasCount);
                                $set('total_amount', $totalAmount);

                                // Mostrar advertencia si ya existe resumen
                                if ($existingSummary) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('⚠️ Resumen Existente')
                                        ->body("Ya existe un resumen para esta fecha (ID: {$existingSummary->id}, Correlativo: {$existingSummary->correlativo})")
                                        ->warning()
                                        ->persistent()
                                        ->send();
                                }

                                // Mostrar información sobre boletas
                                if ($boletasCount === 0) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('❌ Sin Boletas')
                                        ->body("No hay boletas (aceptadas o pendientes) para la fecha {$state}")
                                        ->danger()
                                        ->persistent()
                                        ->send();
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('✅ Boletas Encontradas')
                                        ->body("{$boletasCount} boletas por S/ " . number_format($totalAmount, 2))
                                        ->success()
                                        ->send();
                                }
                            }),

                        Forms\Components\DatePicker::make('fecha_generacion')
                            ->label('Fecha de Generación')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(now())
                            ->helperText('Se establece automáticamente al generar'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Estado SUNAT')
                    ->schema([
                        Forms\Components\TextInput::make('ticket')
                            ->label('Ticket SUNAT')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Se obtiene tras el envío'),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                Summary::STATUS_PENDING => 'Pendiente',
                                Summary::STATUS_PROCESSING => 'Procesando',
                                Summary::STATUS_SENT => 'Enviado',
                                Summary::STATUS_ACCEPTED => 'Aceptado',
                                Summary::STATUS_REJECTED => 'Rechazado',
                                Summary::STATUS_ERROR => 'Error',
                            ])
                            ->disabled()
                            ->dehydrated(false)
                            ->default(Summary::STATUS_PENDING),

                        Forms\Components\TextInput::make('sunat_code')
                            ->label('Código SUNAT')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(3)
                    ->visible(fn($record) => $record !== null),

                Forms\Components\Section::make('Detalles del Resumen')
                    ->schema([
                        Forms\Components\TextInput::make('receipts_count')
                            ->label('Cantidad de Boletas')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric(),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Monto Total')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric()
                            ->prefix('S/'),

                        Forms\Components\TextInput::make('processing_time_ms')
                            ->label('Tiempo de Procesamiento (ms)')
                            ->disabled()
                            ->dehydrated(false)
                            ->numeric(),
                    ])
                    ->columns(3)
                    ->visible(fn($record) => $record !== null),

                Forms\Components\Section::make('Archivos')
                    ->schema([
                        Forms\Components\TextInput::make('xml_path')
                            ->label('Archivo XML')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('download_xml')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->url(fn($record) => $record?->xml_path ? route('download.xml', $record) : null)
                                    ->openUrlInNewTab()
                                    ->visible(fn($record) => $record?->xml_path !== null)
                            ),

                        Forms\Components\TextInput::make('cdr_path')
                            ->label('CDR SUNAT')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('download_cdr')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->url(fn($record) => $record?->cdr_path ? route('download.cdr', $record) : null)
                                    ->openUrlInNewTab()
                                    ->visible(fn($record) => $record?->cdr_path !== null)
                            ),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record !== null),

                Forms\Components\Section::make('Información Adicional')
                    ->schema([
                        Forms\Components\Textarea::make('sunat_description')
                            ->label('Descripción SUNAT')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(2),

                        Forms\Components\Textarea::make('error_message')
                            ->label('Mensaje de Error')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(3)
                            ->visible(fn($record) => $record?->error_message !== null),
                    ])
                    ->visible(fn($record) => $record !== null && ($record->sunat_description || $record->error_message)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('correlativo')
                    ->label('Correlativo')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('fecha_referencia')
                    ->label('Fecha Referencia')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        Summary::STATUS_PENDING => 'Pendiente',
                        Summary::STATUS_PROCESSING => 'Procesando',
                        Summary::STATUS_SENT => 'Enviado',
                        Summary::STATUS_ACCEPTED => 'Aceptado',
                        Summary::STATUS_REJECTED => 'Rechazado',
                        Summary::STATUS_ERROR => 'Error',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        Summary::STATUS_PENDING => 'gray',
                        Summary::STATUS_PROCESSING => 'warning',
                        Summary::STATUS_SENT => 'info',
                        Summary::STATUS_ACCEPTED => 'success',
                        Summary::STATUS_REJECTED => 'danger',
                        Summary::STATUS_ERROR => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        Summary::STATUS_PENDING => 'heroicon-o-clock',
                        Summary::STATUS_PROCESSING => 'heroicon-o-arrow-path',
                        Summary::STATUS_SENT => 'heroicon-o-paper-airplane',
                        Summary::STATUS_ACCEPTED => 'heroicon-o-check-circle',
                        Summary::STATUS_REJECTED => 'heroicon-o-x-circle',
                        Summary::STATUS_ERROR => 'heroicon-o-exclamation-triangle',
                        default => 'heroicon-o-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('ticket')
                    ->label('Ticket SUNAT')
                    ->searchable()
                    ->copyable()
                    ->placeholder('Sin ticket')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('receipts_count')
                    ->label('Boletas')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Monto Total')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('sunat_code')
                    ->label('Código SUNAT')
                    ->searchable()
                    ->placeholder('Sin código')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('xml_path')
                    ->label('XML')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-check')
                    ->falseIcon('heroicon-o-document-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('cdr_path')
                    ->label('CDR')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('fecha_generacion')
                    ->label('Fecha Generación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('processing_time_ms')
                    ->label('Tiempo (ms)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        Summary::STATUS_PENDING => 'Pendiente',
                        Summary::STATUS_PROCESSING => 'Procesando',
                        Summary::STATUS_SENT => 'Enviado',
                        Summary::STATUS_ACCEPTED => 'Aceptado',
                        Summary::STATUS_REJECTED => 'Rechazado',
                        Summary::STATUS_ERROR => 'Error',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('fecha_referencia')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['fecha_desde'],
                                fn(Builder $query, $date): Builder => $query->whereDate('fecha_referencia', '>=', $date),
                            )
                            ->when(
                                $data['fecha_hasta'],
                                fn(Builder $query, $date): Builder => $query->whereDate('fecha_referencia', '<=', $date),
                            );
                    }),

                Tables\Filters\TernaryFilter::make('has_xml')
                    ->label('Con XML')
                    ->placeholder('Todos')
                    ->trueLabel('Con XML')
                    ->falseLabel('Sin XML')
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('xml_path'),
                        false: fn(Builder $query) => $query->whereNull('xml_path'),
                    ),

                Tables\Filters\TernaryFilter::make('has_cdr')
                    ->label('Con CDR')
                    ->placeholder('Todos')
                    ->trueLabel('Con CDR')
                    ->falseLabel('Sin CDR')
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('cdr_path'),
                        false: fn(Builder $query) => $query->whereNull('cdr_path'),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('generate')
                    ->label('Generar')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('warning')
                    ->visible(fn(Summary $record): bool => $record->status === Summary::STATUS_PENDING)
                    ->action(function (Summary $record) {
                        try {
                            $sunatService = app(SunatService::class);
                            // Obtener boletas para el resumen
                            $boletas = Invoice::where('issue_date', $record->fecha_referencia)
                                ->where('sunat_status', 'ACEPTADO')
                                ->where('invoice_type', 'receipt')
                                ->get()
                                ->map(function ($invoice) {
                                    return [
                                        'series' => $invoice->series,
                                        'number' => $invoice->number,
                                        'invoice_type' => $invoice->invoice_type,
                                        'total' => $invoice->total,
                                        'subtotal' => $invoice->subtotal,
                                        'igv' => $invoice->igv,
                                        'customer_document_type' => $invoice->customer_document_type,
                                        'customer_document_number' => $invoice->customer_document_number,
                                        'estado' => '1' // 1=Adicionar
                                    ];
                                })
                                ->toArray();

                            $result = $sunatService->enviarResumenBoletas(
                                $boletas,
                                now()->format('Y-m-d'),
                                $record->fecha_referencia
                            );

                            if ($result['success']) {
                                // Calcular valores localmente desde las boletas
                                $receiptsCount = count($boletas);
                                $totalAmount = collect($boletas)->sum('total');

                                $record->update([
                                    'correlativo' => $result['correlativo'],
                                    'receipts_count' => $receiptsCount,
                                    'total_amount' => $totalAmount,
                                    'receipts_data' => json_encode($boletas),
                                    'xml_path' => $result['xml_path'],
                                    'status' => Summary::STATUS_PROCESSING,
                                    'fecha_generacion' => now(),
                                ]);

                                Notification::make()
                                    ->title('Resumen generado exitosamente')
                                    ->success()
                                    ->send();
                            } else {
                                $record->update([
                                    'status' => Summary::STATUS_ERROR,
                                    'error_message' => $result['message'],
                                ]);

                                Notification::make()
                                    ->title('Error al generar resumen')
                                    ->body($result['message'])
                                    ->danger()
                                    ->send();
                            }
                        } catch (Exception $e) {
                            $record->update([
                                'status' => Summary::STATUS_ERROR,
                                'error_message' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Error inesperado')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Generar Resumen')
                    ->modalDescription('¿Está seguro de generar el resumen para esta fecha?'),

                Tables\Actions\Action::make('send')
                    ->label('Enviar')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn(Summary $record): bool => $record->status === Summary::STATUS_PROCESSING && $record->xml_path)
                    ->action(function (Summary $record) {
                        try {
                            $sunatService = app(SunatService::class);
                            // Obtener boletas para el resumen
                            $boletas = Invoice::where('issue_date', $record->fecha_referencia)
                                ->where('sunat_status', 'ACEPTADO')
                                ->where('invoice_type', 'receipt')
                                ->get()
                                ->map(function ($invoice) {
                                    return [
                                        'series' => $invoice->series,
                                        'number' => $invoice->number,
                                        'invoice_type' => $invoice->invoice_type,
                                        'total' => $invoice->total,
                                        'subtotal' => $invoice->subtotal,
                                        'igv' => $invoice->igv,
                                        'customer_document_type' => $invoice->customer_document_type,
                                        'customer_document_number' => $invoice->customer_document_number,
                                        'estado' => '1' // 1=Adicionar
                                    ];
                                })
                                ->toArray();

                            $result = $sunatService->enviarResumenBoletas(
                                $boletas,
                                now()->format('Y-m-d'),
                                $record->fecha_referencia
                            );

                            if ($result['success']) {
                                $record->update([
                                    'ticket' => $result['ticket'],
                                    'status' => Summary::STATUS_SENT,
                                ]);

                                Notification::make()
                                    ->title('Resumen enviado a SUNAT')
                                    ->body('Ticket: ' . $result['ticket'])
                                    ->success()
                                    ->send();
                            } else {
                                $record->update([
                                    'status' => Summary::STATUS_ERROR,
                                    'error_message' => $result['message'],
                                ]);

                                Notification::make()
                                    ->title('Error al enviar resumen')
                                    ->body($result['message'])
                                    ->danger()
                                    ->send();
                            }
                        } catch (Exception $e) {
                            $record->update([
                                'status' => Summary::STATUS_ERROR,
                                'error_message' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Error inesperado')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Enviar a SUNAT')
                    ->modalDescription('¿Está seguro de enviar este resumen a SUNAT?'),

                Tables\Actions\Action::make('check_status')
                    ->label('Consultar Estado')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('gray')
                    ->visible(fn(Summary $record): bool => $record->status === Summary::STATUS_SENT && $record->ticket)
                    ->action(function (Summary $record) {
                        try {
                            $sunatService = app(SunatService::class);
                            $result = $sunatService->consultarEstadoResumen($record->ticket);

                            if ($result['success']) {
                                // Mapear estado SUNAT al estado del modelo
                                $status = match ($result['codigo']) {
                                    '0' => Summary::STATUS_ACCEPTED,
                                    '98' => Summary::STATUS_PROCESSING,
                                    '99' => Summary::STATUS_REJECTED,
                                    default => Summary::STATUS_ERROR
                                };

                                $record->update([
                                    'status' => $status,
                                    'sunat_code' => $result['codigo'],
                                    'sunat_description' => $result['descripcion'],
                                    'cdr_path' => $result['cdr_path'] ?? null,
                                ]);

                                $statusText = match ($status) {
                                    Summary::STATUS_ACCEPTED => 'Aceptado por SUNAT',
                                    Summary::STATUS_REJECTED => 'Rechazado por SUNAT',
                                    Summary::STATUS_PROCESSING => 'En proceso',
                                    default => 'Estado actualizado'
                                };

                                $notificationColor = match ($status) {
                                    Summary::STATUS_ACCEPTED => 'success',
                                    Summary::STATUS_REJECTED => 'danger',
                                    Summary::STATUS_PROCESSING => 'warning',
                                    default => 'info'
                                };

                                $bodyMessage = $result['descripcion'] ?? $result['message'] ?? '';
                                if (isset($result['cdr_path']) && $result['cdr_path']) {
                                    $bodyMessage .= ' (CDR descargado)';
                                }

                                Notification::make()
                                    ->title($statusText)
                                    ->body($bodyMessage)
                                    ->color($notificationColor)
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Error al consultar estado')
                                    ->body($result['message'])
                                    ->danger()
                                    ->send();
                            }
                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Error inesperado')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\ViewAction::make()
                    ->label('Ver'),

                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSummaries::route('/'),
            'create' => Pages\CreateSummary::route('/create'),
            'edit' => Pages\EditSummary::route('/{record}/edit'),
        ];
    }
}
