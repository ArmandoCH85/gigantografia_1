<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerPriceTierResource\Pages;
use App\Models\CustomerPriceTier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerPriceTierResource extends Resource
{
    protected static ?string $model = CustomerPriceTier::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Ventas';

    protected static ?string $modelLabel = 'Nivel de Precio';

    protected static ?string $pluralModelLabel = 'Niveles de Precio';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Nivel de Precio')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(50)
                            ->label('Nombre')
                            ->placeholder('Ej: Mayorista'),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->label('Código'),
                        Forms\Components\TextInput::make('discount_percentage')
                            ->required()
                            ->numeric()
                            ->suffix('%')
                            ->default(0)
                            ->maxValue(100)
                            ->label('Porcentaje de Descuento'),
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
                    ->label('Nombre'),
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->label('Código'),
                Tables\Columns\TextColumn::make('discount_percentage')
                    ->suffix('%')
                    ->sortable()
                    ->label('Descuento'),
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
            'index' => Pages\ListCustomerPriceTiers::route('/'),
            'create' => Pages\CreateCustomerPriceTier::route('/create'),
            'edit' => Pages\EditCustomerPriceTier::route('/{record}/edit'),
        ];
    }
}
