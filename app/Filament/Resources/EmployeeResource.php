<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'Empleado';

    protected static ?string $pluralModelLabel = 'Empleados';

    protected static ?string $navigationLabel = 'Empleados';

    protected static ?int $navigationSort = 1;

    /**
     * OPTIMIZACIÓN: Agregar eager loading para evitar N+1 queries
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['user']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Personal')
                    ->description('Datos personales del empleado')
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ingrese el nombre'),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Apellidos')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ingrese los apellidos'),

                        Forms\Components\TextInput::make('document_number')
                            ->label('Número de Documento')
                            ->required()
                            ->maxLength(15)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Ingrese número de identificación'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('Ingrese número telefónico'),

                        Forms\Components\Textarea::make('address')
                            ->label('Dirección')
                            ->maxLength(255)
                            ->placeholder('Ingrese dirección completa')
                            ->columnSpan(2),
                    ]),

                Forms\Components\Section::make('Información Laboral')
                    ->description('Datos del puesto y contratación')
                    ->icon('heroicon-o-briefcase')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('position')
                            ->label('Cargo')
                            ->required()
                            ->searchable()
                            ->options([
                                'Gerente' => 'Gerente',
                                'Cocinero' => 'Cocinero',
                                'Ayudante de cocina' => 'Ayudante de cocina',
                                'Mesero' => 'Mesero',
                                'Cajero' => 'Cajero',
                                'Limpieza' => 'Limpieza',
                                'Recepcionista' => 'Recepcionista',
                                'Repartidor' => 'Repartidor',
                                'Administrativo' => 'Administrativo',
                            ])
                            ->placeholder('Seleccione un cargo'),

                        Forms\Components\DatePicker::make('hire_date')
                            ->label('Fecha de Contratación')
                            ->required()
                            ->default(now())
                            ->placeholder('Seleccione fecha'),

                        Forms\Components\TextInput::make('base_salary')
                            ->label('Salario Base')
                            ->required()
                            ->numeric()
                            ->prefix('S/')
                            ->default(0.00)
                            ->placeholder('0.00'),
                    ]),

                Forms\Components\Section::make('Cuenta de Usuario')
                    ->description('Acceso al sistema (opcional)')
                    ->icon('heroicon-o-key')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Usuario Asociado')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Seleccione un usuario (opcional)')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre')
                                    ->required(),
                                Forms\Components\TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->required()
                                    ->email()
                                    ->unique(),
                                Forms\Components\TextInput::make('password')
                                    ->label('Contraseña')
                                    ->password()
                                    ->required()
                                    ->confirmed(),
                                Forms\Components\TextInput::make('password_confirmation')
                                    ->label('Confirmar Contraseña')
                                    ->password()
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fullName')
                    ->label('Nombre Completo')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('Documento')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-identification'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->icon('heroicon-o-phone'),

                Tables\Columns\TextColumn::make('position')
                    ->label('Cargo')
                    ->searchable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Gerente', 'Administrativo' => 'primary',
                        'Cocinero', 'Ayudante de cocina' => 'warning',
                        'Mesero', 'Cajero', 'Recepcionista' => 'success',
                        'Limpieza', 'Repartidor' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('hire_date')
                    ->label('Contratación')
                    ->date('d/m/Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar'),

                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Salario Base')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sin usuario')
                    ->toggleable(),

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
                Tables\Filters\SelectFilter::make('position')
                    ->label('Cargo')
                    ->options([
                        'Gerente' => 'Gerente',
                        'Cocinero' => 'Cocinero',
                        'Ayudante de cocina' => 'Ayudante de cocina',
                        'Mesero' => 'Mesero',
                        'Cajero' => 'Cajero',
                        'Limpieza' => 'Limpieza',
                        'Recepcionista' => 'Recepcionista',
                        'Repartidor' => 'Repartidor',
                        'Administrativo' => 'Administrativo',
                    ]),

                Tables\Filters\Filter::make('with_user')
                    ->label('Con cuenta de usuario')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('user_id')),

                Tables\Filters\Filter::make('without_user')
                    ->label('Sin cuenta de usuario')
                    ->query(fn(Builder $query): Builder => $query->whereNull('user_id')),

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
                ]),
            ])
            ->defaultSort('hire_date', 'desc')
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading('No hay empleados registrados')
            ->emptyStateDescription('Crea tu primer empleado para comenzar a gestionar tu equipo.')
            ->emptyStateActions([
                Tables\Actions\Action::make('crear')
                    ->label('Crear Empleado')
                    ->url(route('filament.admin.resources.employees.create'))
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
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Recursos Humanos';
    }
}
//cambios visuales
