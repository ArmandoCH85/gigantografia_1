<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Hash;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Set;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    public static function getNavigationLabel(): string
    {
        return trans('filament-users::user.resource.label');
    }

    public static function getPluralLabel(): string
    {
        return trans('filament-users::user.resource.label');
    }

    public static function getLabel(): string
    {
        return trans('filament-users::user.resource.single');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-users.group');
    }

    public function getTitle(): string
    {
        return trans('filament-users::user.resource.title.resource');
    }

    public static function form(Form $form): Form
    {
        $rows = [
            TextInput::make('name')
                ->required()
                ->label(trans('filament-users::user.resource.name')),
            TextInput::make('email')
                ->email()
                ->required()
                ->label(trans('filament-users::user.resource.email')),
            TextInput::make('login_code')
                ->label('Código PIN (6 dígitos)')
                ->required()
                ->minLength(6)
                ->maxLength(6)
                ->rule('digits:6')
                ->unique(ignoreRecord: true)
                ->helperText('Solo números, exactamente 6 dígitos. Requerido para login con PIN.')
                ->extraAttributes(['inputmode' => 'numeric', 'pattern' => '\\d{6}'])
                ->suffixAction(
                    Action::make('generarCodigo')
                        ->label('Generar')
                        ->icon('heroicon-m-sparkles')
                        ->action(function (Set $set) {
                            // Generar un código único de 6 dígitos, preservando ceros a la izquierda
                            $attempts = 0;
                            do {
                                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                                $exists = \App\Models\User::where('login_code', $code)->exists();
                                $attempts++;
                            } while ($exists && $attempts < 10);

                            $set('login_code', $code);
                        })
                ),
            TextInput::make('password')
                ->label(trans('filament-users::user.resource.password'))
                ->password()
                ->required()
                ->maxLength(255)
                ->dehydrateStateUsing(static function ($state, $record) {
                    return !empty($state)
                        ? Hash::make($state)
                        : ($record->password ?? null);
                }),
        ];


        if (config('filament-users.shield') && class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)) {
            $rows[] = Forms\Components\Select::make('roles')
                ->multiple()
                ->preload()
                ->relationship('roles', 'name')
                ->label(trans('filament-users::user.resource.roles'));
        }

        $form->schema($rows);

        return $form;
    }

    public static function table(Table $table): Table
    {
        if(class_exists( STS\FilamentImpersonate\Tables\Actions\Impersonate::class) && config('filament-users.impersonate')){
            $table->actions([Impersonate::make('impersonate')]);
        }
        $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->label(trans('filament-users::user.resource.id')),
                TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->label(trans('filament-users::user.resource.name')),
                TextColumn::make('email')
                    ->sortable()
                    ->searchable()
                    ->label(trans('filament-users::user.resource.email')),

                TextColumn::make('created_at')
                    ->label(trans('filament-users::user.resource.created_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(trans('filament-users::user.resource.updated_at'))
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('verified')
                    ->label(trans('filament-users::user.resource.verified'))
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('email_verified_at')),
                Tables\Filters\Filter::make('unverified')
                    ->label(trans('filament-users::user.resource.unverified'))
                    ->query(fn(Builder $query): Builder => $query->whereNull('email_verified_at')),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->action(function (User $record) {
                            try {
                                // Desvincular empleados asociados antes de eliminar
                                \App\Models\Employee::where('user_id', $record->id)->update(['user_id' => null]);

                                $record->delete();
                                
                                \Filament\Notifications\Notification::make()
                                    ->title('Usuario eliminado')
                                    ->body('Si tenía un empleado asociado, este ha sido desvinculado pero no eliminado.')
                                    ->success()
                                    ->send();
                                    
                            } catch (\Illuminate\Database\QueryException $e) {
                                // Fallback por si hay otras restricciones
                                if ($e->getCode() == 23000) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('No se puede eliminar el usuario')
                                        ->body('Hay datos relacionados que impiden la eliminación.')
                                        ->danger()
                                        ->send();
                                    return;
                                }
                                throw $e;
                            }
                        }),
                ]),
            ]);
        return $table;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
