<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    protected static ?string $navigationLabel = 'Productos';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        // Columna Principal
                        Forms\Components\Group::make()
                            ->columnSpan(['lg' => 2])
                            ->schema([
                                Forms\Components\Section::make('Detalles del Producto')
                                    ->description('Información principal del producto o servicio')
                                    ->icon('heroicon-o-cube')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nombre del Producto')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ej: Banner Roll-up, Vinil Adhesivo')
                                            ->columnSpanFull()
                                            ->prefixIcon('heroicon-o-tag'),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('code')
                                                    ->label('Código SKU')
                                                    ->default(fn() => 'PRD-' . strtoupper(str()->random(8)))
                                                    ->required()
                                                    ->maxLength(20)
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->unique(Product::class, 'code', ignoreRecord: true)
                                                    ->prefixIcon('heroicon-o-qr-code'),

                                                Forms\Components\Select::make('product_type')
                                                    ->label('Tipo de Producto')
                                                    ->options([
                                                        Product::TYPE_SERVICE_GIGANTOGRAFIA => 'Gigantografía (Configurable)',
                                                        Product::TYPE_SALE_ITEM => 'Producto Estándar (Unidad)',
                                                    ])
                                                    ->required()
                                                    ->default(Product::TYPE_SALE_ITEM)
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                        if ($state === Product::TYPE_SERVICE_GIGANTOGRAFIA) {
                                                            $set('current_stock', 0);
                                                            $set('sale_price', 0);
                                                            $set('current_cost', 0);
                                                        }
                                                    })
                                                    ->prefixIcon('heroicon-o-swatch'),
                                            ]),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Descripción')
                                            ->placeholder('Describa las características del producto...')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make('Multimedia')
                                    ->description('Imágenes del producto')
                                    ->icon('heroicon-o-photo')
                                    ->schema([
                                        Forms\Components\FileUpload::make('image_path')
                                            ->label('Imagen Principal')
                                            ->image()
                                            ->imageEditor()
                                            ->directory('productos')
                                            ->columnSpanFull()
                                            ->downloadable()
                                            ->openable(),
                                    ]),
                            ]),

                        // Columna Lateral (Sidebar)
                        Forms\Components\Group::make()
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                Forms\Components\Section::make('Clasificación')
                                    ->icon('heroicon-o-tag')
                                    ->schema([
                                        Forms\Components\Select::make('category_id')
                                            ->label('Categoría')
                                            ->relationship('category', 'name')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Nombre')
                                                    ->required()
                                                    ->maxLength(50),
                                                Forms\Components\TextInput::make('description')
                                                    ->label('Descripción')
                                                    ->maxLength(255),
                                            ])
                                            ->prefixIcon('heroicon-o-rectangle-stack'),
                                    ]),

                                Forms\Components\Section::make('Precios e Inventario')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->schema([
                                        Forms\Components\TextInput::make('sale_price')
                                            ->label('Precio Referencial')
                                            ->helperText(
                                                fn(Forms\Get $get) =>
                                                $get('product_type') === Product::TYPE_SERVICE_GIGANTOGRAFIA
                                                    ? 'Se calcula en el pedido.'
                                                    : 'Precio venta unidad.'
                                            )
                                            ->required()
                                            ->numeric()
                                            ->prefix('S/')
                                            ->default(0.00)
                                            ->disabled(fn(Forms\Get $get) => $get('product_type') === Product::TYPE_SERVICE_GIGANTOGRAFIA)
                                            ->dehydrated()
                                            ->step(0.01),

                                        Forms\Components\TextInput::make('current_cost')
                                            ->label('Costo Referencial')
                                            ->required()
                                            ->numeric()
                                            ->prefix('S/')
                                            ->default(0.00)
                                            ->disabled(fn(Forms\Get $get) => $get('product_type') === Product::TYPE_SERVICE_GIGANTOGRAFIA)
                                            ->dehydrated()
                                            ->step(0.01),

                                        Forms\Components\TextInput::make('current_stock')
                                            ->label('Stock Actual')
                                            ->numeric()
                                            ->default(0.00)
                                            ->step(0.001)
                                            ->disabled(fn(Forms\Get $get) => $get('product_type') === Product::TYPE_SERVICE_GIGANTOGRAFIA)
                                            ->visible(fn(Forms\Get $get) => $get('product_type') !== Product::TYPE_SERVICE_GIGANTOGRAFIA)
                                            ->dehydrated()
                                            ->prefixIcon('heroicon-o-archive-box'),
                                    ]),

                                Forms\Components\Section::make('Estado')
                                    ->icon('heroicon-o-check-circle')
                                    ->schema([
                                        Forms\Components\Toggle::make('active')
                                            ->label('Activo')
                                            ->default(true)
                                            ->onColor('success')
                                            ->offColor('danger'),

                                        Forms\Components\Toggle::make('available')
                                            ->label('Disponible para Venta')
                                            ->default(true)
                                            ->onColor('success')
                                            ->offColor('danger'),

                                        Forms\Components\Placeholder::make('created_at')
                                            ->label('Creado el')
                                            ->content(fn (?Product $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                                        Forms\Components\Placeholder::make('updated_at')
                                            ->label('Última actualización')
                                            ->content(fn (?Product $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
                                    ]),
                            ]),
                    ])
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['category']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Imagen')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-product.png')),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->sortable()
                    ->searchable(),



                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Precio')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_cost')
                    ->label('Costo')
                    ->money('PEN')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('available')
                    ->label('Disponible')
                    ->boolean()
                    ->sortable(),



                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Filters\TernaryFilter::make('available')
                    ->label('Disponible')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar Seleccionados'),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }



    public static function getNavigationGroup(): ?string
    {
        return 'Productos y Catálogo';
    }
}
