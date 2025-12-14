<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Models\Purchase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = '📦 Inventario y Compras';

    protected static ?string $navigationLabel = 'Compras';

    protected static ?string $modelLabel = 'Compra';

    protected static ?string $pluralModelLabel = 'Compras';

    protected static ?string $slug = 'inventario/compras';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // GRID PRINCIPAL PARA PROVEEDOR Y COMPROBANTE EN LA MISMA FILA
                Forms\Components\Grid::make(2)
                    ->schema([
                        // SECCIÓN 1: DATOS DEL PROVEEDOR
                        Forms\Components\Section::make('🏢 PROVEEDOR')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('supplier_id')
                                            ->label('Proveedor')
                                            ->placeholder('Seleccione...')
                                            ->relationship('supplier', 'business_name')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                if ($state) {
                                                    $supplier = \App\Models\Supplier::find($state);
                                                    if ($supplier) {
                                                        $set('supplier_ruc', $supplier->tax_id);
                                                        $set('supplier_business_name', $supplier->business_name);
                                                        $set('supplier_address', $supplier->address);
                                                    }
                                                } else {
                                                    $set('supplier_ruc', '');
                                                    $set('supplier_business_name', '');
                                                    $set('supplier_address', '');
                                                }
                                            })
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('search_ruc')
                                            ->label('🔢 Búsqueda por RUC')
                                            ->placeholder('Ingrese RUC (11 dígitos)')
                                            ->numeric()
                                            ->maxLength(11)
                                            ->minLength(11)
                                            ->helperText('Ingrese RUC y presione Enter o Tab para buscar')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if (strlen($state) === 11) {
                                                    $ruc = $state;

                                                    try {
                                                        // Buscar usando RucLookupService (servicio correcto para consultar RUC)
                                                        $rucService = app(\App\Services\RucLookupService::class);
                                                        $rucInfo = $rucService->lookupRuc($ruc);

                                                        if ($rucInfo) {
                                                            // Guardar en la base de datos
                                                            $supplier = \App\Models\Supplier::updateOrCreate(
                                                                ['tax_id' => $ruc],
                                                                [
                                                                    'business_name' => $rucInfo['razon_social'],
                                                                    'address' => $rucInfo['direccion'] ?? '',
                                                                    'phone' => $rucInfo['telefono'] ?? '',
                                                                    'email' => $rucInfo['email'] ?? '',
                                                                    'active' => true,
                                                                ]
                                                            );

                                                            // Actualizar el selector y mostrar información
                                                            $set('supplier_id', $supplier->id);
                                                            $set('supplier_ruc', $supplier->tax_id);
                                                            $set('supplier_business_name', $supplier->business_name);
                                                            $set('supplier_address', $supplier->address);

                                                            \Filament\Notifications\Notification::make()
                                                                ->title('✅ Proveedor Encontrado')
                                                                ->body("Se encontró y guardó el proveedor: {$supplier->business_name}")
                                                                ->success()
                                                                ->send();
                                                        } else {
                                                            \Filament\Notifications\Notification::make()
                                                                ->title('❌ Proveedor No Encontrado')
                                                                ->body('No se encontró información para el RUC proporcionado')
                                                                ->warning()
                                                                ->send();
                                                        }
                                                    } catch (\Exception $e) {
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('⚠️ Error en Búsqueda')
                                                            ->body('Ocurrió un error al buscar el proveedor: ' . $e->getMessage())
                                                            ->danger()
                                                            ->send();
                                                    }
                                                }
                                            }),
                                    ]),
                            ])
                            ->columnSpan(1),

                        // SECCIÓN 2: DATOS DEL COMPROBANTE
                        Forms\Components\Section::make('📄 COMPROBANTE')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('document_type')
                                            ->label('Tipo')
                                            ->placeholder('Seleccione...')
                                            ->options([
                                                'invoice' => 'FACTURA',
                                                'receipt' => 'BOLETA',
                                                'ticket' => 'TICKET',
                                                'dispatch_guide' => 'GUÍA',
                                                'other' => 'OTRO',
                                            ])
                                            ->required()
                                            ->default('invoice')
                                            ->reactive()
                                            ->afterStateUpdated(function (callable $set, $state) {
                                                if ($state === 'dispatch_guide') {
                                                    $set('document_number', 'T001-');
                                                }
                                            }),

                                        Forms\Components\TextInput::make('document_number')
                                            ->label('Número')
                                            ->placeholder('Ej: F001-12345')
                                            ->required()
                                            ->maxLength(50),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\DatePicker::make('purchase_date')
                                            ->label('Fecha')
                                            ->placeholder('Seleccione...')
                                            ->required()
                                            ->default(now()),

                                        Forms\Components\Select::make('warehouse_id')
                                            ->label('Almacén')
                                            ->placeholder('Seleccione...')
                                            ->relationship('warehouse', 'name')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->default(function () {
                                                return \App\Models\Warehouse::where('is_default', true)->first()?->id;
                                            }),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->label('Estado')
                                            ->placeholder('Seleccione...')
                                            ->options([
                                                'pending' => 'PENDIENTE',
                                                'completed' => 'COMPLETADO',
                                                'cancelled' => 'ANULADO',
                                            ])
                                            ->required()
                                            ->default('completed'),

                                        Forms\Components\Textarea::make('notes')
                                            ->label('Notas')
                                            ->placeholder('Observaciones...')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                // SECCIÓN DE INFORMACIÓN DEL PROVEEDOR (OCULTA)
                // Forms\Components\Fieldset::make('supplier_info')
                //     ->label('📋 Información del Proveedor')
                //     ->schema([
                //         Forms\Components\Placeholder::make('ruc')
                //             ->label('📋 RUC')
                //             ->content(function ($get) {
                //                 return $get('supplier_ruc') ?? '';
                //             }),
                //         Forms\Components\Placeholder::make('business_name')
                //             ->label('🏢 Razón Social')
                //             ->content(function ($get) {
                //                 return $get('supplier_business_name') ?? '';
                //             }),
                //         Forms\Components\Placeholder::make('address')
                //             ->label('📍 Dirección')
                //             ->content(function ($get) {
                //                 return $get('supplier_address') ?? '';
                //             }),
                //     ])
                //     ->columns(1)
                //     ->visible(function ($get) {
                //         return !empty($get('supplier_ruc'));
                //     })
                //     ->columnSpan(1)
                //     ->extraAttributes([
                //         'class' => '-mt-6 mb-2'
                //     ]),

                // SECCIÓN 3: DETALLE DE PRODUCTOS
                Forms\Components\Section::make('🛒 DETALLE DE PRODUCTOS')
                    ->description('Agregue los productos e ingredientes comprados')
                    ->icon('heroicon-o-shopping-bag')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->label('')
                            ->relationship()
                            ->addActionLabel('➕ AGREGAR PRODUCTO')
                            ->collapsible()
                            ->collapsed()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                $state['product_id']
                                    ? '📦 ' . \App\Models\Product::find($state['product_id'])?->name . ' - ' . ($state['quantity'] ?? '?') . ' x S/' . ($state['unit_cost'] ?? '0')
                                    : null
                            )
                            ->schema([
                                Forms\Components\Grid::make(5)
                                    ->schema([
                                        Forms\Components\Select::make('product_id')
                                            ->label('📦 Producto')
                                            ->placeholder('Buscar...')
                                            ->options(\App\Models\Product::where('active', true)->pluck('name', 'id'))
                                            ->required()
                                            ->searchable()
                                            ->reactive()
                                            ->afterStateUpdated(function (callable $set, $state) {
                                                if ($state) {
                                                    $product = \App\Models\Product::find($state);
                                                    if ($product) {
                                                        $set('unit_cost', $product->current_cost ?? 0);
                                                    }
                                                }
                                            }),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label('📊 Cantidad')
                                            ->placeholder('1.000')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0.001)
                                            ->step(0.001)
                                            ->default(1)
                                            ->formatStateUsing(fn($state) => $state ? number_format((float)$state, 3, '.', '') : '1')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $get, callable $set) {
                                                $quantity = (float) ($state ?? 0);
                                                $unitCost = (float) ($get('unit_cost') ?? 0);
                                                $includeIgv = $get('include_igv') ?? false;
                                                $baseSubtotal = $quantity * $unitCost;

                                                if ($includeIgv) {
                                                    $igvPercent = \App\Models\ElectronicBillingConfig::getIgvPercent();
                                                    $igvFactor = 1 + ($igvPercent / 100);
                                                    $subtotal = $baseSubtotal * $igvFactor;
                                                } else {
                                                    $subtotal = $baseSubtotal;
                                                }

                                                $set('subtotal', round($subtotal, 2));

                                                // Actualizar totales generales
                                                $details = $get('../../details') ?? [];
                                                $totalSubtotal = collect($details)->sum(fn($item) => $item['subtotal'] ?? 0);
                                                $totalTax = 0;

                                                foreach ($details as $detail) {
                                                    if ($detail['include_igv'] ?? false) {
                                                        $quantity = (float) ($detail['quantity'] ?? 0);
                                                        $unitCost = (float) ($detail['unit_cost'] ?? 0);
                                                        $itemBaseSubtotal = $quantity * $unitCost;
                                                        $igvPercent = \App\Models\ElectronicBillingConfig::getIgvPercent();
                                                        $itemTax = $itemBaseSubtotal * ($igvPercent / 100);
                                                        $totalTax += $itemTax;
                                                    }
                                                }

                                                $set('../../subtotal', round($totalSubtotal - $totalTax, 2));
                                                $set('../../tax', round($totalTax, 2));
                                                $set('../../total', round($totalSubtotal, 2));
                                            }),

                                        Forms\Components\TextInput::make('unit_cost')
                                            ->label('💵 Costo Unit.')
                                            ->placeholder('0.00')
                                            ->required()
                                            ->numeric()
                                            ->prefix('S/')
                                            ->default(0)
                                            ->formatStateUsing(fn($state) => $state ? number_format((float)$state, 2, '.', '') : '0.00')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $get, callable $set) {
                                                $quantity = (float) ($get('quantity') ?? 0);
                                                $unitCost = (float) ($state ?? 0);
                                                $includeIgv = $get('include_igv') ?? false;
                                                $baseSubtotal = $quantity * $unitCost;

                                                if ($includeIgv) {
                                                    $igvPercent = \App\Models\ElectronicBillingConfig::getIgvPercent();
                                                    $igvFactor = 1 + ($igvPercent / 100);
                                                    $subtotal = $baseSubtotal * $igvFactor;
                                                } else {
                                                    $subtotal = $baseSubtotal;
                                                }

                                                $set('subtotal', round($subtotal, 2));

                                                // Actualizar totales generales
                                                $details = $get('../../details') ?? [];
                                                $totalSubtotal = collect($details)->sum(fn($item) => $item['subtotal'] ?? 0);
                                                $totalTax = 0;

                                                foreach ($details as $detail) {
                                                    if ($detail['include_igv'] ?? false) {
                                                        $quantity = (float) ($detail['quantity'] ?? 0);
                                                        $unitCost = (float) ($detail['unit_cost'] ?? 0);
                                                        $itemBaseSubtotal = $quantity * $unitCost;
                                                        $igvPercent = \App\Models\ElectronicBillingConfig::getIgvPercent();
                                                        $itemTax = $itemBaseSubtotal * ($igvPercent / 100);
                                                        $totalTax += $itemTax;
                                                    }
                                                }

                                                $set('../../subtotal', round($totalSubtotal - $totalTax, 2));
                                                $set('../../tax', round($totalTax, 2));
                                                $set('../../total', round($totalSubtotal, 2));
                                            }),

                                        Forms\Components\Checkbox::make('include_igv')
                                            ->label('🧾 IGV')
                                            ->helperText(function () {
                                                $igvPercent = \App\Models\ElectronicBillingConfig::getIgvPercent();
                                                return "IGV {$igvPercent}%";
                                            })
                                            ->default(true)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $get, callable $set) {
                                                $quantity = (float) ($get('quantity') ?? 0);
                                                $unitCost = (float) ($get('unit_cost') ?? 0);
                                                $baseSubtotal = $quantity * $unitCost;

                                                if ($state) {
                                                    $igvPercent = \App\Models\ElectronicBillingConfig::getIgvPercent();
                                                    $igvFactor = 1 + ($igvPercent / 100);
                                                    $subtotal = $baseSubtotal * $igvFactor;
                                                } else {
                                                    $subtotal = $baseSubtotal;
                                                }

                                                $set('subtotal', round($subtotal, 2));

                                                // Actualizar totales generales
                                                $details = $get('../../details') ?? [];
                                                $totalSubtotal = collect($details)->sum(fn($item) => $item['subtotal'] ?? 0);
                                                $totalTax = 0;

                                                foreach ($details as $detail) {
                                                    if ($detail['include_igv'] ?? false) {
                                                        $quantity = (float) ($detail['quantity'] ?? 0);
                                                        $unitCost = (float) ($detail['unit_cost'] ?? 0);
                                                        $itemBaseSubtotal = $quantity * $unitCost;
                                                        $igvPercent = \App\Models\ElectronicBillingConfig::getIgvPercent();
                                                        $itemTax = $itemBaseSubtotal * ($igvPercent / 100);
                                                        $totalTax += $itemTax;
                                                    }
                                                }

                                                $set('../../subtotal', round($totalSubtotal - $totalTax, 2));
                                                $set('../../tax', round($totalTax, 2));
                                                $set('../../total', round($totalSubtotal, 2));
                                            }),

                                        Forms\Components\TextInput::make('subtotal')
                                            ->label('💰 Total')
                                            ->placeholder('0.00')
                                            ->numeric()
                                            ->prefix('S/')
                                            ->disabled()
                                            ->dehydrated()
                                            ->formatStateUsing(fn($state) => $state ? number_format((float)$state, 2, '.', '') : '0.00'),
                                    ]),
                            ])
                            ->columns(1)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->collapsible()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                // Actualizar totales generales cuando se modifica el repeater
                                $details = $get('details') ?? [];
                                $totalSubtotal = collect($details)->sum(fn($item) => $item['subtotal'] ?? 0);
                                $totalTax = 0;

                                foreach ($details as $detail) {
                                    if ($detail['include_igv'] ?? false) {
                                        $quantity = (float) ($detail['quantity'] ?? 0);
                                        $unitCost = (float) ($detail['unit_cost'] ?? 0);
                                        $itemBaseSubtotal = $quantity * $unitCost;
                                        $igvPercent = \App\Models\ElectronicBillingConfig::getIgvPercent();
                                        $itemTax = $itemBaseSubtotal * ($igvPercent / 100);
                                        $totalTax += $itemTax;
                                    }
                                }

                                $set('subtotal', round($totalSubtotal - $totalTax, 2));
                                $set('tax', round($totalTax, 2));
                                $set('total', round($totalSubtotal, 2));
                            }),
                    ]),

                // SECCIÓN 4: RESUMEN FINANCIERO
                Forms\Components\Section::make('💰 RESUMEN FINANCIERO')
                    ->description('Totales calculados automáticamente de la compra')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('subtotal')
                                    ->label('📋 SUBTOTAL')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated()
                                    ->reactive()
                                    ->helperText('Monto sin impuestos'),

                                Forms\Components\TextInput::make('tax')
                                    ->label('🧾 IGV')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated()
                                    ->reactive()
                                    ->helperText('Impuesto General a las Ventas'),

                                Forms\Components\TextInput::make('total')
                                    ->label('💰 TOTAL A PAGAR')
                                    ->numeric()
                                    ->prefix('S/')
                                    ->default(0)
                                    ->disabled()
                                    ->dehydrated()
                                    ->reactive()
                                    ->helperText('Monto total incluyendo impuestos'),
                            ]),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('CÓDIGO')
                    ->sortable()
                    ->alignCenter()
                    ->size('xs')
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('FECHA COMPRA')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight('semibold')
                    ->color('gray-700'),

                Tables\Columns\TextColumn::make('supplier.business_name')
                    ->label('PROVEEDOR')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->color('gray-800')
                    ->copyable()
                    ->copyMessage('Proveedor copiado al portapapeles')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('ALMACÉN')
                    ->searchable()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('TIPO DOCUMENTO')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'invoice' => 'FACTURA',
                        'receipt' => 'BOLETA',
                        'ticket' => 'TICKET',
                        'dispatch_guide' => 'GUÍA REMISIÓN',
                        'other' => 'OTRO',
                        default => $state,
                    })
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'invoice' => 'primary',
                        'receipt' => 'success',
                        'ticket' => 'warning',
                        'dispatch_guide' => 'info',
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('N° DOCUMENTO')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->color('gray-700')
                    ->copyable()
                    ->copyMessage('Número de documento copiado')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('total')
                    ->label('MONTO TOTAL')
                    ->money('PEN')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn($record): string => $record->status === 'completed' ? 'success' : 'gray-600')
                    ->size('lg'),

                Tables\Columns\TextColumn::make('status')
                    ->label('ESTADO COMPRA')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'PENDIENTE',
                        'completed' => 'COMPLETADO',
                        'cancelled' => 'ANULADO',
                        default => $state,
                    })
                    ->badge()
                    ->icon(fn(string $state): string => match ($state) {
                        'pending' => 'heroicon-o-clock',
                        'completed' => 'heroicon-o-check-circle',
                        'cancelled' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('FECHA REGISTRO')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter()
                    ->color('gray-600'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('ESTADO COMPRA')
                    ->options([
                        'pending' => '⏳ PENDIENTE',
                        'completed' => '✅ COMPLETADO',
                        'cancelled' => '❌ ANULADO',
                    ]),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('PROVEEDOR')
                    ->relationship('supplier', 'business_name')
                    ->searchable()
                    ->preload()
                    ->placeholder('TODOS LOS PROVEEDORES'),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('ALMACÉN')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('TODOS LOS ALMACENES'),

                Tables\Filters\Filter::make('purchase_date')
                    ->label('PERÍODO DE BÚSQUEDA')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')
                            ->label('FECHA INICIAL')
                            ->placeholder('Seleccionar fecha inicial')
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('to_date')
                            ->label('FECHA FINAL')
                            ->placeholder('Seleccionar fecha final')
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('purchase_date', '>=', $date),
                            )
                            ->when(
                                $data['to_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('purchase_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from_date'] ?? null) {
                            $indicators[] = 'DESDE: ' . $data['from_date'];
                        }
                        if ($data['to_date'] ?? null) {
                            $indicators[] = 'HASTA: ' . $data['to_date'];
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('MODIFICAR')
                    ->icon('heroicon-o-pencil')
                    ->color('primary')
                    ->size('sm')
                    ->tooltip('Modificar datos de la compra'),

                Tables\Actions\Action::make('view_details')
                    ->label('DETALLES')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->size('sm')
                    ->tooltip('Ver detalles completos de la compra')
                    ->action(function ($record) {
                        // Redirigir a página de detalles o mostrar modal
                        return redirect()->to(route('filament.admin.resources.purchases.edit', $record));
                    })
                    ->visible(fn($record): bool => $record->details()->count() > 0),

                Tables\Actions\Action::make('duplicate_purchase')
                    ->label('DUPLICAR')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->size('sm')
                    ->tooltip('Duplicar esta compra')
                    ->action(function ($record) {
                        // Lógica para duplicar compra
                        $newPurchase = $record->replicate();
                        $newPurchase->document_number = $record->document_number . ' (COPIA)';
                        $newPurchase->status = 'pending';
                        $newPurchase->save();

                        // Duplicar detalles
                        foreach ($record->details as $detail) {
                            $newDetail = $detail->replicate();
                            $newDetail->purchase_id = $newPurchase->id;
                            $newDetail->save();
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('COMPRA DUPLICADA')
                            ->body('La compra ha sido duplicada exitosamente')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('¿DUPLICAR ESTA COMPRA?')
                    ->modalDescription('Se creará una copia exacta de esta compra con estado "PENDIENTE".')
                    ->modalSubmitActionLabel('SÍ, DUPLICAR')
                    ->modalCancelActionLabel('CANCELAR'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('ELIMINAR SELECCIONADAS')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('¿ELIMINAR COMPRAS SELECCIONADAS?')
                        ->modalDescription('Esta acción no se puede deshacer. ¿Está seguro de continuar?')
                        ->modalSubmitActionLabel('SÍ, ELIMINAR')
                        ->modalCancelActionLabel('CANCELAR'),

                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('MARCAR COMPLETADAS')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if ($record->status === 'pending') {
                                    $record->update(['status' => 'completed']);
                                }
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('COMPRAS ACTUALIZADAS')
                                ->body(count($records) . ' compras marcadas como COMPLETADAS')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('¿MARCAR COMPRAS COMO COMPLETADAS?')
                        ->modalDescription('Se actualizará el estado de las compras seleccionadas a COMPLETADO.')
                        ->modalSubmitActionLabel('SÍ, COMPLETAR')
                        ->modalCancelActionLabel('CANCELAR')
                        ->visible(fn(): bool => auth()->user()->can('update_purchase')),

                    /*
                    Tables\Actions\BulkAction::make('export_selected')
                        ->label('EXPORTAR SELECCIÓN')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->action(function ($records) {
                            // Lógica de exportación
                            return \Filament\Actions\ExportAction::make('export_purchases')
                                ->label('Exportar compras')
                                ->exporter(\App\Exports\PurchasesExport::class)
                                ->fileName('compras-' . now()->format('Y-m-d') . '.xlsx');
                        })
                        ->deselectRecordsAfterCompletion(),
                    */
                ]),
            ])
            ->emptyStateHeading('NO HAY COMPRAS REGISTRADAS')
            ->emptyStateDescription('Comience registrando su primera compra para gestionar el inventario de ingredientes y productos del restaurante.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('REGISTRAR NUEVA COMPRA')
                    ->icon('heroicon-o-plus-circle')
                    ->size('lg')
                    ->color('primary'),
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
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }
}
