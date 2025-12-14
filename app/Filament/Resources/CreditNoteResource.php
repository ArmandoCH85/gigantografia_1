<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditNoteResource\Pages;
use App\Models\CreditNote;
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
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;

class CreditNoteResource extends Resource
{
    protected static ?string $model = CreditNote::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?string $navigationGroup = 'Facturación y Ventas';

    protected static ?string $navigationLabel = 'Notas de Crédito';

    protected static ?string $modelLabel = 'Nota de Crédito';

    protected static ?string $pluralModelLabel = 'Notas de Crédito';

    protected static ?string $slug = 'facturacion/notas-credito';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Nota de Crédito')
                    ->schema([
                        Forms\Components\Select::make('invoice_id')
                            ->label('Factura Relacionada')
                            ->relationship('invoice', 'series')
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->series}-{$record->number} ({$record->customer->business_name})")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(fn($context) => $context === 'edit'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('serie')
                                    ->label('Serie')
                                    ->required()
                                    ->maxLength(4)
                                    ->disabled(),

                                Forms\Components\TextInput::make('numero')
                                    ->label('Número')
                                    ->required()
                                    ->numeric()
                                    ->disabled(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('motivo_codigo')
                                    ->label('Código de Motivo')
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
                                    ->disabled(fn($context) => $context === 'edit'),

                                Forms\Components\Select::make('sunat_status')
                                    ->label('Estado SUNAT')
                                    ->options([
                                        'PENDIENTE' => 'Pendiente',
                                        'ACEPTADO' => 'Aceptado',
                                        'RECHAZADO' => 'Rechazado',
                                        'ERROR' => 'Error',
                                    ])
                                    ->required()
                                    ->disabled(),
                            ]),

                        Forms\Components\Textarea::make('motivo_descripcion')
                            ->label('Descripción del Motivo')
                            ->required()
                            ->maxLength(500)
                            ->disabled(fn($context) => $context === 'edit'),

                        Forms\Components\DateTimePicker::make('fecha_emision')
                            ->label('Fecha de Emisión')
                            ->required()
                            ->disabled(),
                    ])->columns(1),

                Forms\Components\Section::make('Importes')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('monto_operaciones_gravadas')
                                    ->label('Base Imponible')
                                    ->prefix('S/')
                                    ->numeric()
                                    ->disabled(),

                                Forms\Components\TextInput::make('monto_igv')
                                    ->label('IGV')
                                    ->prefix('S/')
                                    ->numeric()
                                    ->disabled(),

                                Forms\Components\TextInput::make('monto_total')
                                    ->label('Total')
                                    ->prefix('S/')
                                    ->numeric()
                                    ->disabled(),
                            ]),
                    ]),

                Forms\Components\Section::make('Archivos SUNAT')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('xml_path')
                                    ->label('Archivo XML')
                                    ->disabled(),

                                Forms\Components\TextInput::make('cdr_path')
                                    ->label('Archivo CDR')
                                    ->disabled(),
                            ]),

                        Forms\Components\Textarea::make('sunat_response')
                            ->label('Respuesta SUNAT')
                            ->disabled()
                            ->rows(3),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('serie')
                    ->label('Serie')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('numero')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.series')
                    ->label('Factura')
                    ->formatStateUsing(fn($record) => "{$record->invoice->series}-{$record->invoice->number}")
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice.customer.business_name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('motivo_codigo')
                    ->label('Motivo')
                    ->formatStateUsing(fn($state) => match ($state) {
                        '01' => '01 - Anulación',
                        '02' => '02 - Error RUC',
                        '03' => '03 - Error descripción',
                        '04' => '04 - Descuento global',
                        '05' => '05 - Descuento ítem',
                        '06' => '06 - Devolución total',
                        '07' => '07 - Devolución ítem',
                        '08' => '08 - Bonificación',
                        '09' => '09 - Disminución valor',
                        '10' => '10 - Otros',
                        default => $state,
                    })
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('monto_total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sunat_status')
                    ->label('Estado SUNAT')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'PENDIENTE' => 'warning',
                        'ACEPTADO' => 'success',
                        'RECHAZADO' => 'danger',
                        'ERROR' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('fecha_emision')
                    ->label('Fecha Emisión')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sunat_status')
                    ->label('Estado SUNAT')
                    ->options([
                        'PENDIENTE' => 'Pendiente',
                        'ACEPTADO' => 'Aceptado',
                        'RECHAZADO' => 'Rechazado',
                        'ERROR' => 'Error',
                    ]),

                Tables\Filters\SelectFilter::make('motivo_codigo')
                    ->label('Motivo')
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
                    ]),

                Tables\Filters\Filter::make('fecha_emision')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'],
                                fn(Builder $query, $date): Builder => $query->whereDate('fecha_emision', '>=', $date),
                            )
                            ->when(
                                $data['hasta'],
                                fn(Builder $query, $date): Builder => $query->whereDate('fecha_emision', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->sunat_status === 'PENDIENTE'),

                Action::make('reenviar_sunat')
                    ->label('Reenviar a SUNAT')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn($record) => in_array($record->sunat_status, ['RECHAZADO', 'ERROR', 'PENDIENTE']))
                    ->requiresConfirmation()
                    ->modalHeading('Reenviar Nota de Crédito a SUNAT')
                    ->modalDescription('¿Está seguro de que desea reenviar esta nota de crédito a SUNAT?')
                    ->action(function ($record) {
                        try {
                            $sunatService = new SunatService();
                            $result = $sunatService->emitirNotaCredito($record->invoice, $record->motivo_codigo, $record->motivo_descripcion);

                            Notification::make()
                                ->title('Nota de crédito reenviada exitosamente')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al reenviar nota de crédito')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('descargar_xml')
                    ->label('Descargar XML')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(fn($record) => !empty($record->xml_path) && Storage::exists($record->xml_path))
                    ->action(function ($record) {
                        return response()->download(storage_path('app/' . $record->xml_path));
                    }),

                Action::make('descargar_cdr')
                    ->label('Descargar CDR')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->visible(fn($record) => !empty($record->cdr_path) && Storage::exists($record->cdr_path))
                    ->action(function ($record) {
                        return response()->download(storage_path('app/' . $record->cdr_path));
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->can('delete_credit_note')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListCreditNotes::route('/'),
            'create' => Pages\CreateCreditNote::route('/create'),
            'view' => Pages\ViewCreditNote::route('/{record}'),
            'edit' => Pages\EditCreditNote::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('sunat_status', 'PENDIENTE')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_credit::note');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can('update_credit::note') && $record->sunat_status === 'PENDIENTE';
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can('delete_credit::note') && $record->sunat_status === 'PENDIENTE';
    }

    public static function canView($record): bool
    {
        return auth()->user()->can('view_credit::note');
    }
}
