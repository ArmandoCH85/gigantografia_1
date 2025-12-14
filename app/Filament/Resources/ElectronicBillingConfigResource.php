<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ElectronicBillingConfigResource\Pages;
use App\Models\AppSetting;
use App\Models\ElectronicBillingConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\HtmlString;

class ElectronicBillingConfigResource extends Resource
{
    protected static ?string $model = AppSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Facturación Electrónica';

    protected static ?string $modelLabel = 'Configuración de Facturación';

    protected static ?string $pluralModelLabel = 'Configuraciones de Facturación';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'configuracion/facturacion-electronica';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('tab', 'FacturacionElectronica');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Control de Entorno SUNAT')
                    ->description('Cambiar entre entorno de pruebas (Beta) y Producción')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('environment_toggle')
                                    ->label('Entorno de Producción')
                                    ->helperText('Activar para usar el entorno de producción de SUNAT')
                                    ->live()
                                    ->afterStateUpdated(function ($state) {
                                        $environment = $state ? 'production' : 'beta';
                                        AppSetting::where('tab', 'FacturacionElectronica')
                                            ->where('key', 'environment')
                                            ->update(['value' => $environment]);

                                        // Crear directorio si no existe
                                        $directory = storage_path("app/private/sunat/certificates/{$environment}");
                                        if (!file_exists($directory)) {
                                            mkdir($directory, 0755, true);
                                        }
                                    })
                                    ->default(function () {
                                        $env = AppSetting::getSetting('FacturacionElectronica', 'environment');
                                        return $env === 'production';
                                    }),

                                Forms\Components\Placeholder::make('current_environment')
                                    ->label('Estado Actual')
                                    ->content(function () {
                                        $env = AppSetting::getSetting('FacturacionElectronica', 'environment');
                                        $badge = match ($env) {
                                            'production' => '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 border border-green-200">🟢 Producción</span>',
                                            'beta' => '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200">🔵 Beta/Pruebas</span>',
                                            default => '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 border border-gray-200">⚪ No configurado</span>'
                                        };
                                        return new HtmlString($badge);
                                    }),
                            ]),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Gestión de Certificados SUNAT')
                    ->description('Cargar y gestionar certificados digitales para facturación electrónica')
                    ->icon('heroicon-o-document-check')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\FileUpload::make('certificate_file')
                                    ->label('Subir Certificado Digital')
                                    ->helperText('Formatos aceptados: .p12, .pfx, .pem (máximo 5MB)')
                                    ->maxSize(5120) // 5MB
                                    ->disk('certificates')
                                    ->directory(function () {
                                        $environment = AppSetting::getSetting('FacturacionElectronica', 'environment') ?: 'beta';
                                        return $environment;
                                    })
                                    ->visibility('private')
                                    ->afterStateUpdated(function ($state) {
                                        if ($state) {
                                            $fileName = basename($state);
                                            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                                            // Validar extensión
                                            if (!in_array($extension, ['p12', 'pfx', 'pem'])) {
                                                return; // No procesar si la extensión no es válida
                                            }

                                            $environment = AppSetting::getSetting('FacturacionElectronica', 'environment') ?: 'beta';
                                            $fullPath = storage_path("app/private/sunat/certificates/{$environment}/" . $fileName);

                                            // Crear directorio si no existe
                                            $directory = dirname($fullPath);
                                            if (!file_exists($directory)) {
                                                mkdir($directory, 0755, true);
                                            }

                                            AppSetting::updateOrCreate(
                                                ['tab' => 'FacturacionElectronica', 'key' => 'certificate_path'],
                                                ['value' => $fullPath]
                                            );
                                        }
                                    })
                                    ->dehydrated(false),

                                Forms\Components\Placeholder::make('certificate_status')
                                    ->label('Estado del Certificado')
                                    ->content(function () {
                                        $path = AppSetting::getSetting('FacturacionElectronica', 'certificate_path');
                                        if ($path && file_exists($path)) {
                                            $filename = basename($path);
                                            $size = round(filesize($path) / 1024, 2);
                                            $env = AppSetting::getSetting('FacturacionElectronica', 'environment');
                                            $envBadge = $env === 'production' ?
                                                '<span class="text-green-600 font-medium">Producción</span>' :
                                                '<span class="text-blue-600 font-medium">Beta</span>';
                                            return new HtmlString("
                                                <div class='space-y-2'>
                                                    <div class='flex items-center space-x-2'>
                                                        <span class='text-green-500'>✅</span>
                                                        <span class='font-medium'>Certificado cargado</span>
                                                    </div>
                                                    <div class='text-sm text-gray-600'>
                                                        <div>📄 {$filename}</div>
                                                        <div>📊 {$size} KB</div>
                                                        <div>🌐 Entorno: {$envBadge}</div>
                                                    </div>
                                                </div>
                                            ");
                                        }
                                        return new HtmlString("
                                            <div class='flex items-center space-x-2 text-amber-600'>
                                                <span>⚠️</span>
                                                <span>No hay certificado cargado</span>
                                            </div>
                                        ");
                                    }),
                            ]),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Configuración de Facturación Electrónica')
                    ->description('Configuración para la conexión con SUNAT')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\TextInput::make('value')
                            ->label(function ($record) {
                                return match ($record->key) {
                                    'soap_type' => 'Tipo de Conexión SOAP',
                                    'environment' => 'Entorno',
                                    'sol_user' => 'Usuario SOL',
                                    'sol_password' => 'Contraseña SOL',
                                    'certificate_path' => 'Ruta del Certificado',
                                    'certificate_password' => 'Contraseña del Certificado',
                                    'send_automatically' => 'Enviar Automáticamente',
                                    'generate_pdf' => 'Generar PDF',
                                    'igv_percent' => 'Porcentaje de IGV',
                                    'qpse_endpoint_beta' => '🧪 Endpoint QPSE Beta',
                                    'qpse_endpoint_production' => '🚀 Endpoint QPSE Producción',
                                    'qpse_username' => '👤 Usuario QPSE',
                                    'qpse_password' => '🔑 Contraseña QPSE',
                                    default => ucfirst(str_replace('_', ' ', $record->key)),
                                };
                            })
                            ->helperText(function ($record) {
                                return match ($record->key) {
                                    'soap_type' => 'Tipo de conexión SOAP (sunat o ose)',
                                    'environment' => 'Entorno - Controlado automáticamente por el toggle superior',
                                    'sol_user' => 'Usuario secundario SOL',
                                    'sol_password' => 'Contraseña SOL (se guardará cifrada)',
                                    'certificate_path' => 'Ruta al certificado - Se actualiza automáticamente al subir archivo',
                                    'certificate_password' => 'Contraseña del certificado (se guardará cifrada)',
                                    'send_automatically' => 'Si los comprobantes se envían automáticamente (true/false)',
                                    'generate_pdf' => 'Si se generan PDFs automáticamente (true/false)',
                                    'igv_percent' => 'Porcentaje de IGV (18.00)',
                                    'qpse_endpoint_beta' => '🧪 URL del endpoint QPSE para PRUEBAS. Ejemplo: https://demo-cpe.qpse.pe',
                                    'qpse_endpoint_production' => '🚀 URL del endpoint QPSE para PRODUCCIÓN. Ejemplo: https://cpe.qpse.pe',
                                    'qpse_username' => '👤 Usuario QPSE (credenciales proporcionadas por QPSE)',
                                    'qpse_password' => '🔑 Contraseña QPSE (se guardará cifrada)',
                                    default => null,
                                };
                            })
                            ->required(function ($record) {
                                // No requerir campos que se manejan automáticamente o son opcionales
                                return !in_array($record->key, ['environment', 'certificate_path', 'qpse_endpoint_beta', 'qpse_endpoint_production', 'qpse_username', 'qpse_password']);
                            })
                            ->maxLength(function ($record) {
                                return $record && in_array($record->key, ['qpse_endpoint_beta', 'qpse_endpoint_production']) ? 500 : 255;
                            })
                            ->columnSpanFull()
                            ->disabled(function ($record) {
                                // Deshabilitar campos que se manejan automáticamente
                                return in_array($record->key, ['environment', 'certificate_path']);
                            })
                            ->password(function ($record) {
                                // Mostrar como contraseña para campos sensibles
                                return in_array($record->key, ['sol_password', 'certificate_password', 'qpse_password']);
                            })
                            ->rules(function ($record) {
                                if ($record && in_array($record->key, ['qpse_endpoint_beta', 'qpse_endpoint_production'])) {
                                    return [
                                        'nullable',
                                        'string',
                                        'url',
                                        'max:500',
                                        function (string $attribute, $value, \Closure $fail) {
                                            if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                                                $fail('El endpoint debe ser una URL válida.');
                                            }
                                            if ($value && !str_starts_with($value, 'https://')) {
                                                $fail('El endpoint debe usar HTTPS para mayor seguridad.');
                                            }
                                        },
                                    ];
                                }
                                return [];
                            })
                            ->dehydrateStateUsing(function ($state, $record) {
                                // Cifrar valores sensibles
                                if (in_array($record->key, ['sol_password', 'certificate_password', 'qpse_password']) && $state) {
                                    return Crypt::encryptString($state);
                                }
                                return $state;
                            })
                            ->afterStateHydrated(function ($component, $state, $record) {
                                // No mostrar valores cifrados en el formulario
                                if (in_array($record->key, ['sol_password', 'certificate_password', 'qpse_password'])) {
                                    $component->state('');
                                }
                            })
                            ->extraInputAttributes(function ($record) {
                                if ($record && $record->key === 'qpse_endpoint_beta') {
                                    return [
                                        'style' => 'border: 2px solid #f59e0b; background-color: #fef3c7;',
                                        'placeholder' => 'https://beta.qpse.com/api/v1/invoices'
                                    ];
                                }
                                if ($record && $record->key === 'qpse_endpoint_production') {
                                    return [
                                        'style' => 'border: 2px solid #10b981; background-color: #f0fdf4;',
                                        'placeholder' => 'https://api.qpse.com/v1/invoices'
                                    ];
                                }
                                return [];
                            }),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Información Adicional')
                    ->description('Esta configuración se utiliza para la emisión de comprobantes electrónicos')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\Placeholder::make('info')
                            ->content(new HtmlString('
                                <div class="text-sm text-gray-500">
                                    <p>Esta información es utilizada para la conexión con SUNAT para la emisión de comprobantes electrónicos.</p>
                                    <p class="mt-2">Las contraseñas se almacenan cifradas en la base de datos por seguridad.</p>
                                    <p class="mt-2 text-amber-500 font-medium">Importante: En entorno de producción, asegúrese de utilizar credenciales reales y un certificado digital válido.</p>
                                </div>
                            ')),

                        Forms\Components\Placeholder::make('default')
                            ->label('Valor por Defecto')
                            ->content(function ($record) {
                                if (in_array($record->key, ['sol_password', 'certificate_password'])) {
                                    return '********';
                                }
                                return $record->default ?? 'No definido';
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(),
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
            'index' => Pages\ManageElectronicBillingConfig::route('/'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Configuración')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'soap_type' => 'Tipo de Conexión SOAP',
                            'environment' => 'Entorno',
                            'sol_user' => 'Usuario SOL',
                            'sol_password' => 'Contraseña SOL',
                            'certificate_path' => 'Ruta del Certificado',
                            'certificate_password' => 'Contraseña del Certificado',
                            'send_automatically' => 'Enviar Automáticamente',
                            'generate_pdf' => 'Generar PDF',
                            'igv_percent' => 'Porcentaje de IGV',
                            'qpse_endpoint_beta' => '🧪 Endpoint QPSE Beta',
                            'qpse_endpoint_production' => '🚀 Endpoint QPSE Producción',
                            'qpse_username' => '👤 Usuario QPSE',
                            'qpse_password' => '🔑 Contraseña QPSE',
                            default => ucfirst(str_replace('_', ' ', $state)),
                        };
                    }),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valor')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        if (in_array($record->key, ['sol_password', 'certificate_password', 'qpse_password'])) {
                            return '********';
                        }
                        if ($record->key === 'qpse_endpoint_beta' && $state) {
                            return $state . ' 🧪';
                        }
                        if ($record->key === 'qpse_endpoint_production' && $state) {
                            return $state . ' 🚀';
                        }
                        if ($record->key === 'qpse_username' && $state) {
                            return $state . ' 👤';
                        }
                        return $state;
                    })
                    ->color(function ($record) {
                        if ($record->key === 'qpse_endpoint_beta') {
                            return 'warning';
                        }
                        if ($record->key === 'qpse_endpoint_production') {
                            return 'success';
                        }
                        return null;
                    })
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }
}
