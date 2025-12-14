<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductCategoryResource\Pages;
use App\Filament\Resources\ProductCategoryResource\RelationManagers;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Collection;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $modelLabel = 'Categoría de Producto';

    protected static ?string $pluralModelLabel = 'Categorías de Productos';

    protected static ?string $navigationLabel = 'Categorías';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Básica')
                    ->description('Datos principales de la categoría')
                    ->icon('heroicon-o-information-circle')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('Ingrese el nombre de la categoría')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('description')
                            ->label('Descripción')
                            ->maxLength(255)
                            ->placeholder('Ingrese una descripción breve')
                            ->columnSpan(1),
                    ]),

                Forms\Components\Section::make('Organización')
                    ->description('Configuración de visualización y jerarquía')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Select::make('parent_category_id')
                            ->label('Categoría Padre')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Seleccione una categoría padre (opcional)')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->required(),
                                Forms\Components\TextInput::make('description')
                                    ->label('Descripción'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('visible_in_menu')
                                    ->label('Visible en Menú')
                                    ->required()
                                    ->default(true)
                                    ->helperText('Determina si la categoría será visible en el menú')
                                    ->onIcon('heroicon-s-eye')
                                    ->offIcon('heroicon-s-eye-slash'),

                                Forms\Components\TextInput::make('display_order')
                                    ->label('Orden de Visualización')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->helperText('Número que determina el orden de visualización (menor = primero)')
                                    ->suffixIcon('heroicon-m-arrows-up-down'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-tag'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Categoría Padre')
                    ->sortable()
                    ->default('—')
                    ->icon('heroicon-o-arrow-up'),

                Tables\Columns\IconColumn::make('visible_in_menu')
                    ->label('Visible en Menú')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('display_order')
                    ->label('Orden')
                    ->numeric()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent_category_id')
                    ->label('Categoría Padre')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('visible_in_menu')
                    ->label('Visibilidad')
                    ->placeholder('Todas las categorías')
                    ->trueLabel('Solo visibles')
                    ->falseLabel('Solo ocultas')
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Ver')
                        ->color('gray'),
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->color('warning'),
                    Tables\Actions\DeleteAction::make()
                        ->label('Eliminar')
                        ->color('danger'),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Acciones'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar Seleccionados'),
                    Tables\Actions\BulkAction::make('cambiar_visibilidad')
                        ->label('Cambiar Visibilidad')
                        ->icon('heroicon-o-eye')
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                $record->visible_in_menu = $data['visible'];
                                $record->save();
                            }
                        })
                        ->form([
                            Forms\Components\Toggle::make('visible')
                                ->label('¿Mostrar en menú?')
                                ->default(true),
                        ]),
                ]),
            ])
            ->defaultSort('display_order', 'asc')
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateHeading('No hay categorías')
            ->emptyStateDescription('Crea tu primera categoría de productos para empezar a organizar tu inventario.')
            ->emptyStateActions([
                Tables\Actions\Action::make('crear')
                    ->label('Crear Categoría')
                    ->url(route('filament.admin.resources.product-categories.create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
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
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }



    public static function getNavigationGroup(): ?string
    {
        return 'Productos y Catálogo';
    }
}
