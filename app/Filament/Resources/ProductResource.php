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
                Forms\Components\Section::make('Información Básica')
                    ->description('Defina el tipo de gigantografía (ej: Banner, Vinil, Lona). La configuración específica se hará en cada pedido.')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->default(fn() => 'PRD-' . strtoupper(str()->random(8)))
                            ->required()
                            ->maxLength(20)
                            ->disabled()
                            ->dehydrated()
                            ->unique(Product::class, 'code', ignoreRecord: true),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Tipo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Banner, Vinil en soporte, Lona, etc.')
                            ->helperText('Este es el nombre que verá el cliente al elegir el tipo de gigantografía'),

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
                            ]),


                        Forms\Components\Select::make('product_type')
                            ->label('Tipo de Producto')
                            ->options([
                                Product::TYPE_SALE_ITEM => 'Producto Estándar (Unidad)',
                                Product::TYPE_SERVICE_GIGANTOGRAFIA => 'Gigantografía (Configurable)',
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
                            }),

                    ])->columns(2),

                Forms\Components\Section::make('Precios y Costos')
                    ->description('Para gigantografías configurables, el precio final se calcula en cada pedido según material, medidas y acabados')
                    ->schema([
                        Forms\Components\TextInput::make('sale_price')
                            ->label('Precio de Referencia')
                            ->helperText(
                                fn(Forms\Get $get) =>
                                $get('product_type') === Product::TYPE_SERVICE_GIGANTOGRAFIA
                                    ? 'El precio se calculará en el pedido según medidas, material y acabados.'
                                    : 'Precio de venta por unidad.'
                            )
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->default(0.00)
                            ->disabled(fn(Forms\Get $get) => $get('product_type') === Product::TYPE_SERVICE_GIGANTOGRAFIA)
                            ->dehydrated()
                            ->maxValue(99999999.99)
                            ->step(0.01),

                        Forms\Components\TextInput::make('current_cost')
                            ->label('Costo Referencial')
                            ->helperText(
                                fn(Forms\Get $get) =>
                                $get('product_type') === Product::TYPE_SERVICE_GIGANTOGRAFIA
                                    ? 'El costo dependerá de los insumos utilizados.'
                                    : 'Costo actual del producto.'
                            )
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->default(0.00)
                            ->disabled(fn(Forms\Get $get) => $get('product_type') === Product::TYPE_SERVICE_GIGANTOGRAFIA)
                            ->dehydrated()
                            ->maxValue(99999999.99)
                            ->step(0.01),

                        Forms\Components\TextInput::make('current_stock')
                            ->label('Stock Actual')
                            ->numeric()
                            ->default(0.00)
                            ->step(0.001)
                            ->disabled(fn(Forms\Get $get) => $get('product_type') === Product::TYPE_SERVICE_GIGANTOGRAFIA)
                            ->visible(fn(Forms\Get $get) => $get('product_type') !== Product::TYPE_SERVICE_GIGANTOGRAFIA)
                            ->dehydrated(),
                    ])->columns(2),

                Forms\Components\Section::make('Detalles Adicionales')
                    ->description('Información complementaria del tipo de producto')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Ej: Ideal para exteriores, resistente a la intemperie, impresión de alta calidad...')
                            ->helperText('Describa las características generales de este tipo de gigantografía')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('image_path')
                            ->label('Imagen')
                            ->image()
                            ->imageEditor()
                            ->directory('productos')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Estado y Configuración')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->required()
                            ->default(true)
                            ->helperText('Determina si el producto está activo en el sistema'),

                        Forms\Components\Toggle::make('available')
                            ->label('Disponible')
                            ->required()
                            ->default(true)
                            ->helperText('Determina si el producto está disponible para la venta'),
                    ])->columns(2),
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
