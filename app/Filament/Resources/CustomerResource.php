<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Collection;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?int $navigationSort = 2;

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
                                Forms\Components\Section::make('Perfil del Cliente')
                                    ->description('Información principal e identidad comercial')
                                    ->icon('heroicon-m-user-circle')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Razón Social / Nombre Completo')
                                            ->required()
                                            ->maxLength(255)
                                            ->prefixIcon('heroicon-m-user')
                                            ->columnSpanFull(),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('document_type')
                                                    ->label('Tipo de Documento')
                                                    ->options(Customer::DOCUMENT_TYPES)
                                                    ->default('DNI')
                                                    ->selectablePlaceholder(false)
                                                    ->live(),

                                                Forms\Components\TextInput::make('document_number')
                                                    ->label('Número de Documento')
                                                    ->maxLength(15)
                                                    ->prefixIcon('heroicon-m-identification')
                                                    ->placeholder(fn($get) => $get('document_type') === 'DNI' ? '00000000' : '20000000000')
                                                    ->rules([
                                                        fn($get) => function (string $attribute, $value, $fail) use ($get) {
                                                            if (empty($value)) return;
                                                            $type = $get('document_type');
                                                            if ($type === 'DNI' && strlen($value) !== 8) $fail('El DNI debe tener 8 dígitos.');
                                                            if ($type === 'RUC' && strlen($value) !== 11) $fail('El RUC debe tener 11 dígitos.');
                                                        },
                                                    ]),
                                            ]),
                                    ]),

                                Forms\Components\Section::make('Contacto y Ubicación')
                                    ->description('Canales de comunicación y dirección fiscal')
                                    ->icon('heroicon-m-map-pin')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('phone')
                                            ->label('Teléfono / Celular')
                                            ->tel()
                                            ->required()
                                            ->maxLength(20)
                                            ->prefixIcon('heroicon-m-phone')
                                            ->placeholder('999 999 999'),

                                        Forms\Components\TextInput::make('email')
                                            ->label('Correo Electrónico')
                                            ->email()
                                            ->maxLength(255)
                                            ->prefixIcon('heroicon-m-envelope')
                                            ->placeholder('contacto@empresa.com'),

                                        Forms\Components\Textarea::make('address')
                                            ->label('Dirección Fiscal')
                                            ->rows(2)
                                            ->maxLength(255)
                                            ->columnSpanFull()
                                            ->placeholder('Av. Principal 123, Oficina 404...'),

                                        Forms\Components\Textarea::make('address_references')
                                            ->label('Referencias')
                                            ->rows(1)
                                            ->maxLength(255)
                                            ->columnSpanFull()
                                            ->placeholder('Frente al parque...'),
                                    ]),
                            ]),

                        // Columna Lateral (Sidebar)
                        Forms\Components\Group::make()
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                Forms\Components\Section::make('Clasificación')
                                    ->icon('heroicon-m-tag')
                                    ->schema([
                                        Forms\Components\Select::make('customer_type')
                                            ->label('Tipo de Cliente')
                                            ->options([
                                                'Particular' => 'Particular',
                                                'Negocio' => 'Negocio',
                                                'Empresa' => 'Empresa',
                                                'Gobierno' => 'Gobierno',
                                            ])
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('sales_channel')
                                            ->label('Canal de Captación')
                                            ->options([
                                                'Redes Sociales' => 'Redes Sociales',
                                                'Web' => 'Web',
                                                'Presencial' => 'Presencial',
                                                'Recomendación' => 'Recomendación',
                                                'Ferias' => 'Ferias',
                                            ])
                                            ->searchable(),

                                        Forms\Components\TextInput::make('price_tier_id')
                                            ->label('Nivel de Precio')
                                            ->numeric()
                                            ->prefix('#'),
                                    ]),

                                Forms\Components\Section::make('Estado')
                                    ->icon('heroicon-m-shield-check')
                                    ->schema([
                                        Forms\Components\Toggle::make('tax_validated')
                                            ->label('Validado Fiscalmente')
                                            ->onColor('success')
                                            ->offColor('danger')
                                            ->onIcon('heroicon-m-check')
                                            ->offIcon('heroicon-m-x-mark'),

                                        Forms\Components\Placeholder::make('created_at')
                                            ->label('Registrado el')
                                            ->content(fn (?Customer $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                                        Forms\Components\Placeholder::make('updated_at')
                                            ->label('Última actualización')
                                            ->content(fn (?Customer $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
                                    ]),
                            ]),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre/Razón Social')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('formattedDocument')
                    ->label('Documento')
                    ->searchable(['document_type', 'document_number'])
                    ->sortable(['document_type', 'document_number'])
                    ->copyable()
                    ->icon('heroicon-o-identification'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->icon('heroicon-o-phone')
                    ->copyable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->icon('heroicon-o-envelope')
                    ->copyable(),

                Tables\Columns\TextColumn::make('fullAddress')
                    ->label('Dirección')
                    ->searchable(['address', 'address_references'])
                    ->limit(30)
                    ->tooltip(fn(Customer $record): ?string => $record->fullAddress)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('tax_validated')
                    ->label('Validado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger'),

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

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Eliminado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo de Documento')
                    ->options(Customer::DOCUMENT_TYPES),

                Tables\Filters\TernaryFilter::make('tax_validated')
                    ->label('Estado de Validación')
                    ->placeholder('Todos los clientes')
                    ->trueLabel('Validados')
                    ->falseLabel('No validados'),

                Tables\Filters\TrashedFilter::make()
                    ->label('Mostrar eliminados'),
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
                    Tables\Actions\RestoreAction::make()
                        ->label('Restaurar'),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Acciones'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar Seleccionados'),
                    Tables\Actions\RestoreBulkAction::make()
                        ->label('Restaurar Seleccionados'),
                    Tables\Actions\BulkAction::make('marcar_validados')
                        ->label('Marcar como Validados')
                        ->icon('heroicon-o-check-badge')
                        ->action(function (Collection $records): void {
                            $records->each(function (Customer $record): void {
                                $record->update(['tax_validated' => true]);
                            });
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading('No hay clientes registrados')
            ->emptyStateDescription('Registra tu primer cliente para comenzar a gestionar tus ventas.')
            ->emptyStateActions([
                Tables\Actions\Action::make('crear')
                    ->label('Crear Cliente')
                    ->url(route('filament.admin.resources.customers.create'))
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }



    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }
}
