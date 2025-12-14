<?php

namespace App\Filament\Resources;

use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SimpleWarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Inventario y Compras';

    protected static ?string $navigationLabel = 'Almacenes';

    protected static ?string $modelLabel = 'Almacén';

    protected static ?string $pluralModelLabel = 'Almacenes';

    protected static ?string $slug = 'warehouses';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Nombre'),
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->label('Código'),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->label('Descripción'),
                Forms\Components\TextInput::make('location')
                    ->maxLength(255)
                    ->label('Ubicación'),
                Forms\Components\Toggle::make('is_default')
                    ->label('Almacén Principal'),
                Forms\Components\Toggle::make('active')
                    ->label('Activo')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nombre'),
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->label('Código'),
                Tables\Columns\TextColumn::make('location')
                    ->searchable()
                    ->label('Ubicación'),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean()
                    ->label('Principal'),
                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->label('Activo'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => \App\Filament\Resources\SimpleWarehouseResource\Pages\ListSimpleWarehouses::route('/'),
            'create' => \App\Filament\Resources\SimpleWarehouseResource\Pages\CreateSimpleWarehouse::route('/create'),
            'edit' => \App\Filament\Resources\SimpleWarehouseResource\Pages\EditSimpleWarehouse::route('/{record}/edit'),
        ];
    }
}
