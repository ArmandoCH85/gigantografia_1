<?php

namespace App\Filament\Pages;

use App\Models\CashRegister;
use App\Models\Payment;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class GestionCaja extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Gestión de Caja';

    protected static ?string $title = 'Gestión de Caja';

    protected static string $view = 'filament.pages.gestion-caja';

    protected static ?string $slug = 'gestion-caja';

    protected static ?string $navigationGroup = 'Ventas';
    
    // Propiedades para la vista
    public ?CashRegister $openRegister = null;
    public bool $hasOpenRegister = false;
    public float $dailySales = 0.00;
    public float $totalCash = 0.00;
    public float $openingAmount = 0.00;
    
    // Desglose por método de pago
    public float $cashPayments = 0.00;
    public float $cardPayments = 0.00;
    public float $transferPayments = 0.00;
    public float $digitalWalletPayments = 0.00;
    public float $otherPayments = 0.00;
    
    public $recentPayments = [];

    public function mount()
    {
        $this->refreshData();
    }

    public function refreshData()
    {
        $this->openRegister = CashRegister::where('status', 'open')->latest()->first();
        $this->hasOpenRegister = $this->openRegister !== null;

        if ($this->hasOpenRegister && $this->openRegister) {
            $this->openingAmount = $this->openRegister->opening_amount;
            
            $query = Payment::where('payment_datetime', '>=', $this->openRegister->opening_datetime);
            
            // Calcular ventas totales
            $this->dailySales = $query->sum('amount');
            
            // Desglose
            $this->cashPayments = Payment::where('payment_datetime', '>=', $this->openRegister->opening_datetime)
                ->where('payment_method', Payment::METHOD_CASH)
                ->sum('amount');
                
            $this->cardPayments = Payment::where('payment_datetime', '>=', $this->openRegister->opening_datetime)
                ->whereIn('payment_method', [Payment::METHOD_CARD, Payment::METHOD_CREDIT_CARD, Payment::METHOD_DEBIT_CARD])
                ->sum('amount');
                
            $this->transferPayments = Payment::where('payment_datetime', '>=', $this->openRegister->opening_datetime)
                ->where('payment_method', Payment::METHOD_BANK_TRANSFER)
                ->sum('amount');
                
            $this->digitalWalletPayments = Payment::where('payment_datetime', '>=', $this->openRegister->opening_datetime)
                ->where('payment_method', Payment::METHOD_DIGITAL_WALLET)
                ->sum('amount');
                
            $this->otherPayments = $this->dailySales - ($this->cashPayments + $this->cardPayments + $this->transferPayments + $this->digitalWalletPayments);
                
            $this->totalCash = $this->openingAmount + $this->cashPayments;

            // Obtener últimos 10 pagos
            $this->recentPayments = Payment::where('payment_datetime', '>=', $this->openRegister->opening_datetime)
                ->with(['order.customer'])
                ->latest('payment_datetime')
                ->limit(10)
                ->get();

        } else {
            $this->dailySales = 0;
            $this->totalCash = 0;
            $this->openingAmount = 0;
            $this->cashPayments = 0;
            $this->cardPayments = 0;
            $this->transferPayments = 0;
            $this->digitalWalletPayments = 0;
            $this->otherPayments = 0;
            $this->recentPayments = [];
        }
    }

    public function openRegisterAction(): Action
    {
        return Action::make('openRegister')
            ->label('Aperturar Caja')
            ->color('success')
            ->icon('heroicon-o-lock-open')
            ->modalHeading('Aperturar Caja')
            ->modalDescription('Ingrese el monto inicial para abrir la caja.')
            ->form([
                TextInput::make('opening_amount')
                    ->label('Monto Inicial (S/)')
                    ->numeric()
                    ->required()
                    ->prefix('S/')
                    ->default(0),
                Textarea::make('observations')
                    ->label('Observaciones')
                    ->placeholder('Opcional...')
            ])
            ->action(function (array $data) {
                CashRegister::create([
                    'opening_datetime' => now(),
                    'opening_amount' => $data['opening_amount'],
                    'opened_by' => Auth::id(),
                    'status' => 'open',
                    'observations' => $data['observations'] ?? null,
                ]);

                Notification::make()
                    ->title('Caja Aperturada Correctamente')
                    ->success()
                    ->send();

                $this->refreshData();
            });
    }

    public function closeRegisterAction(): Action
    {
        return Action::make('closeRegister')
            ->label('Cerrar Caja')
            ->color('danger')
            ->icon('heroicon-o-lock-closed')
            ->modalHeading('Cerrar Caja')
            ->modalDescription('Verifique los montos antes de cerrar.')
            ->form([
                TextInput::make('expected_amount')
                    ->label('Monto Esperado (Sistema)')
                    ->disabled()
                    ->default(fn () => $this->totalCash)
                    ->prefix('S/'),
                TextInput::make('actual_amount')
                    ->label('Monto Real en Caja (S/)')
                    ->numeric()
                    ->required()
                    ->prefix('S/'),
                Textarea::make('observations')
                    ->label('Observaciones de Cierre')
            ])
            ->action(function (array $data) {
                if (!$this->openRegister) return;

                $actualAmount = (float) $data['actual_amount'];
                $difference = $actualAmount - $this->totalCash;

                $this->openRegister->update([
                    'closing_datetime' => now(),
                    'closed_by' => Auth::id(),
                    'status' => 'closed',
                    'expected_amount' => $this->totalCash,
                    'actual_amount' => $actualAmount,
                    'difference' => $difference,
                    'observations' => ($this->openRegister->observations ? $this->openRegister->observations . "\n" : "") . "Cierre: " . ($data['observations'] ?? ''),
                ]);

                Notification::make()
                    ->title('Caja Cerrada Correctamente')
                    ->success()
                    ->send();

                $this->refreshData();
            });
    }
}
