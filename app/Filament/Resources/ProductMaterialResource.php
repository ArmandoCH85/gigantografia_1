<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductMaterialResource\Pages;
use App\Models\ProductMaterial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductMaterialResource extends Resource
{
    protected static ?string $model = ProductMaterial::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = 'Productos y Catálogo';

    protected static ?string $modelLabel = 'Material';

    protected static ?string $pluralModelLabel = 'Materiales';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Material')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->label('Categoría')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(50),
                            ]),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->label('Nombre del Material'),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->label('Código'),
                        Forms\Components\TextInput::make('unit_price')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->label('Precio por m²'),
                        Forms\Components\TextInput::make('max_width')
                            ->numeric()
                            ->suffix('m')
                            ->label('Ancho Máximo'),
                        Forms\Components\TextInput::make('max_height')
                            ->numeric()
                            ->suffix('m')
                            ->label('Alto Máximo'),
                        Forms\Components\Toggle::make('active')
                            ->required()
                            ->default(true)
                            ->label('Activo'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Material'),
                Tables\Columns\TextColumn::make('category.name')
                    ->sortable()
                    ->label('Categoría'),
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->label('Código'),
                Tables\Columns\TextColumn::make('unit_price')
                    ->money('PEN')
                    ->sortable()
                    ->label('Precio m²'),
                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->label('Activo'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListProductMaterials::route('/'),
            'create' => Pages\CreateProductMaterial::route('/create'),
            'edit' => Pages\EditProductMaterial::route('/{record}/edit'),
        ];
    }
}
