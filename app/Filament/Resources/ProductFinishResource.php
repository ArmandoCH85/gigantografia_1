<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductFinishResource\Pages;
use App\Models\ProductFinish;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductFinishResource extends Resource
{
    protected static ?string $model = ProductFinish::class;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'Productos y Catálogo';

    protected static ?string $modelLabel = 'Acabado';

    protected static ?string $pluralModelLabel = 'Acabados';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Acabado')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->label('Nombre del Acabado'),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->label('Código'),
                        Forms\Components\TextInput::make('additional_cost')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->default(0)
                            ->label('Costo Adicional'),
                        Forms\Components\Toggle::make('requires_quantity')
                            ->required()
                            ->label('Requiere Cantidad')
                            ->helperText('Activar si el acabado se cobra por unidad (ej: ojales).'),
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
                    ->label('Acabado'),
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->label('Código'),
                Tables\Columns\TextColumn::make('additional_cost')
                    ->money('PEN')
                    ->sortable()
                    ->label('Costo Adicional'),
                Tables\Columns\IconColumn::make('requires_quantity')
                    ->boolean()
                    ->label('Requiere Cantidad'),
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
            'index' => Pages\ListProductFinishes::route('/'),
            'create' => Pages\CreateProductFinish::route('/create'),
            'edit' => Pages\EditProductFinish::route('/{record}/edit'),
        ];
    }
}
