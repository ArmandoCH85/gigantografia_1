<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuotationResource\Pages;
use App\Models\Quotation;
use App\Models\Customer;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use App\Models\ProductMaterial;
use App\Models\ProductFinish;
use App\Services\PriceCalculatorService;
use Illuminate\Support\Str;
use App\Models\CustomerPriceTier;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Cotizaciones';

    protected static ?string $modelLabel = 'Cotización';

    protected static ?string $pluralModelLabel = 'Cotizaciones';

    protected static ?string $slug = 'ventas/cotizaciones';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'quotation_number';

    protected static int $globalSearchResultsLimit = 10;

    // Protección de acceso eliminada temporalmente para restaurar visibilidad


    public static function getGloballySearchableAttributes(): array
    {
        return ['quotation_number', 'customer.name', 'customer.document_number'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Cliente' => $record->customer->name,
            'Estado' => match ($record->status) {
                'draft' => 'Borrador',
                'sent' => 'Enviada',
                'approved' => 'Aprobada',
                'rejected' => 'Rechazada',
                'expired' => 'Vencida',
                'converted' => 'Convertida',
                default => $record->status,
            },
            'Total' => 'S/ ' . number_format($record->total, 2),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'draft')->orWhere('status', 'sent')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'sent')->count() > 0 ? 'warning' : 'primary';
    }

    public static function form(Form $form): Form
    {

        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Información General')
                            ->description('Información básica de la cotización')
                            ->icon('heroicon-o-document-text')
                            ->collapsible()
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('quotation_number')
                                            ->label('Número de Cotización')
                                            ->default(fn() => Quotation::generateQuotationNumber())
                                            ->disabled()
                                            ->required(),

                                        Forms\Components\DatePicker::make('issue_date')
                                            ->label('Fecha de Emisión')
                                            ->default(now())
                                            ->required(),

                                        Forms\Components\DatePicker::make('valid_until')
                                            ->label('Válido Hasta')
                                            ->default(now()->addDays(15))
                                            ->required(),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('status')
                                            ->label('Estado')
                                            ->options([
                                                Quotation::STATUS_DRAFT => 'Borrador',
                                                Quotation::STATUS_SENT => 'Enviada',
                                                Quotation::STATUS_APPROVED => 'Aprobada',
                                                Quotation::STATUS_REJECTED => 'Rechazada',
                                                Quotation::STATUS_EXPIRED => 'Vencida',
                                                Quotation::STATUS_CONVERTED => 'Convertida',
                                            ])
                                            ->default(Quotation::STATUS_DRAFT)
                                            ->disabled(fn(string $operation): bool => $operation === 'create')
                                            ->required(),

                                        Forms\Components\Select::make('payment_terms')
                                            ->label('Términos de Pago')
                                            ->options([
                                                Quotation::PAYMENT_TERMS_CASH => 'Contado',
                                                Quotation::PAYMENT_TERMS_CREDIT_15 => 'Crédito 15 días',
                                                Quotation::PAYMENT_TERMS_CREDIT_30 => 'Crédito 30 días',
                                                Quotation::PAYMENT_TERMS_CREDIT_60 => 'Crédito 60 días',
                                            ])
                                            ->default(Quotation::PAYMENT_TERMS_CASH)
                                            ->required(),
                                    ]),

                                Forms\Components\Hidden::make('user_id')
                                    ->default(fn() => Auth::id()),
                            ]),

                        Forms\Components\Section::make('Cliente')
                            ->description('Seleccione o cree un cliente para la cotización')
                            ->icon('heroicon-o-user')
                            ->collapsible()
                            ->schema([
                                Forms\Components\Select::make('customer_id')
                                    ->label('Cliente')
                                    ->relationship('customer', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nombre')
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('document_type')
                                                    ->label('Tipo de Documento')
                                                    ->options([
                                                        'DNI' => 'DNI',
                                                        'RUC' => 'RUC',
                                                        'CE' => 'Carnet de Extranjería',
                                                        'Pasaporte' => 'Pasaporte',
                                                    ])
                                                    ->required(),

                                                Forms\Components\TextInput::make('document_number')
                                                    ->label('Número de Documento')
                                                    ->required()
                                                    ->maxLength(20),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('phone')
                                                    ->label('Teléfono')
                                                    ->tel()
                                                    ->maxLength(20),

                                                Forms\Components\TextInput::make('email')
                                                    ->label('Correo Electrónico')
                                                    ->email()
                                                    ->maxLength(255),
                                            ]),

                                        Forms\Components\TextInput::make('address')
                                            ->label('Dirección')
                                            ->maxLength(255),
                                    ])
                                    ->required(),
                            ]),

                        Forms\Components\Section::make('Productos')
                            ->description('Agregue los productos a la cotización')
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Forms\Components\Repeater::make('details')
                                    ->label('Detalle de Productos')
                                    ->relationship()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        static::recalculateTotals($set, $get);
                                    })
                                    ->schema([
                                        Forms\Components\Select::make('product_id')
                                            ->label('Producto Base')
                                            ->options(function () {
                                                return \App\Models\Product::query()
                                                    ->whereHas('category', fn($q) => $q->whereIn('name', ['Baner', 'Vinil']))
                                                    ->where('active', true)
                                                    ->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set) {
                                                if ($state) {
                                                    $product = \App\Models\Product::with('category')->find($state);
                                                    $set('_product_category', $product?->category?->name);
                                                    $set('width', null);
                                                    $set('height', null);
                                                    $set('material_id', null);
                                                    $set('finishes', []);
                                                }
                                            }),

                                        Forms\Components\Hidden::make('_product_category'),

                                        Forms\Components\Placeholder::make('_categoria_display')
                                            ->label('📦 Categoría')
                                            ->content(fn($get) => $get('_product_category') ?? '—')
                                            ->visible(fn($get) => $get('product_id') !== null)
                                            ->extraAttributes(['class' => 'text-lg font-semibold text-primary-600']),

                                        // Configuración Personalizada (Banner/Vinil)
                                        Forms\Components\Section::make('Configuración Personalizada')
                                            ->schema([
                                                Forms\Components\Grid::make(2)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('width')
                                                            ->label('Ancho (metros)')
                                                            ->numeric()
                                                            ->inputMode('decimal')
                                                            ->minValue(0.1)
                                                            ->step(0.01)
                                                            ->suffix('m')
                                                            ->required(fn($get) => !\Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower(\App\Models\ProductMaterial::find($get('material_id'))?->name ?? ''), 'lona trasluc'))
                                                            ->live()
                                                            ->afterStateUpdated(fn($state, $set, $get) => self::calculateCustomPrice($set, $get)),

                                                        Forms\Components\TextInput::make('height')
                                                            ->label('Alto (metros)')
                                                            ->numeric()
                                                            ->inputMode('decimal')
                                                            ->minValue(0.1)
                                                            ->step(0.01)
                                                            ->suffix('m')
                                                            ->required()
                                                            ->live()
                                                            ->afterStateUpdated(fn($state, $set, $get) => self::calculateCustomPrice($set, $get)),
                                                    ]),

                                                Forms\Components\Select::make('material_id')
                                                    ->label('Material')
                                                    ->options(function ($get) {
                                                        $productId = $get('product_id');
                                                        if (!$productId) return [];

                                                        $product = \App\Models\Product::with('category')->find($productId);
                                                        if (!$product) return [];

                                                        return \App\Models\ProductMaterial::where('category_id', $product->category_id)
                                                            ->where('active', true)
                                                            ->pluck('name', 'id');
                                                    })
                                                    ->searchable()
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn($state, $set, $get) => self::calculateCustomPrice($set, $get)),

                                                Forms\Components\Repeater::make('finishes')
                                                    ->label('Acabados')
                                                    ->visible(fn($get) => str_contains(strtoupper($get('_product_category') ?? ''), 'BANER'))
                                                    ->schema([
                                                        Forms\Components\Select::make('id')
                                                            ->label('Acabado')
                                                            ->options(\App\Models\ProductFinish::where('active', true)->pluck('name', 'id'))
                                                            ->required()
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                                $finish = \App\Models\ProductFinish::find($state);
                                                                $set('_requires_quantity', $finish?->requires_quantity ?? false);
                                                                self::calculateCustomPrice($set, $get);
                                                            }),

                                                         Forms\Components\TextInput::make('quantity')
                                                             ->label('Cantidad')
                                                             ->numeric()
                                                             ->minValue(1)
                                                             ->default(1)
                                                             ->visible(fn($get) => ($get('_requires_quantity') ?? false) && !\Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower(\App\Models\ProductFinish::find($get('id'))?->name ?? ''), 'tubo'))
                                                             ->live()
                                                             ->afterStateUpdated(fn($state, $set, $get) => self::calculateCustomPrice($set, $get)),

                                                         Forms\Components\TextInput::make('tube_width')
                                                             ->label('Medida de Tubo (m)')
                                                             ->numeric()
                                                             ->inputMode('decimal')
                                                             ->step(0.01)
                                                             ->minValue(0.1)
                                                             ->visible(fn($get) => \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower(\App\Models\ProductFinish::find($get('id'))?->name ?? ''), 'tubo'))
                                                             ->required(fn($get) => \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower(\App\Models\ProductFinish::find($get('id'))?->name ?? ''), 'tubo'))
                                                             ->live()
                                                             ->afterStateUpdated(fn($state, $set, $get) => self::calculateCustomPrice($set, $get)),

                                                         Forms\Components\Hidden::make('_requires_quantity'),
                                                    ])
                                                    ->defaultItems(0)
                                                    ->addActionLabel('+ Agregar Acabado')
                                                    ->live()
                                                    ->afterStateUpdated(fn($state, $set, $get) => self::calculateCustomPrice($set, $get))
                                                    ->columnSpan('full'),

                                                Forms\Components\Placeholder::make('_precio_calculado')
                                                    ->label('💰 Precio Calculado')
                                                    ->content(fn($get) => 'S/ ' . number_format(floatval($get('unit_price') ?? 0), 2))
                                                    ->extraAttributes(['class' => 'text-3xl font-bold text-success-600'])
                                                    ->columnSpan('full'),
                                            ])
                                            ->collapsible()
                                            ->collapsed(false)
                                            ->visible(fn($get) => $get('product_id') !== null)
                                            ->columnSpan('full'),

                                        // Campos estándar (ya existen, mantener)
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('quantity')
                                                    ->label('Cantidad')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(1)
                                                    ->required()
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, $set, $get) {
                                                        self::updateDetailSubtotal($state, $set, $get);
                                                    }),

                                                Forms\Components\TextInput::make('unit_price')
                                                    ->label('Precio Unitario')
                                                    ->prefix('S/')
                                                    ->numeric()
                                                    ->required()
                                                    ->disabled()
                                                    ->dehydrated(true),

                                                Forms\Components\TextInput::make('subtotal')
                                                    ->label('Subtotal')
                                                    ->prefix('S/')
                                                    ->disabled()
                                                    ->numeric()
                                                    ->dehydrated(true)
                                                    ->default(0),
                                            ]),

                                        Forms\Components\Textarea::make('notes')
                                            ->label('Notas')
                                            ->placeholder('Notas adicionales')
                                            ->maxLength(255)
                                            ->columnSpan('full'),
                                    ])
                                    ->defaultItems(1)
                                    ->reorderable(false)
                                    ->collapsible()
                                    ->collapsed(false)
                                    ->itemLabel(
                                        fn(array $state): ?string =>
                                        $state['product_id']
                                            ? Product::find($state['product_id'])?->name . ' - ' . ($state['quantity'] ?? 1) . ' x S/ ' . number_format((float)($state['unit_price'] ?? 0), 2)
                                            : null
                                    )
                                    ->columnSpan('full'),
                            ]),

                        Forms\Components\Section::make('Totales')
                            ->description('Resumen de los montos de la cotización')
                            ->icon('heroicon-o-calculator')
                            ->collapsible()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('subtotal')
                                            ->label('Subtotal')
                                            ->prefix('S/')
                                            ->disabled()
                                            ->numeric()
                                            ->default(0)
                                            ->dehydrated(true),

                                        Forms\Components\TextInput::make('tax')
                                            ->label('IGV (18%)')
                                            ->prefix('S/')
                                            ->disabled()
                                            ->numeric()
                                            ->default(0)
                                            ->dehydrated(true),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('discount')
                                            ->label('Descuento')
                                            ->prefix('S/')
                                            ->numeric()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                static::recalculateTotals($set, $get);
                                            }),

                                        Forms\Components\TextInput::make('total')
                                            ->label('Total')
                                            ->prefix('S/')
                                            ->disabled()
                                            ->numeric()
                                            ->default(0)
                                            ->dehydrated(true)
                                            ->extraAttributes(['class' => 'text-primary-600 font-bold']),
                                    ]),
                            ]),

                        Forms\Components\Section::make('Anticipo')
                            ->description('Dinero a cuenta que deja el cliente')
                            ->icon('heroicon-o-banknotes')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('advance_payment')
                                            ->label('Monto del Anticipo')
                                            ->prefix('S/')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                $total = floatval($get('total') ?? 0);
                                                $advance = floatval($state ?? 0);

                                                // Validar que el anticipo no sea mayor al total
                                                if ($advance > $total) {
                                                    $set('advance_payment', $total);
                                                }
                                            })
                                            ->helperText('Monto que el cliente deja como anticipo o señal'),

                                        Forms\Components\Placeholder::make('pending_balance')
                                            ->label('Saldo Pendiente')
                                            ->content(function ($get) {
                                                $total = floatval($get('total') ?? 0);
                                                $advance = floatval($get('advance_payment') ?? 0);
                                                $pending = $total - $advance;
                                                return 'S/ ' . number_format($pending, 2);
                                            })
                                            ->extraAttributes(['class' => 'text-lg font-semibold text-primary-600']),
                                    ]),

                                Forms\Components\Textarea::make('advance_payment_notes')
                                    ->label('Notas del Anticipo')
                                    ->placeholder('Observaciones sobre el anticipo (método de pago, fecha, etc.)')
                                    ->maxLength(500)
                                    ->columnSpan('full'),
                            ]),

                        Forms\Components\Section::make('Notas y Condiciones')
                            ->description('Información adicional para la cotización')
                            ->icon('heroicon-o-document')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label('Notas')
                                    ->placeholder('Notas adicionales para el cliente')
                                    ->maxLength(500),

                                Forms\Components\Textarea::make('terms_and_conditions')
                                    ->label('Términos y Condiciones')
                                    ->placeholder('Términos y condiciones de la cotización')
                                    ->default('1. Precios incluyen IGV.
2. Cotización válida hasta la fecha indicada.
3. Forma de pago según lo acordado.
4. Tiempo de entrega a coordinar.')
                                    ->maxLength(1000),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('quotation_number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Fecha Emisión')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Válido Hasta')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(
                        fn(Quotation $record): string =>
                        $record->valid_until < now() && !$record->isConverted() ? 'danger' : 'success'
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(
                        fn(string $state): string =>
                        match ($state) {
                            'draft' => 'gray',
                            'sent' => 'info',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            'expired' => 'warning',
                            'converted' => 'primary',
                            default => 'gray',
                        }
                    )
                    ->formatStateUsing(
                        fn(string $state): string =>
                        match ($state) {
                            'draft' => 'Borrador',
                            'sent' => 'Enviada',
                            'approved' => 'Aprobada',
                            'rejected' => 'Rechazada',
                            'expired' => 'Vencida',
                            'converted' => 'Convertida',
                            default => $state,
                        }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('advance_payment')
                    ->label('Anticipo')
                    ->money('PEN')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn($state) => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->multiple()
                    ->options([
                        Quotation::STATUS_DRAFT => 'Borrador',
                        Quotation::STATUS_SENT => 'Enviada',
                        Quotation::STATUS_APPROVED => 'Aprobada',
                        Quotation::STATUS_REJECTED => 'Rechazada',
                        Quotation::STATUS_EXPIRED => 'Vencida',
                        Quotation::STATUS_CONVERTED => 'Convertida',
                    ])
                    ->indicator('Estado'),

                Tables\Filters\Filter::make('valid')
                    ->label('Vigentes')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->where('valid_until', '>=', now())
                            ->whereNotIn('status', [Quotation::STATUS_REJECTED, Quotation::STATUS_EXPIRED, Quotation::STATUS_CONVERTED])
                    )
                    ->indicator('Vigentes'),

                Tables\Filters\Filter::make('expired')
                    ->label('Vencidas')
                    ->query(
                        fn(Builder $query): Builder =>
                        $query->where('valid_until', '<', now())
                            ->whereNotIn('status', [Quotation::STATUS_REJECTED, Quotation::STATUS_EXPIRED, Quotation::STATUS_CONVERTED])
                    )
                    ->indicator('Vencidas'),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->indicator('Cliente'),
            ])
            ->actions([
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn(Quotation $record) => !$record->isConverted()),
                
                // ACCIÓN: CONVERTIR A COMPROBANTE
                Tables\Actions\Action::make('convert')
                    ->label('Facturar')
                    ->icon('heroicon-o-document-currency-dollar')
                    ->color('success')
                    ->visible(fn(Quotation $record) => !$record->isConverted() && !$record->isDraft())
                    ->modalHeading('Generar Comprobante de Pago')
                    ->modalDescription('Esto convertirá la cotización en una Orden y generará el comprobante electrónico.')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('invoice_type')
                                    ->label('Tipo de Comprobante')
                                    ->options([
                                        'receipt' => 'Boleta de Venta',
                                        'invoice' => 'Factura Electrónica',
                                        'sales_note' => 'Nota de Venta (Interna)',
                                    ])
                                    ->default('receipt')
                                    ->required()
                                    ->reactive(),
                                
                                Forms\Components\Select::make('payment_method')
                                    ->label('Medio de Pago')
                                    ->options([
                                        'cash' => 'Efectivo',
                                        'yape' => 'Yape',
                                        'plin' => 'Plin',
                                        'card' => 'Tarjeta',
                                        'transfer' => 'Transferencia',
                                    ])
                                    ->default('cash')
                                    ->required(),
                            ]),
                        
                        Forms\Components\TextInput::make('payment_amount')
                            ->label('Monto a Pagar')
                            ->default(fn(Quotation $record) => $record->total)
                            ->numeric()
                            ->prefix('S/')
                            ->required(),
                    ])
                    ->action(function (Quotation $record, array $data) {
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function() use ($record, $data) {
                                // 1. Convertir a Pedido (Order)
                                $order = $record->convertToOrder('takeout');

                                // 2. Registrar el Pago
                                $order->registerPayment(
                                    $data['payment_method'],
                                    $data['payment_amount'],
                                    'Pago directo desde Cotización'
                                );

                                // 3. Determinar Serie según tipo
                                // Lógica simple de series por defecto (ajustar según tu lógica real de Series)
                                $seriesPrefix = match($data['invoice_type']) {
                                    'invoice' => 'F001',
                                    'receipt' => 'B001',
                                    'sales_note' => 'N001',
                                    default => 'T001'
                                };
                                
                                // Buscar si existe una serie configurada en BD
                                $serieDb = \App\Models\DocumentSeries::where('series', $seriesPrefix)->first();
                                if (!$serieDb) {
                                     // Fallback o error si no hay series
                                     // throw new \Exception("No hay serie configurada para {$seriesPrefix}");
                                }
                                
                                // 4. Generar el Comprobante
                                // Nota: generateInvoice usa Transaction internamente, pero estamos dentro de una.
                                // Asegúrate que tu modelo soporte transacciones anidadas o simplemente llama lógico.
                                $invoice = $order->generateInvoice(
                                    $data['invoice_type'],
                                    $seriesPrefix,
                                    $record->customer_id
                                );

                                if ($invoice) {
                                    Notification::make()
                                        ->title('Éxito')
                                        ->body("Comprobante {$invoice->series}-{$invoice->number} generado correctamente.")
                                        ->success()
                                        ->send();
                                        
                                    // Abrir PDF en nueva pestaña (Redirección)
                                    // En Action de Filament, redirect se hace retornando response o usando redirect()
                                     // return redirect()->route('print.invoice', $invoice);
                                }
                            });
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn(Quotation $record) => !$record->isConverted()),

                Action::make('print')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn(Quotation $record) => route('filament.admin.resources.quotations.print', ['quotation' => $record]))
                    ->openUrlInNewTab(),

                Action::make('email')
                    ->label('Enviar por Email')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required()
                            ->default(fn(Quotation $record) => $record->customer->email ?? ''),

                        Forms\Components\TextInput::make('subject')
                            ->label('Asunto')
                            ->default(fn(Quotation $record) => 'Cotización ' . $record->quotation_number),

                        Forms\Components\Textarea::make('message')
                            ->label('Mensaje')
                            ->default('Adjuntamos la cotización solicitada. Por favor, revise los detalles y no dude en contactarnos si tiene alguna pregunta.'),
                    ])
                    ->action(function (array $data, Quotation $record) {
                        // Enviar la cotización por correo electrónico
                        $response = \Illuminate\Support\Facades\Http::post(
                            route('filament.admin.resources.quotations.email', $record),
                            $data
                        );

                        if ($response->successful()) {
                            Notification::make()
                                ->title('Cotización enviada')
                                ->body('La cotización ha sido enviada correctamente a ' . $data['email'])
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error')
                                ->body('Ha ocurrido un error al enviar la cotización: ' . $response->body())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('export')
                        ->label('Exportar a CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            return response()->streamDownload(function () use ($records) {
                                $csv = fopen('php://output', 'w');

                                // Encabezados
                                fputcsv($csv, [
                                    'Número',
                                    'Cliente',
                                    'Fecha Emisión',
                                    'Válido Hasta',
                                    'Estado',
                                    'Subtotal',
                                    'IGV',
                                    'Descuento',
                                    'Total',
                                ]);

                                // Datos
                                foreach ($records as $record) {
                                    fputcsv($csv, [
                                        $record->quotation_number,
                                        $record->customer->name,
                                        $record->issue_date->format('d/m/Y'),
                                        $record->valid_until->format('d/m/Y'),
                                        match ($record->status) {
                                            'draft' => 'Borrador',
                                            'sent' => 'Enviada',
                                            'approved' => 'Aprobada',
                                            'rejected' => 'Rechazada',
                                            'expired' => 'Vencida',
                                            'converted' => 'Convertida',
                                            default => $record->status,
                                        },
                                        number_format($record->subtotal, 2),
                                        number_format($record->tax, 2),
                                        number_format($record->discount, 2),
                                        number_format($record->total, 2),
                                    ]);
                                }

                                fclose($csv);
                            }, 'cotizaciones.csv', [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="cotizaciones.csv"',
                            ]);
                        })
                        ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
            'view' => Pages\ViewQuotation::route('/{record}'),
            'print' => Pages\PrintQuotation::route('/{record}/print'),
        ];
    }

    /**
     * Actualiza el precio unitario y subtotal cuando se selecciona un producto.
     *
     * @param mixed $state El ID del producto seleccionado
     * @param callable $set Función para establecer valores en el formulario
     * @param callable $get Función para obtener valores del formulario
     * @return void
     */
    protected static function updateProductPrice($state, $set, $get): void
    {
        try {
            if ($state) {
                $product = Product::find($state);
                if ($product) {
                    $set('unit_price', $product->price);

                    // Calcular subtotal inmediatamente
                    $quantity = floatval($get('quantity') ?? 1);
                    $subtotal = $product->price * $quantity;
                    $set('subtotal', $subtotal);
                }
            }
        } catch (\Exception $e) {
            // Registrar error silenciosamente
            \Illuminate\Support\Facades\Log::error('Error al actualizar precio del producto', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Actualiza el subtotal de un detalle de cotización.
     *
     * @param mixed $state El valor actual del campo
     * @param callable $set Función para establecer valores en el formulario
     * @param callable $get Función para obtener valores del formulario
     * @return void
     */
    protected static function updateDetailSubtotal($state, $set, $get): void
    {
        try {
            $quantity = floatval($get('quantity') ?? 1);
            $unitPrice = floatval($get('unit_price') ?? 0);
            $subtotal = $quantity * $unitPrice;
            $set('subtotal', $subtotal);

            // IMPORTANTE: Recalcular totales generales
            static::recalculateTotals($set, $get);
        } catch (\Exception $e) {
            // Registrar error silenciosamente
            \Illuminate\Support\Facades\Log::error('Error al actualizar subtotal', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Método auxiliar para recalcular los totales generales de la cotización
     * basado en los detalles de productos.
     */
    protected static function recalculateTotals($set, $get): void
    {
        try {
            // Determinar en qué nivel estamos y obtener el prefijo para llegar a la raíz (donde están los totales)
            $rootPrefix = '';
            $details = null;

            if ($get('details') !== null) {
                $rootPrefix = '';
                $details = $get('details');
            } elseif ($get('../details') !== null) {
                $rootPrefix = '../';
                $details = $get('../details');
            } elseif ($get('../../details') !== null) {
                $rootPrefix = '../../';
                $details = $get('../../details');
            }

            if ($details === null) {
                return;
            }
            
            $subtotalTotal = 0;

            foreach ($details as $detail) {
                $quantity = floatval($detail['quantity'] ?? 1);
                $unitPrice = floatval($detail['unit_price'] ?? 0);
                $subtotalTotal += ($quantity * $unitPrice);
            }

            // Los precios YA INCLUYEN IGV
            $totalConIgv = $subtotalTotal;
            
            // Obtener descuento del nivel raíz
            $discount = floatval($get($rootPrefix . 'discount') ?? 0);
            
            $totalFinal = $totalConIgv - $discount;

            // Calcular IGV desglosado (incluido en el total)
            $subtotalSinIgv = round($totalFinal / 1.18, 2);
            $tax = round($totalFinal - $subtotalSinIgv, 2);

            // Actualizar campos de totales en la raíz
            $set($rootPrefix . 'subtotal', $subtotalSinIgv);
            $set($rootPrefix . 'tax', $tax);
            $set($rootPrefix . 'total', $totalFinal);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al recalcular totales', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected static function calculateCustomPrice($set, $get): void
    {
        try {
            // Buscamos los campos necesarios probando el scope actual y scopes superiores
            // Esto es mucho más robusto que usar prefijos fijos detectados por product_id
            $prefixes = ['', '../', '../../', '../../../'];
            
            $width = 0;
            $height = 0;
            $materialId = null;
            $finishes = [];
            $foundContext = false;
            $currentPrefix = '';

            foreach ($prefixes as $prefix) {
                $mId = $get($prefix . 'material_id');
                $h = $get($prefix . 'height');
                
                // Si encontramos material y altura en este nivel, asumimos que es el contexto del detalle
                if ($mId !== null && $h !== null) {
                    $width = floatval($get($prefix . 'width') ?? 0);
                    $height = floatval($h ?? 0);
                    $materialId = $mId;
                    $finishes = $get($prefix . 'finishes') ?? [];
                    $currentPrefix = $prefix;
                    $foundContext = true;
                    break;
                }
            }

            if (!$foundContext || !$materialId || !$height) {
                return;
            }

            // Validación específica para Lona Traslúcida vs Resto
            $material = \App\Models\ProductMaterial::find($materialId);
            $isLonaTrasluc = $material && \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower($material->name ?? ''), 'lona trasluc');

            if (!$isLonaTrasluc && !$width) {
                return;
            }

            // Buscar el ID del cliente (usualmente en la raíz)
            $customerId = $get('customer_id') 
                ?? $get('../customer_id') 
                ?? $get('../../customer_id') 
                ?? $get('../../../customer_id')
                ?? $get('../../../../customer_id');
                
            $priceTierId = null;
            if ($customerId) {
                $customer = \App\Models\Customer::find($customerId);
                $priceTierId = $customer?->price_tier_id;
            }

            // Calcular precio usando el servicio
            $calculator = new \App\Services\PriceCalculatorService();
            $price = $calculator->calculatePrice(
                $width,
                $height,
                $materialId,
                $finishes,
                $priceTierId
            );

            // Aplicar el precio y subtotal al ítem del detalle usando el prefijo encontrado
            $set($currentPrefix . 'unit_price', $price);
            $quantity = floatval($get($currentPrefix . 'quantity') ?? 1);
            $set($currentPrefix . 'subtotal', $price * $quantity);
            $set($currentPrefix . '_precio_calculado', 'S/ ' . number_format($price, 2));

            // Recalcular totales generales
            self::recalculateTotals($set, $get);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error crítico en calculateCustomPrice', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
