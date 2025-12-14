<?php

namespace App\Filament\Resources;

use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Inventario y Compras';

    protected static ?string $navigationLabel = 'Proveedores';

    protected static ?string $modelLabel = 'Proveedor';

    protected static ?string $pluralModelLabel = 'Proveedores';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información General')
                    ->schema([
                        Forms\Components\TextInput::make('business_name')
                            ->required()
                            ->maxLength(255)
                            ->label('Razón Social')
                            ->validationMessages([
                                'required' => '🏢 ¡Necesitamos el nombre de la empresa! Por favor, escribe la razón social completa.',
                                'max' => '📝 El nombre de la empresa es muy largo. Máximo 255 caracteres.',
                                'string' => '🔤 El nombre de la empresa debe ser texto válido.',
                            ])
                            ->helperText('Nombre oficial de la empresa o negocio'),
                        Forms\Components\TextInput::make('tax_id')
                            ->required()
                            ->maxLength(20)
                            ->label('RUC')
                            ->validationMessages([
                                'required' => '📄 ¡El RUC es obligatorio! Ingresa el número de identificación tributaria.',
                                'max' => '🔢 El RUC es muy largo. Máximo 20 caracteres.',
                                'string' => '🔤 El RUC debe contener solo números y letras válidas.',
                            ])
                            ->helperText('Número de RUC de 11 dígitos')
                            ->mask('99999999999'),
                        Forms\Components\TextInput::make('address')
                            ->maxLength(255)
                            ->label('Dirección')
                            ->validationMessages([
                                'max' => '📍 La dirección es muy larga. Máximo 255 caracteres.',
                                'string' => '🔤 La dirección debe ser texto válido.',
                            ])
                            ->helperText('Dirección completa del proveedor (opcional)')
                            ->placeholder('Av. Principal 123, Lima'),
                    ])->columns(2),

                Forms\Components\Section::make('Información de Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20)
                            ->label('Teléfono')
                            ->validationMessages([
                                'max' => '📱 El número de teléfono es muy largo. Máximo 20 caracteres.',
                            ])
                            ->helperText('Número de teléfono principal (opcional)')
                            ->mask('999-999-999')
                            ->placeholder('999-999-999'),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->label('Correo Electrónico')
                            ->validationMessages([
                                'email' => '📧 ¡Formato de email incorrecto! Ejemplo: proveedor@empresa.com',
                                'max' => '📮 El email es muy largo. Máximo 255 caracteres.',
                            ])
                            ->helperText('Email de contacto (opcional)')
                            ->placeholder('proveedor@empresa.com'),
                        Forms\Components\TextInput::make('contact_name')
                            ->maxLength(255)
                            ->label('Nombre de Contacto')
                            ->validationMessages([
                                'max' => '👤 El nombre de contacto es muy largo. Máximo 255 caracteres.',
                                'string' => '🔤 El nombre debe contener solo letras y espacios.',
                            ])
                            ->helperText('Persona de contacto (opcional)')
                            ->placeholder('Juan Pérez'),
                        Forms\Components\TextInput::make('contact_phone')
                            ->tel()
                            ->maxLength(20)
                            ->label('Teléfono de Contacto')
                            ->validationMessages([
                                'max' => '📱 El teléfono de contacto es muy largo. Máximo 20 caracteres.',
                            ])
                            ->helperText('Teléfono alternativo (opcional)')
                            ->mask('999-999-999'),
                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->helperText('¿Este proveedor está activo?')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')
                    ->searchable()
                    ->sortable()
                    ->label('Razón Social'),
                Tables\Columns\TextColumn::make('tax_id')
                    ->searchable()
                    ->sortable()
                    ->label('RUC'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->label('Teléfono'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->label('Correo'),
                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->label('Activo'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('active')
                    ->options([
                        true => 'Activos',
                        false => 'Inactivos',
                    ])
                    ->label('Estado'),
            ])
            ->headerActions([
                // Tables\Actions\ExportAction::make()
                //     ->exporter(SuppliersExport::class)
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\SupplierResource\Relations\PurchaseRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\SupplierResource\Pages\ListSuppliers::route('/'),
            'create' => \App\Filament\Resources\SupplierResource\Pages\CreateSupplier::route('/create'),
            'edit' => \App\Filament\Resources\SupplierResource\Pages\EditSupplier::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
