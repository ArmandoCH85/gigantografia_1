<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentSeriesResource\Pages;
use App\Models\DocumentSeries;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentSeriesResource extends Resource
{
    protected static ?string $model = DocumentSeries::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Facturación Electrónica';

    protected static ?string $navigationLabel = 'Series de Comprobantes';

    protected static ?int $navigationSort = 4;

    // Habilitar navegación para gestionar series de comprobantes
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $modelLabel = 'Serie de Comprobante';

    protected static ?string $pluralModelLabel = 'Series de Comprobantes';

    protected static ?string $slug = 'document-series';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('document_type')
                    ->label('Tipo de Documento')
                    ->options([
                        'invoice' => 'Factura',
                        'receipt' => 'Boleta',
                        'sales_note' => 'Nota de Venta',
                        'credit_note' => 'Nota de Crédito',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('series')
                    ->label('Serie')
                    ->required()
                    ->maxLength(10)
                    ->helperText('Ejemplo: F001, B001, NV001')
                    ->placeholder('Ingrese la serie'),
                Forms\Components\TextInput::make('current_number')
                    ->label('Numeración Actual')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->helperText('El próximo comprobante se emitirá con este número'),
                Forms\Components\Toggle::make('active')
                    ->label('Activo')
                    ->default(true)
                    ->helperText('Solo las series activas se utilizan para emitir comprobantes'),
                Forms\Components\Textarea::make('description')
                    ->label('Descripción')
                    ->maxLength(255)
                    ->placeholder('Descripción opcional de esta serie'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_type_name')
                    ->label('Tipo de Documento')
                    ->searchable(['document_type']),
                Tables\Columns\TextColumn::make('series')
                    ->label('Serie')
                    ->searchable(),
                Tables\Columns\TextColumn::make('current_number')
                    ->label('Próximo Número')
                    ->formatStateUsing(fn(int $state) => str_pad($state, 8, '0', STR_PAD_LEFT)),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo de Documento')
                    ->options([
                        'invoice' => 'Factura',
                        'receipt' => 'Boleta',
                        'sales_note' => 'Nota de Venta',
                        'credit_note' => 'Nota de Crédito',
                    ]),
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Activo')
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListDocumentSeries::route('/'),
            'create' => Pages\CreateDocumentSeries::route('/create'),
            'edit' => Pages\EditDocumentSeries::route('/{record}/edit'),
        ];
    }
}
